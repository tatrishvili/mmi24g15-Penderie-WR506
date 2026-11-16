<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Append-only record of successful mutations and security events.
 * Never store private notes, photos, tokens or addresses in metadata.
 */
#[ORM\Entity]
#[ORM\Table(name: 'activity_log')]
#[ORM\Index(name: 'activity_household_created', columns: ['household_id', 'created_at'])]
#[ORM\Index(name: 'activity_actor_created', columns: ['actor_user_id', 'created_at'])]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Household::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Household $household = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actorUser = null;

    #[ORM\ManyToOne(targetEntity: Profile::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Profile $actorProfile = null;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $subjectType = null;

    #[ORM\Column(nullable: true)]
    private ?int $subjectId = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $action,
        ?Household $household = null,
        ?User $actorUser = null,
        ?Profile $actorProfile = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = [],
    ) {
        $this->action = $action;
        $this->household = $household;
        $this->actorUser = $actorUser;
        $this->actorProfile = $actorProfile;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->metadata = $metadata;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): ?Household
    {
        return $this->household;
    }

    public function getActorUser(): ?User
    {
        return $this->actorUser;
    }

    public function getActorProfile(): ?Profile
    {
        return $this->actorProfile;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): ?int
    {
        return $this->subjectId;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
