<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'social_workspace')]
#[ORM\UniqueConstraint(name: 'UNIQ_WORKSPACE_OWNER_HOUSEHOLD', columns: ['owner_id', 'household_id'])]
class SocialWorkspace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $owner;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Household $household;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    public array $profile = [];

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    public array $collections = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    public array $pins = [];

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    public int $version = 1;

    public function __construct(User $owner, Household $household)
    {
        $this->owner = $owner;
        $this->household = $household;
    }
}
