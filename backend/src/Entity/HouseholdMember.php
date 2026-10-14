<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Membership of a User in a Household. A User can belong to several
 * households (several foyers); the ACTIVE one lives in the session.
 */
#[ORM\Entity]
#[ORM\Table(name: 'household_member')]
#[ORM\UniqueConstraint(name: 'uniq_member', columns: ['user_id', 'household_id'])]
class HouseholdMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Household::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Household $household;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, Household $household)
    {
        $this->user = $user;
        $this->household = $household;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
