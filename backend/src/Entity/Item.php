<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ItemCondition;
use App\Enum\ItemStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * THE core of Penderie: one owned object. Scoped to a Household (so every
 * request stays inside the active foyer) and owned by a Profile (Matrix v3:
 * a child only ever sees their own objects).
 */
#[ORM\Entity(repositoryClass: \App\Repository\ItemRepository::class)]
#[ORM\Table(name: 'item')]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Household::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Household $household;

    #[ORM\ManyToOne(targetEntity: Profile::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Profile $ownerProfile;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $category = null;

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Room $room = null;

    #[ORM\ManyToOne(targetEntity: StorageLocation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?StorageLocation $storageLocation = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $author = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $artist = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $publisher = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $size = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $material = null;

    #[ORM\Column(name: '`condition`', type: 'string', enumType: ItemCondition::class)]
    private ItemCondition $condition;

    #[ORM\Column(type: 'string', enumType: ItemStatus::class)]
    private ItemStatus $status;

    #[ORM\Column(length: 32, unique: true)]
    private string $qrToken;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $memory = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ItemPhoto> */
    #[ORM\OneToMany(targetEntity: ItemPhoto::class, mappedBy: 'item', cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $photos;

    public function __construct(Household $household, Profile $ownerProfile, string $name)
    {
        $this->household = $household;
        $this->ownerProfile = $ownerProfile;
        $this->name = $name;
        $this->condition = ItemCondition::Bon;
        $this->status = ItemStatus::Disponible;
        $this->qrToken = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->photos = new ArrayCollection();
    }

    #[ORM\Column(length: 20, options: ['default' => 'private'])]
    private string $visibility = 'private';

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): void
    {
        $this->visibility = $visibility;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getOwnerProfile(): Profile
    {
        return $this->ownerProfile;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): void
    {
        $this->category = $category;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): void
    {
        $this->room = $room;
    }

    public function getStorageLocation(): ?StorageLocation
    {
        return $this->storageLocation;
    }

    public function setStorageLocation(?StorageLocation $storageLocation): void
    {
        $this->storageLocation = $storageLocation;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): void
    {
        $this->author = $author;
    }

    public function getArtist(): ?string
    {
        return $this->artist;
    }

    public function setArtist(?string $artist): void
    {
        $this->artist = $artist;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setPublisher(?string $publisher): void
    {
        $this->publisher = $publisher;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): void
    {
        $this->brand = $brand;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(?string $size): void
    {
        $this->size = $size;
    }

    public function getMaterial(): ?string
    {
        return $this->material;
    }

    public function setMaterial(?string $material): void
    {
        $this->material = $material;
    }

    public function getCondition(): ItemCondition
    {
        return $this->condition;
    }

    public function setCondition(ItemCondition $condition): void
    {
        $this->condition = $condition;
    }

    public function getStatus(): ItemStatus
    {
        return $this->status;
    }

    public function setStatus(ItemStatus $status): void
    {
        $this->status = $status;
    }

    public function getQrToken(): string
    {
        return $this->qrToken;
    }

    public function getMemory(): ?string
    {
        return $this->memory;
    }

    public function setMemory(?string $memory): void
    {
        $this->memory = $memory;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @return Collection<int, ItemPhoto> */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }
}
