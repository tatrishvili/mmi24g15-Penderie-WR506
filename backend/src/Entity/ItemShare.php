<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'item_share')]
class ItemShare
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
    public Item $item;

    #[ORM\Column(length: 64, unique: true)]
    public string $tokenHash;

    #[ORM\Column]
    public bool $revoked = false;

    #[ORM\Column]
    public bool $verifiedOnly = false;

    #[ORM\Column]
    public \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    public array $presentation = [];

    public function __construct(User $owner, Item $item, string $token)
    {
        $this->owner = $owner;
        $this->item = $item;
        $this->presentation = ['name' => $item->getName(), 'type' => $item->getType(), 'color' => $item->getColor(), 'photoId' => $item->getPhotos()->first() ? $item->getPhotos()->first()->getId() : null];
        $this->tokenHash = hash('sha256', $token);
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify('+7 days');
    }
}
