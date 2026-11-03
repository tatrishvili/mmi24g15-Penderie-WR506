<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'child_connection')]
class ChildConnection
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Version, ORM\Column(type: 'integer')]
    public int $version = 1;
    #[ORM\Column(length: 12)]
    public string $state = 'pending';
    #[ORM\Column]
    public bool $requesterApproved = false;
    #[ORM\Column]
    public bool $recipientApproved = false;
    #[ORM\Column]
    public bool $recipientAccepted = false;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?User $requesterApprovedBy = null;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?User $recipientApprovedBy = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Profile::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Profile $requester,
        #[ORM\ManyToOne(targetEntity: Profile::class)]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        public ?Profile $recipientChild = null,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        public ?User $recipientRelative = null,
    ) {
    }
}
