<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'item_loan')]
class Loan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Item $item;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $borrower;

    #[ORM\Column(length: 255)]
    public string $itemName;

    #[ORM\Column(length: 20)]
    public string $status = 'pending';

    #[ORM\Column(type: 'text')]
    public string $message;

    #[ORM\Column(type: 'date_immutable')]
    public \DateTimeImmutable $dueAt;

    #[ORM\Column]
    public \DateTimeImmutable $requestedAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $handedAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $returnedAt = null;

    public function __construct(Item $item, User $owner, User $borrower, \DateTimeImmutable $dueAt, string $message)
    {
        $this->item = $item;
        $this->itemName = $item->getName();
        $this->owner = $owner;
        $this->borrower = $borrower;
        $this->dueAt = $dueAt;
        $this->message = $message;
        $this->requestedAt = new \DateTimeImmutable();
    }
}
