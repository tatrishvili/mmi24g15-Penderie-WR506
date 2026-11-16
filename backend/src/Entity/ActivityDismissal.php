<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_dismissal')]
#[ORM\UniqueConstraint(name: 'uniq_activity_dismissal_actor_log', columns: ['actor_key', 'activity_log_id'])]
#[ORM\Index(name: 'IDX_ACTIVITY_DISMISSAL_LOG', columns: ['activity_log_id'])]
class ActivityDismissal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 50)]
    public string $actorKey;

    #[ORM\ManyToOne(targetEntity: ActivityLog::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ActivityLog $activityLog;

    public function __construct(string $actorKey, ActivityLog $activityLog)
    {
        $this->actorKey = $actorKey;
        $this->activityLog = $activityLog;
    }
}
