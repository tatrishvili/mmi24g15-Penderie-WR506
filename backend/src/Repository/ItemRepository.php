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

        if (null !== $actor) {
            $profile = $actor->profile();
            if (null !== $profile && !$profile->isAdult()) {
                $qb->andWhere('i.ownerProfile = :profile')->setParameter('profile', $profile);
            } else {
                $qb->join('i.ownerProfile', 'owner')
                    ->andWhere("i.visibility != 'personal' OR owner.user = :viewer OR owner.role = :childRole")
                    ->setParameter('viewer', $actor->requireUser())
                    ->setParameter('childRole', \App\Enum\ProfileRole::Child);
            }
        }

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

        $total = (int) (clone $qb)->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();

        /** @var list<Item> $items */
        $items = $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
