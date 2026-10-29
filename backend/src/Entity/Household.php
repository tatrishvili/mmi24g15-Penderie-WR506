<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'household')]
class Household
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    /** Public slug used for standalone child login (Mode C): /{slug} → profiles → PIN. */
    #[ORM\Column(length: 180, unique: true)]
    private string $slug;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Profile> */
    #[ORM\OneToMany(targetEntity: Profile::class, mappedBy: 'household')]
    private Collection $profiles;

    /** @var Collection<int, Place> */
    #[ORM\OneToMany(targetEntity: Place::class, mappedBy: 'household')]
    private Collection $places;

    /** @var Collection<int, Room> */
    #[ORM\OneToMany(targetEntity: Room::class, mappedBy: 'household')]
    private Collection $rooms;

    public function __construct(string $name, string $slug)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->createdAt = new \DateTimeImmutable();
        $this->profiles = new ArrayCollection();
        $this->places = new ArrayCollection();
        $this->rooms = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Profile> */
    public function getProfiles(): Collection
    {
        return $this->profiles;
    }

    /** @return Collection<int, Place> */
    public function getPlaces(): Collection
    {
        return $this->places;
    }

    /** @return Collection<int, Room> */
    public function getRooms(): Collection
    {
        return $this->rooms;
    }
}
