<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateProfileDto;
use App\Dto\RegisterDto;
use App\Entity\Household;
use App\Entity\Place;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\ProfileRole;
use App\Exception\ValidationFailedHttpException;
use App\Security\AuthResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AuthService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private UserPasswordHasherInterface $hasher,
        private Security $security,
    ) {
    }

    /**
     * Registers a parent: Household + User + parent Profile + default Place,
     * then logs the parent in right away (same session cookie).
     *
     * @return array<string, mixed>
     */
    public function register(RegisterDto $dto): array
    {
        $violations = $this->validator->validate($dto);

        if (\count($violations) > 0) {
            throw ValidationFailedHttpException::fromViolations($violations);
        }

        $email = strtolower(trim((string) $dto->email));

        if (null !== $this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            throw new UnprocessableEntityHttpException('This e-mail is already registered.');
        }

        if ($dto->password !== $dto->passwordConfirm) {
            throw new UnprocessableEntityHttpException('Password confirmation does not match.');
        }

        $householdName = trim((string) $dto->householdName);
        $household = new Household($householdName, $this->uniqueSlug($householdName));
        $this->em->persist($household);

        $user = new User($email, $household);
        $user->setPassword($this->hasher->hashPassword($user, (string) $dto->password));
        $this->em->persist($user);

        $profile = new Profile($household, trim((string) $dto->firstName), ProfileRole::Parent, $user);
        $this->em->persist($profile);

        $placeName = trim((string) ($dto->placeName ?? ''));
        $place = new Place($household, '' === $placeName ? 'Maison' : $placeName, true);
        $this->em->persist($place);

        $this->em->flush();

        // The firewall holds two authenticators, so the json_login one is named explicitly.
        $this->security->login($user, 'security.authenticator.json_login.main');

        return [
            'user' => AuthResponse::userPayload($user),
            'household' => AuthResponse::householdPayload($household),
            'profile' => AuthResponse::profilePayload($profile),
            'place' => AuthResponse::placePayload($place),
            'activeProfileId' => $profile->getId(),
        ];
    }

    /**
     * Creates a child profile (name + PIN, no credentials) or a partner profile
     * (own e-mail + password, own User row) inside the actor's household.
     *
     * @return array<string, mixed>
     */
    public function createProfile(CurrentActor $actor, CreateProfileDto $dto): array
    {
        $actor->requireAdultProfile();
        $household = $actor->requireHousehold();

        $violations = $this->validator->validate($dto);

        if (\count($violations) > 0) {
            throw ValidationFailedHttpException::fromViolations($violations);
        }

        $role = ProfileRole::from((string) $dto->role);
        $name = trim((string) $dto->name);

        if (ProfileRole::Child === $role) {
            $pin = (string) $dto->pin;

            if (1 !== preg_match('/^\d{4,6}$/', $pin)) {
                throw new UnprocessableEntityHttpException('PIN must be 4 to 6 digits.');
            }

            $child = new Profile($household, $name, ProfileRole::Child);
            $child->setPinHash($this->hasher->hashPassword($child, $pin));
            $child->setAvatarColor($dto->avatarColor);
            $this->em->persist($child);
            $this->em->flush();

            return ['profile' => AuthResponse::profilePayload($child)];
        }

        $email = strtolower(trim((string) $dto->email));

        if ('' === $email || false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new UnprocessableEntityHttpException('A valid e-mail is required for a partner profile.');
        }

        if (null !== $this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            throw new UnprocessableEntityHttpException('This e-mail is already registered.');
        }

        $password = (string) $dto->password;

        if (\strlen($password) < 8) {
            throw new UnprocessableEntityHttpException('Password must be at least 8 characters.');
        }

        $partnerUser = new User($email, $household);
        $partnerUser->setPassword($this->hasher->hashPassword($partnerUser, $password));
        $this->em->persist($partnerUser);

        $partner = new Profile($household, $name, ProfileRole::Partner, $partnerUser);

        if (null !== $dto->pin && '' !== $dto->pin) {
            if (1 !== preg_match('/^\d{4,6}$/', (string) $dto->pin)) {
                throw new UnprocessableEntityHttpException('PIN must be 4 to 6 digits.');
            }

            $partner->setPinHash($this->hasher->hashPassword($partner, (string) $dto->pin));
        }

        $partner->setAvatarColor($dto->avatarColor);
        $this->em->persist($partner);
        $this->em->flush();

        return [
            'profile' => AuthResponse::profilePayload($partner),
            'user' => AuthResponse::userPayload($partnerUser),
        ];
    }

    /**
     * Switches the session to the given profile of the actor's household.
     * Unknown or foreign profiles answer 404 (no existence leak = IDOR-safe).
     */
    public function switchProfile(CurrentActor $actor, int $profileId, Request $request): Profile
    {
        $household = $actor->requireHousehold();

        $target = $this->em->getRepository(Profile::class)->find($profileId);

        if (!$target instanceof Profile || $target->getHousehold()->getId() !== $household->getId()) {
            throw new NotFoundHttpException('Profile not found.');
        }

        $active = $actor->profile();

        if ($active instanceof Profile && !$active->isAdult() && $active->getId() !== $target->getId()) {
            throw new AccessDeniedHttpException('A child profile cannot switch to another profile.');
        }

        $request->getSession()->set('penderie.active_profile_id', $target->getId());

        return $target;
    }

    /**
     * @return array<string, mixed>
     */
    public function mePayload(CurrentActor $actor): array
    {
        $household = $actor->requireHousehold();
        $active = $actor->profile();

        $profiles = $this->em->getRepository(Profile::class)->findBy(['household' => $household], ['id' => 'ASC']);
        $places = $this->em->getRepository(Place::class)->findBy(['household' => $household], ['id' => 'ASC']);

        $payload = [
            'household' => AuthResponse::householdPayload($household),
            'profiles' => array_map([AuthResponse::class, 'profilePayload'], $profiles),
            'places' => array_map([AuthResponse::class, 'placePayload'], $places),
            'activeProfileId' => $active?->getId(),
            'activeProfile' => null !== $active ? AuthResponse::profilePayload($active) : null,
        ];

        $tokenUser = $this->security->getUser();

        if ($tokenUser instanceof User) {
            $payload['user'] = AuthResponse::userPayload($tokenUser);
        }

        return $payload;
    }

    private function uniqueSlug(string $householdName): string
    {
        $base = (string) (new AsciiSlugger())->slug($householdName)->lower();

        if ('' === $base) {
            $base = 'foyer';
        }

        $slug = $base;
        $suffix = 0;

        while (null !== $this->em->getRepository(Household::class)->findOneBy(['slug' => $slug])) {
            ++$suffix;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
