<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\ChildAccount;
use App\Entity\Household;
use App\Entity\HouseholdMember;
use App\Entity\Item;
use App\Entity\ItemShare;
use App\Entity\Loan;
use App\Entity\PageVisit;
use App\Entity\PerformanceMetric;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Aggregates the read-only KPIs shown to household admins and to the
 * CLI-promoted super admin. Household users only ever see their own foyer.
 */
final class AdminMetrics
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CurrentActor $actor,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $user, string $period = '30d'): array
    {
        $global = $user->isSuperAdmin();
        $household = $global ? null : $this->household($user);
        $period = $this->normalizePeriod($period);
        $since = $this->periodStart($period);

        if (!$global && null === $household) {
            return $this->emptyDashboard($period);
        }

        return [
            'scope' => $global ? 'global' : 'household',
            'householdName' => $household?->getName(),
            'period' => $period,
            'trackingStartedAt' => $this->trackingStartedAt(),
            'activityStartedAt' => $this->activityStartedAt($household),
            'households' => $global ? $this->scalar('SELECT COUNT(h.id) FROM '.Household::class.' h') : 1,
            'members' => $this->countMembers($household),
            'users' => $this->countUsers($household),
            'items' => $this->countItems($household),
            'activeLoans' => $this->countActiveLoans($household),
            'visits30d' => $this->countVisits($household, $since),
            'uniqueVisitors30d' => $this->countUniqueVisitors($household, $since),
            'signups30d' => $this->countSignups($household, $since),
            'activeUsers30d' => $this->countActiveUsers($household, $since),
            'unverified' => $this->countUnverified($household),
            'scans' => $this->countScans($household, $since),
            'shares' => $this->countActiveShares($household),
            'childAccounts' => $this->countChildAccounts($household),
            'performance' => $this->performanceSummary($since),
            'recentActivity' => $this->recentActivity($household),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $metric, string $period = '30d'): ?array
    {
        $period = $this->normalizePeriod($period);
        $since = $this->periodStart($period);
        $household = $user->isSuperAdmin() ? null : $this->household($user);

        if (!$user->isSuperAdmin() && null === $household) {
            return $this->detailTable('admin.detail.title.'.$metric, [], []);
        }

        return match ($metric) {
            'households' => $this->householdDetails($household),
            'users' => $this->adultAccountDetails($household, $since),
            'members' => $this->membershipDetails($household),
            'items' => $this->itemDetails($household, $since),
            'activeLoans' => $this->loanDetails($household),
            'visits30d', 'uniqueVisitors30d' => $this->visitDetails($household, $since),
            'signups30d' => $this->adultAccountDetails($household, $since, 'signup'),
            'activeUsers30d' => $this->adultAccountDetails($household, $since, 'active'),
            'unverified' => $this->adultAccountDetails($household, null, 'unverified'),
            'scans' => $this->scanDetails($household, $since),
            'shares' => $this->shareDetails($household),
            'childAccounts' => $this->childAccountDetails($household),
            'performance' => $this->performanceDetails($since),
            default => null,
        };
    }

    public function normalizePeriod(mixed $period): string
    {
        return \is_string($period) && \in_array($period, ['7d', '30d', '90d', 'all'], true) ? $period : '30d';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboard(string $period): array
    {
        return [
            'scope' => 'household',
            'householdName' => null,
            'period' => $period,
            'trackingStartedAt' => null,
            'activityStartedAt' => null,
            'households' => 0,
            'members' => 0,
            'users' => 0,
            'items' => 0,
            'activeLoans' => 0,
            'visits30d' => 0,
            'uniqueVisitors30d' => 0,
            'signups30d' => 0,
            'activeUsers30d' => 0,
            'unverified' => 0,
            'scans' => 0,
            'shares' => 0,
            'childAccounts' => 0,
            'performance' => ['requests' => 0, 'errors' => 0, 'averageMs' => 0, 'maxMs' => 0],
            'recentActivity' => [],
        ];
    }

    /**
     * @return list<ActivityLog>
     */
    public function recentActivity(
        ?Household $household,
        int $limit = 25,
        int $offset = 0,
        ?string $action = null,
        ?int $actorId = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(ActivityLog::class, 'a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($household instanceof Household) {
            $qb->andWhere('a.household = :household')->setParameter('household', $household);
        }
        if (null !== $action && '' !== $action) {
            $qb->andWhere('a.action = :action')->setParameter('action', $action);
        }
        if (null !== $actorId) {
            $qb->andWhere('IDENTITY(a.actorUser) = :actor')->setParameter('actor', $actorId);
        }
        if (null !== $from) {
            $qb->andWhere('a.createdAt >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $qb->andWhere('a.createdAt < :to')->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    public function countActivity(
        ?Household $household,
        ?string $action = null,
        ?int $actorId = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): int {
        $qb = $this->em->createQueryBuilder()->select('COUNT(a.id)')->from(ActivityLog::class, 'a');
        if ($household instanceof Household) {
            $qb->andWhere('a.household = :household')->setParameter('household', $household);
        }
        if (null !== $action && '' !== $action) {
            $qb->andWhere('a.action = :action')->setParameter('action', $action);
        }
        if (null !== $actorId) {
            $qb->andWhere('IDENTITY(a.actorUser) = :actor')->setParameter('actor', $actorId);
        }
        if (null !== $from) {
            $qb->andWhere('a.createdAt >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $qb->andWhere('a.createdAt < :to')->setParameter('to', $to);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<string>
     */
    public function activityActions(?Household $household): array
    {
        $qb = $this->em->createQueryBuilder()->select('DISTINCT a.action')->from(ActivityLog::class, 'a')->orderBy('a.action', 'ASC');
        if ($household instanceof Household) {
            $qb->where('a.household = :household')->setParameter('household', $household);
        }

        $rows = $qb->getQuery()->getScalarResult();

        return array_map(static fn (array $row): string => (string) reset($row), $rows);
    }

    /**
     * @return list<User>
     */
    public function activityActors(?Household $household): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('DISTINCT u')
            ->from(User::class, 'u')
            ->join(ActivityLog::class, 'a', 'WITH', 'a.actorUser = u')
            ->orderBy('u.email', 'ASC');
        if ($household instanceof Household) {
            $qb->andWhere('a.household = :household')->setParameter('household', $household);
        }

        /** @var list<User> $actors */
        $actors = $qb->getQuery()->getResult();

        return $actors;
    }

    public function scopeHousehold(User $user): ?Household
    {
        return $this->household($user);
    }

    public function activityStartedAt(?Household $household): ?string
    {
        $qb = $this->em->createQueryBuilder()->select('MIN(a.createdAt)')->from(ActivityLog::class, 'a');
        if ($household instanceof Household) {
            $qb->where('a.household = :household')->setParameter('household', $household);
        }
        $date = $this->dateTime($qb->getQuery()->getSingleScalarResult());

        return $date?->format(DATE_ATOM);
    }

    private function household(User $user): ?Household
    {
        try {
            $active = $this->actor->household();
            if ($active instanceof Household) {
                return $active;
            }
        } catch (\Throwable) {
            // No active foyer: fall back to the first membership.
        }

        $member = $this->em->getRepository(HouseholdMember::class)->findOneBy(['user' => $user], ['id' => 'ASC']);

        return $member?->getHousehold();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function scalar(string $dql, array $parameters = []): int
    {
        return (int) $this->em->createQuery($dql)->setParameters($parameters)->getSingleScalarResult();
    }

    private function countMembers(?Household $household): int
    {
        return null === $household
            ? $this->scalar('SELECT COUNT(m.id) FROM '.HouseholdMember::class.' m')
            : $this->scalar('SELECT COUNT(m.id) FROM '.HouseholdMember::class.' m WHERE m.household = :household', ['household' => $household]);
    }

    private function countUsers(?Household $household): int
    {
        return null === $household
            ? $this->scalar('SELECT COUNT(u.id) FROM '.User::class.' u')
            : $this->scalar('SELECT COUNT(DISTINCT IDENTITY(m.user)) FROM '.HouseholdMember::class.' m WHERE m.household = :household', ['household' => $household]);
    }

    private function countItems(?Household $household): int
    {
        return null === $household
            ? $this->scalar('SELECT COUNT(i.id) FROM App\\Entity\\Item i')
            : $this->scalar('SELECT COUNT(i.id) FROM App\\Entity\\Item i WHERE i.household = :household', ['household' => $household]);
    }

    private function countActiveLoans(?Household $household): int
    {
        if (null === $household) {
            return $this->scalar("SELECT COUNT(l.id) FROM App\\Entity\\Loan l WHERE l.status IN ('accepted', 'active')");
        }

        return $this->scalar("SELECT COUNT(l.id) FROM App\\Entity\\Loan l JOIN l.item i WHERE i.household = :household AND l.status IN ('accepted', 'active')", ['household' => $household]);
    }

    private function countVisits(?Household $household, ?\DateTimeImmutable $since): int
    {
        $dql = 'SELECT COUNT(v.id) FROM '.PageVisit::class.' v WHERE 1 = 1';
        $parameters = [];
        if (null !== $since) {
            $dql .= ' AND v.createdAt >= :since';
            $parameters['since'] = $since;
        }

        if ($household instanceof Household) {
            $dql .= ' AND v.household = :household';
            $parameters['household'] = $household;
        }

        return $this->scalar($dql, $parameters);
    }

    private function countUniqueVisitors(?Household $household, ?\DateTimeImmutable $since): int
    {
        $dql = 'SELECT COUNT(DISTINCT v.visitorId) FROM '.PageVisit::class.' v WHERE v.visitorId IS NOT NULL';
        $parameters = [];
        if (null !== $since) {
            $dql .= ' AND v.createdAt >= :since';
            $parameters['since'] = $since;
        }

        if ($household instanceof Household) {
            $dql .= ' AND v.household = :household';
            $parameters['household'] = $household;
        }

        return $this->scalar($dql, $parameters);
    }

    private function countSignups(?Household $household, ?\DateTimeImmutable $since): int
    {
        $dql = null === $household
            ? 'SELECT COUNT(u.id) FROM '.User::class.' u WHERE 1 = 1'
            : 'SELECT COUNT(DISTINCT IDENTITY(m.user)) FROM '.HouseholdMember::class.' m JOIN m.user u WHERE m.household = :household';
        $parameters = null === $household ? [] : ['household' => $household];
        if (null !== $since) {
            $dql .= ' AND u.createdAt >= :since';
            $parameters['since'] = $since;
        }

        return $this->scalar($dql, $parameters);
    }

    private function countActiveUsers(?Household $household, ?\DateTimeImmutable $since): int
    {
        $dql = null === $household
            ? 'SELECT COUNT(u.id) FROM '.User::class.' u WHERE u.lastLoginAt IS NOT NULL'
            : 'SELECT COUNT(DISTINCT IDENTITY(m.user)) FROM '.HouseholdMember::class.' m JOIN m.user u WHERE m.household = :household AND u.lastLoginAt IS NOT NULL';
        $parameters = null === $household ? [] : ['household' => $household];
        if (null !== $since) {
            $dql .= ' AND u.lastLoginAt >= :since';
            $parameters['since'] = $since;
        }

        return $this->scalar($dql, $parameters);
    }

    private function countUnverified(?Household $household): int
    {
        return null === $household
            ? $this->scalar('SELECT COUNT(u.id) FROM '.User::class.' u WHERE u.emailVerified = false')
            : $this->scalar('SELECT COUNT(DISTINCT IDENTITY(m.user)) FROM '.HouseholdMember::class.' m JOIN m.user u WHERE m.household = :household AND u.emailVerified = false', ['household' => $household]);
    }

    private function periodStart(string $period): ?\DateTimeImmutable
    {
        if ('all' === $period) {
            return null;
        }

        $days = (int) substr($period, 0, -1);

        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify(sprintf('-%d days', $days));
    }

    private function trackingStartedAt(): ?string
    {
        $value = $this->em->getConnection()->fetchOne('SELECT MIN(created_at) FROM page_visit');

        return $this->dateTime($value)?->format(DATE_ATOM);
    }

    private function countScans(?Household $household, ?\DateTimeImmutable $since): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(ActivityLog::class, 'a')
            ->where('a.action = :action')
            ->setParameter('action', 'scan.created');
        if ($household instanceof Household) {
            $qb->andWhere('a.household = :household')->setParameter('household', $household);
        }
        if (null !== $since) {
            $qb->andWhere('a.createdAt >= :since')->setParameter('since', $since);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function countActiveShares(?Household $household): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(ItemShare::class, 's')
            ->where('s.revoked = false')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        if ($household instanceof Household) {
            $qb->join('s.item', 'i')->andWhere('i.household = :household')->setParameter('household', $household);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function countChildAccounts(?Household $household): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(ChildAccount::class, 'c');
        if ($household instanceof Household) {
            $qb->join('c.profile', 'p')->andWhere('p.household = :household')->setParameter('household', $household);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array{requests: int, errors: int, averageMs: int, maxMs: int}
     */
    private function performanceSummary(?\DateTimeImmutable $since): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('SUM(m.requestCount) AS requests', 'SUM(m.errorCount) AS errors', 'SUM(m.totalDurationMs) AS totalMs', 'MAX(m.maxDurationMs) AS maxMs')
            ->from(PerformanceMetric::class, 'm');
        if (null !== $since) {
            $qb->where('m.bucketDate >= :since')->setParameter('since', $since->setTime(0, 0));
        }
        $result = $qb->getQuery()->getSingleResult();
        $requests = (int) ($result['requests'] ?? 0);

        return [
            'requests' => $requests,
            'errors' => (int) ($result['errors'] ?? 0),
            'averageMs' => $requests > 0 ? (int) round((int) ($result['totalMs'] ?? 0) / $requests) : 0,
            'maxMs' => (int) ($result['maxMs'] ?? 0),
        ];
    }

    /**
     * @param list<array{key: string, label: string}> $columns
     * @param list<array<string, int|string>> $rows
     * @param list<array{title: string, points: list<array{label: string, value: int}>}> $charts
     *
     * @return array<string, mixed>
     */
    private function detailTable(string $titleKey, array $columns, array $rows, array $charts = []): array
    {
        return [
            'title' => $this->translator->trans($titleKey),
            'columns' => array_map(fn (array $column): array => [
                'key' => $column['key'],
                'label' => $this->translator->trans($column['label']),
            ], $columns),
            'rows' => $rows,
            'charts' => array_map(fn (array $chart): array => [
                'title' => $this->translator->trans($chart['title']),
                'points' => $chart['points'],
            ], $charts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function householdDetails(?Household $household): array
    {
        $households = null === $household
            ? $this->em->getRepository(Household::class)->findBy([], ['id' => 'ASC'])
            : [$household];
        $rows = [];
        foreach ($households as $entry) {
            $rows[] = [
                'name' => $entry->getName(),
                'members' => $this->scalar('SELECT COUNT(m.id) FROM '.HouseholdMember::class.' m WHERE m.household = :household', ['household' => $entry]),
                'items' => $this->scalar('SELECT COUNT(i.id) FROM '.Item::class.' i WHERE i.household = :household', ['household' => $entry]),
                'createdAt' => $entry->getCreatedAt()->format(DATE_ATOM),
            ];
        }

        return $this->detailTable('admin.detail.title.households', [
            ['key' => 'name', 'label' => 'admin.detail.column.name'],
            ['key' => 'members', 'label' => 'admin.detail.column.members'],
            ['key' => 'items', 'label' => 'admin.detail.column.items'],
            ['key' => 'createdAt', 'label' => 'admin.detail.column.created'],
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function adultAccountDetails(?Household $household, ?\DateTimeImmutable $since, string $filter = 'all'): array
    {
        $qb = $this->em->createQueryBuilder()->select('u')->from(User::class, 'u')->orderBy('u.createdAt', 'DESC');
        if (null !== $household) {
            $qb->join(HouseholdMember::class, 'm', 'WITH', 'm.user = u')->andWhere('m.household = :household')->setParameter('household', $household);
        }
        if ('signup' === $filter && null !== $since) {
            $qb->andWhere('u.createdAt >= :since')->setParameter('since', $since);
        } elseif ('active' === $filter) {
            $qb->andWhere('u.lastLoginAt IS NOT NULL');
            if (null !== $since) {
                $qb->andWhere('u.lastLoginAt >= :since')->setParameter('since', $since);
            }
        } elseif ('unverified' === $filter) {
            $qb->andWhere('u.emailVerified = false');
        }

        $rows = [];
        foreach ($qb->getQuery()->getResult() as $account) {
            \assert($account instanceof User);
            $rows[] = [
                'name' => $account->getFirstName(),
                'email' => $account->getEmail(),
                'role' => $this->translator->trans($account->isSuperAdmin() ? 'admin.role.super' : 'admin.role.user'),
                'verified' => $this->translator->trans($account->isEmailVerified() ? 'admin.yes' : 'admin.no'),
                'enabled' => $this->translator->trans($account->isEnabled() ? 'admin.yes' : 'admin.no'),
                'lastLogin' => $account->getLastLoginAt()?->format(DATE_ATOM) ?? '',
                'createdAt' => $account->getCreatedAt()->format(DATE_ATOM),
            ];
        }

        return $this->detailTable('admin.detail.title.accounts', [
            ['key' => 'name', 'label' => 'admin.detail.column.name'],
            ['key' => 'email', 'label' => 'admin.detail.column.email'],
            ['key' => 'role', 'label' => 'admin.detail.column.role'],
            ['key' => 'verified', 'label' => 'admin.detail.column.verified'],
            ['key' => 'enabled', 'label' => 'admin.detail.column.enabled'],
            ['key' => 'lastLogin', 'label' => 'admin.detail.column.lastLogin'],
            ['key' => 'createdAt', 'label' => 'admin.detail.column.created'],
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipDetails(?Household $household): array
    {
        $qb = $this->em->createQueryBuilder()->select('m')->from(HouseholdMember::class, 'm')->join('m.user', 'u')->join('m.household', 'h')->orderBy('h.name', 'ASC');
        if ($household instanceof Household) {
            $qb->andWhere('m.household = :household')->setParameter('household', $household);
        }
        $rows = [];
        foreach ($qb->getQuery()->getResult() as $member) {
            \assert($member instanceof HouseholdMember);
            $rows[] = [
                'household' => $member->getHousehold()->getName(),
                'account' => $member->getUser()->getEmail(),
                'joinedAt' => $member->getCreatedAt()->format(DATE_ATOM),
            ];
        }

        return $this->detailTable('admin.detail.title.memberships', [
            ['key' => 'household', 'label' => 'admin.detail.column.household'],
            ['key' => 'account', 'label' => 'admin.detail.column.account'],
            ['key' => 'joinedAt', 'label' => 'admin.detail.column.joinedAt'],
        ], $rows);
    }

    /**
     * Item drill-down is intentionally aggregate-only; it never includes names,
     * photos, notes, locations or other private item content.
     *
     * @return array<string, mixed>
     */
    private function itemDetails(?Household $household, ?\DateTimeImmutable $since): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('i.status AS status', 'COUNT(i.id) AS total')
            ->from(Item::class, 'i')
            ->groupBy('i.status')
            ->orderBy('i.status', 'ASC');
        if ($household instanceof Household) {
            $qb->where('i.household = :household')->setParameter('household', $household);
        }
        $rows = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $status = $row['status'] instanceof \BackedEnum ? $row['status']->value : (string) $row['status'];
            $rows[] = [
                'status' => $this->translator->trans('admin.itemStatus.'.$status),
                'count' => (int) $row['total'],
            ];
        }

        $created = $this->em->createQueryBuilder()->select('i.createdAt AS createdAt')->from(Item::class, 'i');
        if ($household instanceof Household) {
            $created->where('i.household = :household')->setParameter('household', $household);
        }
        if (null !== $since) {
            $created->andWhere('i.createdAt >= :since')->setParameter('since', $since);
        }
        $hourCounts = array_fill(0, 24, 0);
        $weekdayCounts = array_fill(1, 7, 0);
        foreach ($created->getQuery()->getArrayResult() as $row) {
            $date = $this->dateTime($row['createdAt'] ?? null);
            if (null !== $date) {
                $local = $date->setTimezone(new \DateTimeZone('Europe/Paris'));
                ++$hourCounts[(int) $local->format('G')];
                ++$weekdayCounts[(int) $local->format('N')];
            }
        }

        $weekdayLabels = ['admin.weekday.monday', 'admin.weekday.tuesday', 'admin.weekday.wednesday', 'admin.weekday.thursday', 'admin.weekday.friday', 'admin.weekday.saturday', 'admin.weekday.sunday'];
        $hourPoints = [];
        foreach ($hourCounts as $hour => $count) {
            $hourPoints[] = ['label' => sprintf('%02d:00', $hour), 'value' => $count];
        }
        $weekdayPoints = [];
        foreach ($weekdayLabels as $index => $key) {
            $weekdayPoints[] = ['label' => $this->translator->trans($key), 'value' => $weekdayCounts[$index + 1]];
        }

        return $this->detailTable('admin.detail.title.items', [
            ['key' => 'status', 'label' => 'admin.detail.column.status'],
            ['key' => 'count', 'label' => 'admin.detail.column.count'],
        ], $rows, [
            ['title' => 'admin.detail.chart.itemsByHour', 'points' => $hourPoints],
            ['title' => 'admin.detail.chart.itemsByWeekday', 'points' => $weekdayPoints],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loanDetails(?Household $household): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('l.status AS status', 'COUNT(l.id) AS total')
            ->from(Loan::class, 'l')
            ->where('l.status IN (:statuses)')
            ->setParameter('statuses', ['accepted', 'active'])
            ->groupBy('l.status')
            ->orderBy('l.status', 'ASC');
        if ($household instanceof Household) {
            $qb->join('l.item', 'i')->andWhere('i.household = :household')->setParameter('household', $household);
        }
        $rows = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $rows[] = ['status' => $this->translator->trans('admin.loanStatus.'.$row['status']), 'count' => (int) $row['total']];
        }

        return $this->detailTable('admin.detail.title.loans', [
            ['key' => 'status', 'label' => 'admin.detail.column.status'],
            ['key' => 'count', 'label' => 'admin.detail.column.count'],
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function visitDetails(?Household $household, ?\DateTimeImmutable $since): array
    {
        $qb = $this->em->createQueryBuilder()->select('v.page AS page', 'v.visitorId AS visitorId', 'v.createdAt AS createdAt')->from(PageVisit::class, 'v');
        if ($household instanceof Household) {
            $qb->where('v.household = :household')->setParameter('household', $household);
        }
        if (null !== $since) {
            $qb->andWhere('v.createdAt >= :since')->setParameter('since', $since);
        }
        $pageStats = [];
        $dailyVisits = [];
        $dailyVisitors = [];
        $visitors = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $page = (string) $row['page'];
            $pageStats[$page] ??= ['views' => 0, 'visitors' => []];
            ++$pageStats[$page]['views'];
            $visitor = $row['visitorId'];
            if (\is_string($visitor) && '' !== $visitor) {
                $visitors[$visitor] = true;
                $pageStats[$page]['visitors'][$visitor] = true;
            }
            $date = $this->dateTime($row['createdAt'] ?? null);
            if (null !== $date) {
                $day = $date->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d');
                $dailyVisits[$day] = ($dailyVisits[$day] ?? 0) + 1;
                if (\is_string($visitor) && '' !== $visitor) {
                    $dailyVisitors[$day][$visitor] = true;
                }
            }
        }
        ksort($pageStats);
        ksort($dailyVisits);
        $rows = [];
        foreach ($pageStats as $page => $stats) {
            $rows[] = ['page' => $this->translator->trans('admin.page.'.$page), 'views' => $stats['views'], 'visitors' => \count($stats['visitors'])];
        }
        $dailyPoints = [];
        foreach ($dailyVisits as $day => $count) {
            $dailyPoints[] = ['label' => $day, 'value' => $count];
        }

        return $this->detailTable('admin.detail.title.visits', [
            ['key' => 'page', 'label' => 'admin.detail.column.page'],
            ['key' => 'views', 'label' => 'admin.detail.column.visits'],
            ['key' => 'visitors', 'label' => 'admin.detail.column.uniqueVisitors'],
        ], $rows, [
            ['title' => 'admin.detail.chart.visitsByDay', 'points' => $dailyPoints],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function scanDetails(?Household $household, ?\DateTimeImmutable $since): array
    {
        $qb = $this->em->createQueryBuilder()->select('a.createdAt AS createdAt')->from(ActivityLog::class, 'a')->where('a.action = :action')->setParameter('action', 'scan.created');
        if ($household instanceof Household) {
            $qb->andWhere('a.household = :household')->setParameter('household', $household);
        }
        if (null !== $since) {
            $qb->andWhere('a.createdAt >= :since')->setParameter('since', $since);
        }
        $dailyCounts = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $date = $this->dateTime($row['createdAt'] ?? null);
            if (null !== $date) {
                $day = $date->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d');
                $dailyCounts[$day] = ($dailyCounts[$day] ?? 0) + 1;
            }
        }
        ksort($dailyCounts);
        $points = [];
        foreach ($dailyCounts as $day => $count) {
            $points[] = ['label' => $day, 'value' => $count];
        }

        return $this->detailTable('admin.detail.title.scans', [], [], [
            ['title' => 'admin.detail.chart.scansByDay', 'points' => $points],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shareDetails(?Household $household): array
    {
        $qb = $this->em->createQueryBuilder()->select('s.revoked AS revoked', 's.expiresAt AS expiresAt', 'COUNT(s.id) AS total')->from(ItemShare::class, 's')->groupBy('s.revoked', 's.expiresAt');
        if ($household instanceof Household) {
            $qb->join('s.item', 'i')->andWhere('i.household = :household')->setParameter('household', $household);
        }
        $counts = ['active' => 0, 'revoked' => 0, 'expired' => 0];
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $expiresAt = $this->dateTime($row['expiresAt'] ?? null);
            $expired = null !== $expiresAt && $expiresAt <= $now;
            $key = true === $row['revoked'] ? 'revoked' : ($expired ? 'expired' : 'active');
            $counts[$key] += (int) $row['total'];
        }
        $rows = [
            ['status' => $this->translator->trans('admin.shareStatus.active'), 'count' => $counts['active']],
            ['status' => $this->translator->trans('admin.shareStatus.revoked'), 'count' => $counts['revoked']],
            ['status' => $this->translator->trans('admin.shareStatus.expired'), 'count' => $counts['expired']],
        ];

        return $this->detailTable('admin.detail.title.shares', [
            ['key' => 'status', 'label' => 'admin.detail.column.status'],
            ['key' => 'count', 'label' => 'admin.detail.column.count'],
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function childAccountDetails(?Household $household): array
    {
        $qb = $this->em->createQueryBuilder()->select('c')->from(ChildAccount::class, 'c')->join('c.profile', 'p')->orderBy('p.name', 'ASC');
        if ($household instanceof Household) {
            $qb->andWhere('p.household = :household')->setParameter('household', $household);
        }
        $rows = [];
        foreach ($qb->getQuery()->getResult() as $account) {
            \assert($account instanceof ChildAccount);
            $rows[] = [
                'profile' => $account->profile->getName(),
                'username' => $account->username,
                'status' => $this->translator->trans($account->enabled ? 'admin.yes' : 'admin.no'),
            ];
        }

        return $this->detailTable('admin.detail.title.childAccounts', [
            ['key' => 'profile', 'label' => 'admin.detail.column.profile'],
            ['key' => 'username', 'label' => 'admin.detail.column.username'],
            ['key' => 'status', 'label' => 'admin.detail.column.enabled'],
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function performanceDetails(?\DateTimeImmutable $since): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('m.routeName AS route', 'm.method AS method', 'm.statusCode AS status', 'SUM(m.requestCount) AS requests', 'SUM(m.errorCount) AS errors', 'SUM(m.totalDurationMs) AS totalMs', 'MAX(m.maxDurationMs) AS maxMs')
            ->from(PerformanceMetric::class, 'm')
            ->groupBy('m.routeName', 'm.method', 'm.statusCode')
            ->orderBy('m.routeName', 'ASC');
        if (null !== $since) {
            $qb->where('m.bucketDate >= :since')->setParameter('since', $since->setTime(0, 0));
        }
        $rows = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $requests = (int) $row['requests'];
            $rows[] = [
                'route' => (string) $row['route'],
                'method' => (string) $row['method'],
                'status' => (string) $row['status'],
                'requests' => $requests,
                'errors' => (int) $row['errors'],
                'averageMs' => $requests > 0 ? (int) round((int) $row['totalMs'] / $requests) : 0,
                'maxMs' => (int) $row['maxMs'],
            ];
        }

        return $this->detailTable('admin.detail.title.performance', [
            ['key' => 'route', 'label' => 'admin.detail.column.route'],
            ['key' => 'method', 'label' => 'admin.detail.column.method'],
            ['key' => 'status', 'label' => 'admin.detail.column.statusCode'],
            ['key' => 'requests', 'label' => 'admin.detail.column.requests'],
            ['key' => 'errors', 'label' => 'admin.detail.column.errors'],
            ['key' => 'averageMs', 'label' => 'admin.detail.column.averageMs'],
            ['key' => 'maxMs', 'label' => 'admin.detail.column.maxMs'],
        ], $rows);
    }

    private function dateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        if (\is_string($value) && '' !== $value) {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        }

        return null;
    }
}
