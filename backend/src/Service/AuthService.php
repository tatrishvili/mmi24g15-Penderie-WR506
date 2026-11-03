<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateHouseholdDto;
use App\Dto\CreateProfileDto;
use App\Dto\RegisterDto;
use App\Entity\ChildAccount;
use App\Entity\Household;
use App\Entity\HouseholdMember;
use App\Entity\PendingRegistration;
use App\Entity\Place;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\ProfileRole;
use App\Exception\ValidationFailedHttpException;
use App\Security\AuthResponse;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
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
        private MailerInterface $mailer,
        private string $frontendBaseUrl,
        private string $mailerFromAddress,
    ) {
    }

    /**
     * Store a short-lived pending registration with a password hash until the email is verified.
     */
    public function register(RegisterDto $dto): void
    {
        $violations = $this->validator->validate($dto);

        if (\count($violations) > 0) {
            throw ValidationFailedHttpException::fromViolations($violations);
        }

        if ($dto->password !== $dto->passwordConfirm) {
            throw new UnprocessableEntityHttpException('Password confirmation does not match.');
        }

        $email = strtolower(trim((string) $dto->email));

        if (null !== $this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return;
        }

        $pending = $this->em->getRepository(PendingRegistration::class)->findOneBy(['email' => $email]);
        $username = (string) $dto->username;
        $userWithUsername = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        $pendingWithUsername = $this->em->getRepository(PendingRegistration::class)->findOneBy(['username' => $username]);

        if (
            null !== $userWithUsername
            || ($pendingWithUsername instanceof PendingRegistration && $pendingWithUsername->getEmail() !== $email)
        ) {
            throw new UnprocessableEntityHttpException('This username is already in use. Choose another one.');
        }

        $now = new \DateTimeImmutable();

        if (
            $pending instanceof PendingRegistration
            && $pending->getLastSentAt() > $now->modify('-1 minute')
        ) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = $now->modify('+1 hour');
        $passwordUser = new User($email, '');
        $passwordHash = $this->hasher->hashPassword($passwordUser, (string) $dto->password);
        $birthDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $dto->birthDate);

        if (false === $birthDate) {
            throw new UnprocessableEntityHttpException('Enter a valid date of birth.');
        }

        if ($pending instanceof PendingRegistration) {
            $pending->refresh($passwordHash, hash('sha256', $token), $expiresAt, $username, $birthDate);
        } else {
            $pending = new PendingRegistration(
                $email,
                $passwordHash,
                hash('sha256', $token),
                $expiresAt,
                $username,
                $birthDate,
            );
            $this->em->persist($pending);
        }

        $this->em->flush();
        $this->sendVerificationEmail($email, $token);
    }

    public function setUsername(CurrentActor $actor, string $username): string
    {
        $user = $actor->requireUser();

        if (!$user->isEmailVerified()) {
            throw new AccessDeniedHttpException('Verify your e-mail before setting a username.');
        }
        if (null !== $user->getUsername()) {
            throw new ConflictHttpException('A username is already set for this account.');
        }

        $username = strtolower(trim($username));
        if (1 !== preg_match('/\A[a-z0-9_]{3,30}\z/', $username)) {
            throw new UnprocessableEntityHttpException('Use 3 to 30 letters, numbers, or underscores.');
        }

        if (null !== $this->em->getRepository(User::class)->findOneBy(['username' => $username])) {
            throw new ConflictHttpException('This username is already in use.');
        }
        if (null !== $this->em->getRepository(PendingRegistration::class)->findOneBy(['username' => $username])) {
            throw new ConflictHttpException('This username is already in use.');
        }

        $user->setUsername($username);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictHttpException('This username is already in use.', $exception);
        }

        return $username;
    }

    public function resendVerification(string $email): void
    {
        $email = strtolower(trim($email));
        $pending = $this->em->getRepository(PendingRegistration::class)->findOneBy(['email' => $email]);
        $now = new \DateTimeImmutable();

        if (
            !$pending instanceof PendingRegistration
            || $pending->getLastSentAt() > $now->modify('-1 minute')
        ) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $pending->refresh(
            $pending->getPasswordHash(),
            hash('sha256', $token),
            $now->modify('+1 hour'),
            $pending->getUsername(),
            $pending->getBirthDate(),
        );
        $this->em->flush();
        $this->sendVerificationEmail($email, $token);
    }

    /**
     * Verify the address, create its private starter space, and sign the user in.
     *
     * @return array<string, mixed>
     */
    public function verifyEmail(string $token, Request $request): array
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new BadRequestHttpException('This verification link is invalid or has expired.');
        }

        return $this->em->wrapInTransaction(function (EntityManagerInterface $em) use ($token, $request): array {
            $pending = $em->getRepository(PendingRegistration::class)->findOneBy([
                'tokenHash' => hash('sha256', $token),
            ]);

            if (
                !$pending instanceof PendingRegistration
                || $pending->getExpiresAt() <= new \DateTimeImmutable()
            ) {
                throw new BadRequestHttpException('This verification link is invalid or has expired.');
            }

            $email = $pending->getEmail();
            $passwordHash = $pending->getPasswordHash();
            $username = $pending->getUsername();
            $birthDate = $pending->getBirthDate();

            if (null === $username || null === $birthDate) {
                throw new BadRequestHttpException('Registration details are incomplete. Please register again.');
            }

            if (null !== $em->getRepository(User::class)->findOneBy(['username' => $username])) {
                throw new UnprocessableEntityHttpException('This username is no longer available. Register again with another one.');
            }

            $em->remove($pending);

            $user = new User($email, 'Moi', $username, $birthDate);
            $user->setPassword($passwordHash);
            $householdName = 'Mon dressing';
            $household = new Household($householdName, $this->uniqueSlug($householdName));
            $profile = new Profile($household, 'Moi', ProfileRole::Parent, $user);
            $place = new Place($household, 'Maison', true);

            $em->persist($user);
            $em->persist($household);
            $em->persist(new HouseholdMember($user, $household));
            $em->persist($profile);
            $em->persist($place);
            $em->flush();

            // The firewall has two authenticators; use the configured password authenticator.
            $this->security->login($user, 'security.authenticator.json_login.main');
            $session = $request->getSession();
            $session->set('penderie.active_household_id', $household->getId());
            $session->set('penderie.active_profile_id', $profile->getId());

            return [
                'user' => AuthResponse::userPayload($user),
                'households' => [AuthResponse::householdPayload($household)],
                'household' => AuthResponse::householdPayload($household),
                'profiles' => [AuthResponse::profilePayload($profile)],
                'places' => [AuthResponse::placePayload($place)],
                'activeHouseholdId' => $household->getId(),
                'activeProfileId' => $profile->getId(),
                'activeProfile' => AuthResponse::profilePayload($profile),
            ];
        });
    }

    /**
     * Creates a foyer for the logged-in account holder: Household + membership +
     * parent Profile (named after the user) + default Place. Activates it
     * right away in the session.
     *
     * @return array<string, mixed>
     */
    public function createHousehold(CurrentActor $actor, CreateHouseholdDto $dto, Request $request): array
    {
        $user = $actor->requireUser();

        $violations = $this->validator->validate($dto);

        if (\count($violations) > 0) {
            throw ValidationFailedHttpException::fromViolations($violations);
        }

        $householdName = trim((string) $dto->householdName);
        $firstName = trim((string) $dto->firstName);
        $household = new Household($householdName, $this->uniqueSlug($householdName));
        $this->em->persist($household);

        $this->em->persist(new HouseholdMember($user, $household));

        $user->setFirstName($firstName);

        $profile = new Profile($household, $firstName, ProfileRole::Parent, $user);
        $this->em->persist($profile);

        $placeName = trim((string) ($dto->placeName ?? ''));
        $place = new Place($household, '' === $placeName ? 'Maison' : $placeName, true);
        $this->em->persist($place);

        $this->em->flush();

        $session = $request->getSession();
        $session->set('penderie.active_household_id', $household->getId());
        $session->set('penderie.active_profile_id', $profile->getId());

        return [
            'household' => AuthResponse::householdPayload($household),
            'profile' => AuthResponse::profilePayload($profile),
            'place' => AuthResponse::placePayload($place),
            'activeHouseholdId' => $household->getId(),
            'activeProfileId' => $profile->getId(),
        ];
    }

    /**
     * Switches the session to another foyer of the user. Unknown or foreign
     * foyers answer 404 (no existence leak = IDOR-safe). The user's own adult
     * profile there (if any) is activated automatically.
     *
     * @return array<string, mixed>
     */
    public function selectHousehold(CurrentActor $actor, int $householdId, Request $request): array
    {
        $user = $actor->requireUser();

        $household = $this->em->getRepository(Household::class)->find($householdId);

        if (!$household instanceof Household || !$actor->isMember($user, $household)) {
            throw new NotFoundHttpException('Household not found.');
        }

        $own = $this->em->getRepository(Profile::class)->findOneBy(
            ['user' => $user, 'household' => $household],
            ['id' => 'ASC'],
        );

        $ownAdult = $own instanceof Profile && $own->isAdult() ? $own : null;

        $session = $request->getSession();
        $session->set('penderie.active_household_id', $household->getId());
        $session->set('penderie.active_profile_id', $ownAdult?->getId());

        return [
            'household' => AuthResponse::householdPayload($household),
            'activeHouseholdId' => $household->getId(),
            'activeProfileId' => $ownAdult?->getId(),
        ];
    }

    /**
     * Creates a child profile (no independent sign-in yet) or a partner profile
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
            if (null !== $dto->pin && '' !== $dto->pin) {
                if (1 !== preg_match('/^\d{4,6}$/', $dto->pin)) {
                    throw new UnprocessableEntityHttpException('PIN must be 4 to 6 digits.');
                }
            }

            $child = new Profile($household, $name, ProfileRole::Child);
            $child->createdBy = $actor->requireUser();
            if (null !== $dto->pin && '' !== $dto->pin) {
                $child->setPinHash($this->hasher->hashPassword($child, $dto->pin));
            }
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

        $partnerUser = new User($email, $name);
        $partnerUser->setEmailVerified(false);
        $partnerUser->setPassword($this->hasher->hashPassword($partnerUser, $password));
        $this->em->persist($partnerUser);

        $this->em->persist(new HouseholdMember($partnerUser, $household));

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
        if ($actor->tokenUser() instanceof ChildAccount) {
            throw new AccessDeniedHttpException('A child account cannot switch profiles.');
        }
        $household = $actor->requireHousehold();

        $target = $this->em->getRepository(Profile::class)->find($profileId);

        if (!$target instanceof Profile || $target->getHousehold()->getId() !== $household->getId()) {
            throw new NotFoundHttpException('Profile not found.');
        }

        if ($target->isAdult() && $target->getUser()?->getId() !== $actor->requireUser()->getId()) {
            throw new AccessDeniedHttpException('Each adult must sign in with their own account.');
        }

        if (null !== $target->getPinHash()) {
            throw new AccessDeniedHttpException('This profile requires its PIN on this device.');
        }

        $active = $actor->profile();

        if (!$target->isAdult() && null !== $active && $active->isAdult()
            && !(new FamilyAccess($this->em))->isGuardian($actor->requireUser(), $target)) {
            throw new AccessDeniedHttpException('Only this child\'s mother or father can manage this profile.');
        }

        if ($active instanceof Profile && !$active->isAdult() && $active->getId() !== $target->getId()) {
            throw new AccessDeniedHttpException('A child profile cannot switch to another profile.');
        }

        if (!$target->isAdult()) {
            $account = $this->em->getRepository(ChildAccount::class)->findOneBy(['profile' => $target]);
            if ($account instanceof ChildAccount && !(new FamilyAccess($this->em))->childEnabled($account)) {
                throw new AccessDeniedHttpException('Child access is disabled.');
            }
            $request->getSession()->set('penderie.child_session_version', $account?->sessionVersion);
        }
        $request->getSession()->set('penderie.active_profile_id', $target->getId());

        return $target;
    }

    /**
     * The session state: always the household list; household content only when
     * a foyer is active (a fresh account has none yet — that is normal).
     *
     * @return array<string, mixed>
     */
    public function mePayload(CurrentActor $actor): array
    {
        $tokenUser = $this->security->getUser();

        $payload = [
            'households' => array_map([AuthResponse::class, 'householdPayload'], $actor->households()),
            'household' => null,
            'profiles' => [],
            'places' => [],
            'activeHouseholdId' => null,
            'activeProfileId' => null,
            'activeProfile' => null,
        ];

        if ($tokenUser instanceof ChildAccount) {
            $payload['childAccount'] = ['username' => $tokenUser->username, 'mustChangePassword' => $tokenUser->mustChangePassword];
        }
        $active = $actor->profile();
        $childMode = $actor->isRestrictedProfile();
        if ($childMode && null === $active) {
            $payload['restrictedSessionRevoked'] = true;
        }

        if ($childMode) {
            $payload['households'] = [];
        }

        if ($tokenUser instanceof User && !$childMode) {
            $payload['user'] = AuthResponse::userPayload($tokenUser);
        }

        $household = $actor->household();

        if (null === $household) {
            return $payload;
        }

        $profiles = $childMode ? (null !== $active ? [$active] : []) : $this->em->getRepository(Profile::class)->findBy(['household' => $household], ['id' => 'ASC']);
        $places = $childMode ? [] : $this->em->getRepository(Place::class)->findBy(['household' => $household], ['id' => 'ASC']);

        if ($childMode) {
            $payload['households'] = [AuthResponse::householdPayload($household)];
        }

        $payload['household'] = AuthResponse::householdPayload($household);
        $payload['profiles'] = array_map([AuthResponse::class, 'profilePayload'], $profiles);
        $payload['places'] = array_map([AuthResponse::class, 'placePayload'], $places);
        $payload['activeHouseholdId'] = $household->getId();
        $payload['activeProfileId'] = $active?->getId();
        $payload['activeProfile'] = null !== $active ? AuthResponse::profilePayload($active) : null;

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

    private function sendVerificationEmail(string $email, string $token): void
    {
        $verificationUrl = rtrim($this->frontendBaseUrl, '/').'/verifier-email?token='.rawurlencode($token);

        $message = (new Email())
            ->from(new Address($this->mailerFromAddress, 'Penderie'))
            ->to($email)
            ->subject('Confirmez votre adresse e-mail')
            ->text(
                "Bonjour,\n\n"
                ."Pour confirmer votre adresse et créer votre compte Penderie, ouvrez ce lien :\n"
                .$verificationUrl
                ."\n\nCe lien expire dans une heure. Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.",
            );

        $this->mailer->send($message);
    }
}
