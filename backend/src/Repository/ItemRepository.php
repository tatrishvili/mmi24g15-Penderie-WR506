<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Household;
use App\Entity\Item;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
final class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /**
     * The foyer item list: exact household scope, optional free-text search
     * (name, brand, colour) and optional status filter, paginated.
     *
     * @return array{items: list<Item>, total: int}
     */
    public function searchInHousehold(
        Household $household,
        string $query,
        string $status,
        int $page,
        int $perPage,
        ?\App\Service\CurrentActor $actor = null,
        ?int $roomId = null,
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->where('i.household = :household')
            ->setParameter('household', $household)
            ->orderBy('i.id', 'DESC');

        if (null !== $roomId) {
            $qb->andWhere('IDENTITY(i.room) = :roomId')->setParameter('roomId', $roomId);
        }

        if ('' !== $query) {
            $qb->andWhere('i.name LIKE :q OR i.brand LIKE :q OR i.color LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }

        if ('active' === $status) {
            $qb->andWhere("i.status != 'archive'");
        } elseif ('' !== $status) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }

        /** @var list<Item> $candidates */
        $candidates = $qb->getQuery()->getResult();
        $visible = array_values(array_filter($candidates, static fn (Item $item): bool => null === $actor
            || ($actor->canViewItem($item) && (null === $roomId || $actor->canManageItem($item)))));

        return ['items' => array_slice($visible, ($page - 1) * $perPage, $perPage), 'total' => \count($visible)];
    }
}
