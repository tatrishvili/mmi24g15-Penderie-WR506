<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shared_item_report')]
#[ORM\UniqueConstraint(name: 'UNIQ_SHARED_REPORT_SHARE_REPORTER', columns: ['item_share_id', 'reporter_id'])]
#[ORM\Index(name: 'IDX_SHARED_REPORT_STATUS_CREATED', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'IDX_SHARED_REPORT_REPORTER', columns: ['reporter_id'])]
#[ORM\Index(name: 'IDX_SHARED_REPORT_REVIEWED_BY', columns: ['reviewed_by_id'])]
final class SharedItemReport
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_TAKEN_DOWN = 'taken_down';
    public const STATUS_RESTORED = 'restored';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ItemShare::class)]
    #[ORM\JoinColumn(name: 'item_share_id', nullable: false, onDelete: 'CASCADE')]
    public ItemShare $share;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public User $reporter;

    #[ORM\Column(length: 40)]
    public string $reason;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $details;

    #[ORM\Column(length: 20)]
    public string $status = self::STATUS_PENDING;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $reviewedBy = null;

    public function __construct(ItemShare $share, User $reporter, string $reason, ?string $details)
    {
        $this->share = $share;
        $this->reporter = $reporter;
        $this->reason = $reason;
        $this->details = $details;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function review(string $status, User $moderator): void
    {
        $this->status = $status;
        $this->reviewedAt = new \DateTimeImmutable();
        $this->reviewedBy = $moderator;
    }
}
