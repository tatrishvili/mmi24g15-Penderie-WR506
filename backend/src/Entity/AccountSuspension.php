<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'account_suspension')]
#[ORM\Index(name: 'IDX_ACCOUNT_SUSPENSION_ACTIVE', columns: ['user_id', 'restored_at'])]
#[ORM\Index(name: 'IDX_ACCOUNT_SUSPENSION_SUSPENDED_BY', columns: ['suspended_by_id'])]
#[ORM\Index(name: 'IDX_ACCOUNT_SUSPENSION_RESTORED_BY', columns: ['restored_by_id'])]
final class AccountSuspension
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public User $suspendedBy;

    #[ORM\Column(length: 40)]
    public string $reason;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $restoredAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $restoredBy = null;

    public function __construct(User $user, User $suspendedBy, string $reason)
    {
        $this->user = $user;
        $this->suspendedBy = $suspendedBy;
        $this->reason = $reason;
        $this->createdAt = new \DateTimeImmutable();
    }
}
