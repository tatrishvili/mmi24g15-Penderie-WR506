<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AccountSuspension;
use App\Entity\Household;
use App\Entity\HouseholdMember;
use App\Entity\User;
use App\Service\AdminMetrics;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminMetrics $metrics,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $user = $this->currentUser();
        $period = $this->metrics->normalizePeriod($request->query->get('period'));

        return $this->render('admin/dashboard.html.twig', [
            'metrics' => $this->metrics->dashboard($user, $period),
        ]);
    }

    #[Route('/metrics/{metric}', name: 'metric_detail', requirements: ['metric' => '[A-Za-z0-9]+'], methods: ['GET'])]
    public function metricDetail(string $metric, Request $request): JsonResponse
    {
        $detail = $this->metrics->detail($this->currentUser(), $metric, $request->query->get('period', '30d'));
        if (null === $detail) {
            throw $this->createNotFoundException();
        }

        $response = $this->json($detail);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route('/journal', name: 'journal', methods: ['GET'])]
    public function journal(Request $request): Response
    {
        $user = $this->currentUser();
        $household = $user->isSuperAdmin() ? null : $this->metrics->scopeHousehold($user);
        $action = $request->query->get('action');
        if (null !== $action && '' !== $action && 1 !== preg_match('/^[a-zA-Z0-9._-]{1,80}$/', $action)) {
            throw new BadRequestHttpException($this->translator->trans('admin.error.invalidAction'));
        }
        $actorValue = $request->query->get('actor');
        $actorId = null === $actorValue || '' === $actorValue ? null : filter_var($actorValue, FILTER_VALIDATE_INT);
        if (false === $actorId || (\is_int($actorId) && $actorId < 1)) {
            throw new BadRequestHttpException($this->translator->trans('admin.error.invalidActor'));
        }
        $from = $this->parseDateFilter($request, 'from');
        $to = $this->parseDateFilter($request, 'to');
        if (null !== $to) {
            $to = $to->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'));
        }
        if (null !== $from) {
            $from = $from->setTimezone(new \DateTimeZone('UTC'));
        }
        if (null !== $from && null !== $to && $from >= $to) {
            throw new BadRequestHttpException($this->translator->trans('admin.error.invalidDateRange'));
        }
        $pageValue = filter_var($request->query->get('page', '1'), FILTER_VALIDATE_INT);
        if (false === $pageValue || $pageValue < 1) {
            throw new BadRequestHttpException($this->translator->trans('admin.error.invalidPage'));
        }
        $limit = 50;
        $total = $this->metrics->countActivity($household, $action ?: null, $actorId, $from, $to);
        $pages = max(1, (int) ceil($total / $limit));
        $page = min($pageValue, $pages);

        return $this->render('admin/journal.html.twig', [
            'entries' => ($user->isSuperAdmin() || null !== $household)
                ? $this->metrics->recentActivity($household, $limit, ($page - 1) * $limit, $action ?: null, $actorId, $from, $to)
                : [],
            'actions' => $this->metrics->activityActions($household),
            'actors' => $this->metrics->activityActors($household),
            'activityStartedAt' => $this->metrics->activityStartedAt($household),
            'filters' => [
                'action' => $action,
                'actor' => $actorId,
                'from' => $request->query->get('from', ''),
                'to' => $request->query->get('to', ''),
            ],
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    #[Route('/foyers', name: 'households', methods: ['GET'])]
    public function households(): Response
    {
        $user = $this->currentUser();
        $global = $user->isSuperAdmin();
        $household = $global ? null : $this->metrics->scopeHousehold($user);

        $households = $global
            ? $this->em->getRepository(Household::class)->findBy([], ['id' => 'ASC'])
            : (null === $household ? [] : [$household]);

        return $this->render('admin/households.html.twig', [
            'households' => $households,
            'members' => $this->membersByHousehold($households),
        ]);
    }

    #[Route('/utilisateurs', name: 'users', methods: ['GET'])]
    public function users(Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) > 100) {
            throw new BadRequestHttpException($this->translator->trans('admin.users.error.query'));
        }
        $status = $request->query->get('status', 'all');
        if (!\in_array($status, ['all', 'enabled', 'suspended'], true)) {
            throw new BadRequestHttpException($this->translator->trans('admin.users.error.status'));
        }
        $sort = $request->query->get('sort', 'created');
        $sortFields = ['email' => 'u.email', 'created' => 'u.createdAt', 'lastLogin' => 'u.lastLoginAt'];
        if (!isset($sortFields[$sort])) {
            throw new BadRequestHttpException($this->translator->trans('admin.users.error.sort'));
        }
        $direction = strtoupper((string) $request->query->get('direction', 'desc'));
        if (!\in_array($direction, ['ASC', 'DESC'], true)) {
            throw new BadRequestHttpException($this->translator->trans('admin.users.error.sort'));
        }
        $pageValue = filter_var($request->query->get('page', '1'), FILTER_VALIDATE_INT);
        if (false === $pageValue || $pageValue < 1) {
            throw new BadRequestHttpException($this->translator->trans('admin.error.invalidUserPage'));
        }

        $queryBuilder = $this->em->getRepository(User::class)->createQueryBuilder('u');
        if ('' !== $query) {
            $queryBuilder
                ->andWhere('(LOWER(u.email) LIKE :query OR LOWER(u.firstName) LIKE :query OR LOWER(u.username) LIKE :query)')
                ->setParameter('query', '%'.mb_strtolower($query).'%');
        }
        if ('enabled' === $status) {
            $queryBuilder->andWhere('u.enabled = true');
        } elseif ('suspended' === $status) {
            $queryBuilder->andWhere('u.enabled = false');
        }

        $countQuery = clone $queryBuilder;
        $total = (int) $countQuery->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        $limit = 25;
        $pages = max(1, (int) ceil($total / $limit));
        $page = min($pageValue, $pages);
        /** @var list<User> $users */
        $users = $queryBuilder
            ->orderBy($sortFields[$sort], $direction)
            ->addOrderBy('u.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $activeSuspensions = [];
        if ([] !== $users) {
            $suspensions = $this->em->getRepository(AccountSuspension::class)->findBy([
                'user' => $users,
                'restoredAt' => null,
            ]);
            foreach ($suspensions as $suspension) {
                $userId = $suspension->user->getId();
                if (null !== $userId) {
                    $activeSuspensions[$userId] = $suspension;
                }
            }
        }

        $enabledSuperAdminCount = (int) $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.enabled = true')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_SUPER_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'activeSuspensions' => $activeSuspensions,
            'enabledSuperAdminCount' => $enabledSuperAdminCount,
            'filters' => ['q' => $query, 'status' => $status, 'sort' => $sort, 'direction' => strtolower($direction)],
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    private function parseDateFilter(Request $request, string $name): ?\DateTimeImmutable
    {
        $value = $request->query->get($name);
        if (null === $value || '' === $value) {
            return null;
        }
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new BadRequestHttpException($this->translator->trans('admin.error.invalidDate', ['%field%' => $this->translator->trans('admin.journal.'.$name)]));
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('Europe/Paris'));
        $errors = \DateTimeImmutable::getLastErrors();
        if (false === $date || (false !== $errors && (0 !== $errors['warning_count'] || 0 !== $errors['error_count']))) {
            throw new BadRequestHttpException($this->translator->trans('admin.error.invalidDate', ['%field%' => $this->translator->trans('admin.journal.'.$name)]));
        }

        return $date;
    }

    /**
     * @param list<Household> $households
     *
     * @return array<int, list<HouseholdMember>>
     */
    private function membersByHousehold(array $households): array
    {
        $map = [];
        foreach ($households as $household) {
            $map[(int) $household->getId()] = $this->em->getRepository(HouseholdMember::class)->findBy(
                ['household' => $household],
                ['id' => 'ASC'],
            );
        }

        return $map;
    }
}
