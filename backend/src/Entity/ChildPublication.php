<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'child_publication')]
class ChildPublication
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column]
    public bool $revoked = false;

    /** @param list<string> $recipients
     * @param array<string, mixed> $snapshot
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: ChildApproval::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public ChildApproval $approval,
        #[ORM\Column(type: 'json')]
        public array $recipients,
        #[ORM\Column(type: 'json')]
        public array $snapshot,
    ) {
    }
}
