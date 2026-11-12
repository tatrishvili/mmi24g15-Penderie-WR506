<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'child_account')]
class ChildAccount implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    public string $username;

    #[ORM\Column]
    public string $passwordHash = '';

    #[ORM\Column]
    public bool $enabled = true;

    #[ORM\Column]
    public int $sessionVersion = 1;

    #[ORM\Column]
    public bool $mustChangePassword = true;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $initialPasswordExpiresAt = null;

    public function __construct(
        #[ORM\OneToOne(targetEntity: Profile::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Profile $profile,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return 'child:'.$this->username;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_CHILD'];
    }

    public function eraseCredentials(): void
    {
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self && $user->id === $this->id && $user->enabled
            && $user->sessionVersion === $this->sessionVersion
            && $user->passwordHash === $this->passwordHash;
    }
}
