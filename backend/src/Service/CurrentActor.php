<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Household;
use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Resolves who is acting on the current request from the security token
 * (a User after e-mail login, a Profile after PIN login) plus the session's
 * active profile id (set by profile switching).
 */
final class CurrentActor
{
    public function __construct(
        private TokenStorageInterface $tokens,
        private RequestStack $requests,
        private EntityManagerInterface $em,
    ) {
    }

    public function household(): ?Household
    {
        $user = $this->securityUser();

        if ($user instanceof User) {
            return $user->getHousehold();
        }

        if ($user instanceof Profile) {
            return $user->getHousehold();
        }

        return null;
    }

    public function requireHousehold(): Household
    {
        $household = $this->household();

        if (null === $household) {
            throw new UnauthorizedHttpException('', 'Authentication required.');
        }

        return $household;
    }

    /**
     * The active profile: the PIN-authenticated profile itself, or the profile
     * selected with /profiles/{id}/switch. Null when an adult logged in with
     * e-mail but has not picked a profile yet.
     */
    public function profile(): ?Profile
    {
        $user = $this->securityUser();

        if ($user instanceof Profile) {
            return $user;
        }

        if (!$user instanceof User) {
            return null;
        }

        $profileId = $this->requests->getSession()->get('penderie.active_profile_id');

        if (!\is_int($profileId)) {
            return null;
        }

        $profile = $this->em->getRepository(Profile::class)->find($profileId);

        if (!$profile instanceof Profile || $profile->getHousehold()->getId() !== $user->getHousehold()->getId()) {
            return null;
        }

        return $profile;
    }

    /**
     * Returns an adult profile allowed to manage the household (create profiles,
     * switch freely): the active profile when it is an adult, otherwise the
     * logged-in user's own parent/partner profile.
     *
     * @throws AccessDeniedHttpException when the actor is a child
     */
    public function requireAdultProfile(): Profile
    {
        $user = $this->securityUser();

        $active = $this->profile();

        if ($active instanceof Profile && $active->isAdult()) {
            return $active;
        }

        if ($user instanceof User) {
            $own = $this->em->getRepository(Profile::class)->findOneBy(['user' => $user]);

            if ($own instanceof Profile && $own->isAdult()) {
                return $own;
            }
        }

        throw new AccessDeniedHttpException('Only an adult profile can perform this action.');
    }

    private function securityUser(): ?object
    {
        return $this->tokens->getToken()?->getUser();
    }
}
