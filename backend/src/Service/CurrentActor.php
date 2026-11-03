<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Household;
use App\Entity\HouseholdMember;
use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Resolves the signed-in account and selected household/profile from the
 * security token and session. PINs only unlock profiles inside a User session.
 *
 * A User can belong to several households: the ACTIVE one lives in the
 * session (chosen with /households/{id}/select), never on the User.
 */
final class CurrentActor
{
    public function __construct(
        private TokenStorageInterface $tokens,
        private RequestStack $requests,
        private EntityManagerInterface $em,
    ) {
    }

    public function tokenUser(): ?object
    {
        $user = $this->tokens->getToken()?->getUser();

        if ($user instanceof Profile) {
            throw new UnauthorizedHttpException('', 'This profile sign-in is no longer supported.');
        }

        return $user;
    }

    /**
     * @throws AccessDeniedHttpException when no account holder is authenticated
     */
    public function requireUser(): User
    {
        $user = $this->tokenUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Only an account holder can perform this action.');
        }

        if ($this->isRestrictedProfile()) {
            throw new AccessDeniedHttpException('Sign in again as an adult to perform this action.');
        }

        return $user;
    }

    public function household(): ?Household
    {
        $user = $this->tokenUser();

        if (!$user instanceof User) {
            return null;
        }

        $householdId = $this->requests->getSession()->get('penderie.active_household_id');

        if (!\is_int($householdId)) {
            return null;
        }

        $household = $this->em->getRepository(Household::class)->find($householdId);

        if (!$household instanceof Household || !$this->isMember($user, $household)) {
            return null;
        }

        $profileId = $this->requests->getSession()->get('penderie.active_profile_id');
        if (null !== $profileId) {
            $profile = \is_int($profileId) ? $this->em->getRepository(Profile::class)->find($profileId) : null;
            if (!$profile instanceof Profile || $profile->getHousehold()->getId() !== $household->getId()) {
                return null;
            }
        }

        return $household;
    }

    public function requireHousehold(): Household
    {
        $household = $this->household();

        if (null === $household) {
            throw new UnauthorizedHttpException('', 'No active household. Pick or create a foyer first.');
        }

        return $household;
    }

    public function isMember(User $user, Household $household): bool
    {
        return null !== $this->em->getRepository(HouseholdMember::class)->findOneBy([
            'user' => $user,
            'household' => $household,
        ]);
    }

    /**
     * @return list<Household>
     */
    public function households(): array
    {
        $user = $this->tokenUser();

        if ($user instanceof User) {
            return $this->householdsOf($user);
        }

        return [];
    }

    /**
     * @return list<Household>
     */
    private function householdsOf(User $user): array
    {
        $members = $this->em->getRepository(HouseholdMember::class)->findBy(['user' => $user], ['id' => 'ASC']);

        return array_map(static fn (HouseholdMember $member): Household => $member->getHousehold(), $members);
    }

    /**
     * The active profile selected inside the ACTIVE household.
     */
    public function profile(): ?Profile
    {
        $user = $this->tokenUser();

        if (!$user instanceof User) {
            return null;
        }

        $household = $this->household();

        if (null === $household) {
            return null;
        }

        $profileId = $this->requests->getSession()->get('penderie.active_profile_id');

        if (!\is_int($profileId)) {
            return null;
        }

        $profile = $this->em->getRepository(Profile::class)->find($profileId);

        if (!$profile instanceof Profile || $profile->getHousehold()->getId() !== $household->getId()) {
            return null;
        }

        return $profile;
    }

    public function isRestrictedProfile(): bool
    {
        $profile = $this->profile();

        // A removed/invalid selected profile must not restore the adult identity
        // still carried by a trusted-device session.
        return null !== $profile
            ? !$profile->isAdult()
            : null !== $this->requests->getSession()->get('penderie.active_profile_id');
    }

    /**
     * Returns an adult profile allowed to manage the ACTIVE household: the active
     * profile when it is an adult, otherwise the logged-in user's own profile
     * only when no profile is selected. A selected child never inherits it.
     *
     * @throws AccessDeniedHttpException when the actor is a child
     */
    public function requireAdultProfile(): Profile
    {
        $user = $this->tokenUser();
        $household = $this->household();

        $active = $this->profile();

        if ($this->isRestrictedProfile()) {
            throw new AccessDeniedHttpException('Only an adult profile can perform this action. Sign in again as an adult.');
        }

        if ($active instanceof Profile && $active->isAdult()) {
            return $active;
        }

        if ($user instanceof User && null !== $household) {
            $own = $this->em->getRepository(Profile::class)->findOneBy(['user' => $user, 'household' => $household]);

            if ($own instanceof Profile && $own->isAdult()) {
                return $own;
            }
        }

        throw new AccessDeniedHttpException('Only an adult profile can perform this action.');
    }
}
