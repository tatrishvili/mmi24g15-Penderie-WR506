<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Account-wide, explicitly approved identity; independent of private household profiles. */
#[ORM\Entity]
#[ORM\Table(name: 'community_identity')]
class CommunityIdentity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $owner;

    #[ORM\Column(length: 100)]
    public string $displayName;

    #[ORM\Column(type: 'text')]
    public string $photo;

    public function __construct(User $owner, string $displayName, string $photo)
    {
        $this->owner = $owner;
        $this->displayName = $displayName;
        $this->photo = $photo;
    }
}
