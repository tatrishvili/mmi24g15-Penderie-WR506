<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One photo of an item. The file itself lives on disk under
 * var/uploads/items/ — never in the database, only the relative path.
 */
#[ORM\Entity]
#[ORM\Table(name: 'item_photo')]
class ItemPhoto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Item::class, inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Item $item;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column]
    private bool $isPrimary = false;

    #[ORM\Column]
    private int $sortOrder = 0;

    public function __construct(Item $item, string $path, bool $isPrimary, int $sortOrder)
    {
        $this->item = $item;
        $this->path = $path;
        $this->isPrimary = $isPrimary;
        $this->sortOrder = $sortOrder;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setPrimary(bool $isPrimary): void
    {
        $this->isPrimary = $isPrimary;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
