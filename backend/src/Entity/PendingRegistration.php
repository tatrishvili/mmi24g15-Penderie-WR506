<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pending_registration')]
class PendingRegistration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $username;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $birthDate;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSentAt;

    public function __construct(
        string $email,
        string $passwordHash,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
        ?string $username = null,
        ?\DateTimeImmutable $birthDate = null,
    ) {
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->username = $username;
        $this->birthDate = $birthDate;
        $this->createdAt = new \DateTimeImmutable();
        $this->lastSentAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getLastSentAt(): \DateTimeImmutable
    {
        return $this->lastSentAt;
    }

    public function refresh(
        string $passwordHash,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
        ?string $username = null,
        ?\DateTimeImmutable $birthDate = null,
    ): void {
        $this->passwordHash = $passwordHash;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->username = $username;
        $this->birthDate = $birthDate;
        $this->lastSentAt = new \DateTimeImmutable();
    }
}
