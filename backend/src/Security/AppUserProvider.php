<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ChildAccount;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\FamilyAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Single user provider for the two security identities of the app:
 * - User (e-mail login, json_login)
 * - Profile (legacy token refresh only; CurrentActor rejects profile sign-in)
 *
 * Trusted-device PIN unlock keeps the adult User token. It does not authenticate
 * a Profile or grant permission to return from child mode to adult mode.
 *
 * @implements UserProviderInterface<UserInterface>
 */
final class AppUserProvider implements UserProviderInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if ($user instanceof ChildAccount) {
            $fresh = $this->em->getRepository(ChildAccount::class)->find($user->id);
            if (!$fresh instanceof ChildAccount || !(new FamilyAccess($this->em))->childEnabled($fresh)
                || ($fresh->mustChangePassword && null !== $fresh->initialPasswordExpiresAt && $fresh->initialPasswordExpiresAt < new \DateTimeImmutable())) {
                throw new UserNotFoundException('Child access is unavailable.');
            }

            return $fresh;
        }
        if ($user instanceof User) {
            $fresh = null !== $user->getId()
                ? $this->em->getRepository(User::class)->find($user->getId())
                : null;

            if (!$fresh instanceof User) {
                throw new UserNotFoundException('User account is gone.');
            }

            return $fresh;
        }

        if ($user instanceof Profile) {
            $fresh = null !== $user->getId()
                ? $this->em->getRepository(Profile::class)->find($user->getId())
                : null;

            if (!$fresh instanceof Profile) {
                throw new UserNotFoundException('Profile is gone.');
            }

            return $fresh;
        }

        throw new UnsupportedUserException(\sprintf('Unsupported user class "%s".', get_debug_type($user)));
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, ChildAccount::class, true) || is_a($class, User::class, true) || is_a($class, Profile::class, true);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (str_starts_with($identifier, 'profile:')) {
            $profile = $this->em->getRepository(Profile::class)->find((int) substr($identifier, \strlen('profile:')));

            if (!$profile instanceof Profile) {
                throw new UserNotFoundException(\sprintf('Profile "%s" not found.', $identifier));
            }

            return $profile;
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $identifier]);

        if (!$user instanceof User) {
            throw new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
        }

        return $user;
    }
}
