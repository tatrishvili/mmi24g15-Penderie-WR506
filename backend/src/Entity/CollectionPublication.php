<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'collection_publication')]
class CollectionPublication
{
    #[ORM\Id]
    #[ORM\Column(length: 24)]
    public string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public SocialWorkspace $workspace;

    #[ORM\Column(length: 20)]
    public string $audience;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    public array $presentation;

    #[ORM\Column]
    public \DateTimeImmutable $updatedAt;

    /** @param array<string, mixed> $presentation */
    public function __construct(string $id, SocialWorkspace $workspace, string $audience, array $presentation)
    {
        $this->id = $id;
        $this->workspace = $workspace;
        $this->audience = $audience;
        $this->presentation = $presentation;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
