<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'room')]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Household::class, inversedBy: 'rooms')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Household $household;

    #[ORM\ManyToOne(targetEntity: Place::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Place $place;

    #[ORM\Column(length: 120)]
    private string $name;

    /** @var Collection<int, StorageLocation> */
    #[ORM\OneToMany(targetEntity: StorageLocation::class, mappedBy: 'room')]
    private Collection $storageLocations;

    public function __construct(Household $household, Place $place, string $name)
    {
        $this->household = $household;
        $this->place = $place;
        $this->name = $name;
        $this->storageLocations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getPlace(): Place
    {
        return $this->place;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, StorageLocation> */
    public function getStorageLocations(): Collection
    {
        return $this->storageLocations;
    }
}
