<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Credentials holder (e-mail + password) for adults (parent, partner).
 * Children have NO User row: they authenticate via their Profile + PIN (Mode C).
 * A User can belong to SEVERAL households (see HouseholdMember): the active
 * household lives in the session, not on the User.
 */
#[ORM\Entity(repositoryClass: \App\Repository\UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'UNIQ_APP_USER_USERNAME', columns: ['username'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $username;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $birthDate;

    #[ORM\Column]
    private string $password;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => true])]
    private bool $emailVerified = true;

    public function __construct(
        string $email,
        string $firstName,
        ?string $username = null,
        ?\DateTimeImmutable $birthDate = null,
    ) {
        $this->email = $email;
        $this->firstName = $firstName;
        $this->username = $username;
        $this->birthDate = $birthDate;
        $this->password = '';
        $this->createdAt = new \DateTimeImmutable();
    }

    public function setPassword(string $passwordHash): void
    {
        $this->password = $passwordHash;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): void
    {
        $this->emailVerified = $emailVerified;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function eraseCredentials(): void
    {
        // No temporary sensitive data stored on the user.
    }
}
