<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A physical place of a household (house, flat, ...). A household owns
 * several places natively, with no extra configuration (MLD v2).
 */
#[ORM\Entity]
#[ORM\Table(name: 'place')]
class Place
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Household::class, inversedBy: 'places')]
    #[ORM\JoinColumn(nullable: false)]
    private Household $household;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Household $household, string $name, bool $isDefault = false)
    {
        $this->household = $household;
        $this->name = $name;
        $this->isDefault = $isDefault;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
