<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProfileRole;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A household member. Adults (parent/partner) own a User for credentials;
 * children currently use a restricted profile on an authenticated adult device.
 * A PIN is only a trusted-device unlock, never a remote sign-in credential.
 * Profiles own items independently of physical location.
 */
#[ORM\Entity]
#[ORM\Table(name: 'profile')]
class Profile implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Household::class, inversedBy: 'profiles')]
    #[ORM\JoinColumn(nullable: false)]
    private Household $household;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', enumType: ProfileRole::class)]
    private ProfileRole $role;

    /** Optional hashed PIN for trusted-device profile unlock. */
    #[ORM\Column(nullable: true)]
    private ?string $pinHash = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $avatarColor = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Household $household, string $name, ProfileRole $role, ?User $user = null)
    {
        $this->household = $household;
        $this->name = $name;
        $this->role = $role;
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRole(): ProfileRole
    {
        return $this->role;
    }

    public function getPinHash(): ?string
    {
        return $this->pinHash;
    }

    public function setPinHash(?string $pinHash): void
    {
        $this->pinHash = $pinHash;
    }

    public function getAvatarColor(): ?string
    {
        return $this->avatarColor;
    }

    public function setAvatarColor(?string $avatarColor): void
    {
        $this->avatarColor = $avatarColor;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isAdult(): bool
    {
        return $this->role->isAdult();
    }

    public function getUserIdentifier(): string
    {
        return 'profile:'.$this->id;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return [$this->role->securityRole()];
    }

    public function getPassword(): ?string
    {
        return $this->pinHash;
    }

    public function eraseCredentials(): void
    {
        // No temporary sensitive data stored on the profile.
    }
}
