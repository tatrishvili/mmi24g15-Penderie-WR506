<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'guardian_link')]
#[ORM\UniqueConstraint(name: 'guardian_child_user', columns: ['child_id', 'parent_id'])]
class GuardianLink
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 12)]
    public string $state = 'active';

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Version, ORM\Column(type: 'integer')]
    public int $version = 1;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Profile::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Profile $child,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public User $parent,
        #[ORM\Column(length: 10)]
        public string $relationship,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false)]
        public User $invitedBy,
    ) {
    }
}
