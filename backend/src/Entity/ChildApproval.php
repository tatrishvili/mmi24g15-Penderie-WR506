<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'child_approval')]
class ChildApproval
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Version, ORM\Column(type: 'integer')]
    public int $version = 1;
    #[ORM\Column(length: 15)]
    public string $status = 'pending';
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $decidedAt = null;
    #[ORM\ManyToOne(targetEntity: User::class)]
    public ?User $decidedBy = null;
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    /** @param array<string, mixed> $proposal */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Profile::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Profile $child,
        #[ORM\Column(length: 30)]
        public string $kind,
        #[ORM\Column(type: 'json')]
        public array $proposal,
        #[ORM\Column(length: 64)]
        public string $digest,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }
}
