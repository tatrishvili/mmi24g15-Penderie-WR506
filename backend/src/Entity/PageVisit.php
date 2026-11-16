<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Anonymous page-view beacon. Stores no IP address, user agent, cookie or
 * referrer. The visitor id is a random value kept in browser storage.
 */
#[ORM\Entity]
#[ORM\Table(name: 'page_visit')]
#[ORM\Index(name: 'visit_created', columns: ['created_at'])]
#[ORM\Index(name: 'visit_household_created', columns: ['household_id', 'created_at'])]
class PageVisit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $visitorId = null;

    #[ORM\ManyToOne(targetEntity: Household::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Household $household = null;

    #[ORM\Column(length: 40)]
    private string $page;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $locale = null;

    #[ORM\Column]
    private bool $authenticated = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $page,
        ?string $visitorId = null,
        ?Household $household = null,
        ?string $locale = null,
        bool $authenticated = false,
    ) {
        $this->page = $page;
        $this->visitorId = $visitorId;
        $this->household = $household;
        $this->locale = $locale;
        $this->authenticated = $authenticated;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVisitorId(): ?string
    {
        return $this->visitorId;
    }

    public function getHousehold(): ?Household
    {
        return $this->household;
    }

    public function getPage(): string
    {
        return $this->page;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
