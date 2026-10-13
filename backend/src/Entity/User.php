<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Credentials holder (e-mail + password) for adults (parent, partner).
 * Children have NO User row: they authenticate via their Profile + PIN (Mode C).
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column]
    private string $password;

    #[ORM\ManyToOne(targetEntity: Household::class, inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private Household $household;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $email, Household $household)
    {
        $this->email = $email;
        $this->password = '';
        $this->household = $household;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function setPassword(string $passwordHash): void
    {
        $this->password = $passwordHash;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
