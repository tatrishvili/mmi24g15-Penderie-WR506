<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'child_collection')]
class ChildCollection
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Version, ORM\Column(type: 'integer')]
    public int $version = 1;
    /** @var list<int> */
    #[ORM\Column(type: 'json')]
    public array $itemIds = [];

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Profile::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Profile $owner,
        #[ORM\Column(length: 100)]
        public string $name,
    ) {
    }
}
