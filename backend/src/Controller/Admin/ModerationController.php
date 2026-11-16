<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AccountSuspension;
use App\Entity\ActivityLog;
use App\Entity\SharedItemReport;
use App\Entity\User;
use App\Enum\ItemStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin', name: 'admin_moderation_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ModerationController extends AbstractController
{
    private const SUSPENSION_REASONS = ['abuse', 'policy', 'security', 'other'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/utilisateurs/{id}/suspendre', name: 'user_suspend', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function suspendUser(int $id, Request $request): RedirectResponse
    {
        $this->requireCsrf($request, 'suspend-user-'.$id);
        $target = $this->em->getRepository(User::class)->find($id);
        if (!$target instanceof User) {
            throw $this->createNotFoundException();
        }

        $admin = $this->currentUser();
        if ($admin->getId() === $target->getId()) {
            $this->addFlash('error', $this->translator->trans('admin.user.error.self'));

            return $this->redirectToRoute('admin_users');
        }

        $reason = $request->request->get('reason');
        if (!\is_string($reason) || !\in_array($reason, self::SUSPENSION_REASONS, true)) {
            throw new BadRequestHttpException($this->translator->trans('admin.user.error.reason'));
        }

        $this->em->wrapInTransaction(function () use ($target, $admin, $reason): void {
            if (!$this->em->getConnection()->getDatabasePlatform() instanceof SQLitePlatform) {
                $this->em->lock($target, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($target);
            }

            if ($target->isSuperAdmin()) {
                $enabledAdmins = $this->enabledSuperAdminsForUpdate();
                if (\count($enabledAdmins) <= 1) {
                    throw new ConflictHttpException($this->translator->trans('admin.user.error.lastAdmin'));
                }
            }

            if (!$target->isEnabled()
                || $this->em->getRepository(AccountSuspension::class)->findOneBy(['user' => $target, 'restoredAt' => null]) instanceof AccountSuspension) {
                throw new ConflictHttpException($this->translator->trans('admin.user.error.alreadySuspended'));
            }

            $target->setEnabled(false);
            $suspension = new AccountSuspension($target, $admin, $reason);
            $this->em->persist($suspension);
            $this->em->persist(new ActivityLog(
                'admin.user.suspended',
                null,
                $admin,
                null,
                'user',
                $target->getId(),
                ['reason' => $reason],
            ));
        });

        $this->addFlash('success', $this->translator->trans('admin.user.suspended'));

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/utilisateurs/{id}/restaurer', name: 'user_restore', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function restoreUser(int $id, Request $request): RedirectResponse
    {
        $this->requireCsrf($request, 'restore-user-'.$id);
        $suspension = $this->em->getRepository(AccountSuspension::class)->findOneBy(
            ['user' => $id, 'restoredAt' => null],
            ['id' => 'DESC'],
        );
        if (!$suspension instanceof AccountSuspension) {
            throw $this->createNotFoundException();
        }

        $admin = $this->currentUser();
        $this->em->wrapInTransaction(function () use ($suspension, $admin): void {
            if (!$this->em->getConnection()->getDatabasePlatform() instanceof SQLitePlatform) {
                $this->em->lock($suspension, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($suspension);
            }
            if (null !== $suspension->restoredAt) {
                throw new ConflictHttpException($this->translator->trans('admin.user.error.alreadyRestored'));
            }

            $suspension->user->setEnabled(true);
            $suspension->restoredAt = new \DateTimeImmutable();
            $suspension->restoredBy = $admin;
            $this->em->persist(new ActivityLog(
                'admin.user.restored',
                null,
                $admin,
                null,
                'user',
                $suspension->user->getId(),
            ));
        });

        $this->addFlash('success', $this->translator->trans('admin.user.restored'));

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/signalements', name: 'reports', methods: ['GET'])]
    public function reports(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $status = $request->query->get('status', 'pending');
        $allowedStatuses = ['pending', 'taken_down', 'resolved', 'all'];
        if (!\in_array($status, $allowedStatuses, true)) {
            throw new BadRequestHttpException($this->translator->trans('admin.report.error.status'));
        }

        $query = $this->em->getRepository(SharedItemReport::class)->createQueryBuilder('report')
            ->join('report.share', 'share')->addSelect('share')
            ->join('share.owner', 'owner')->addSelect('owner')
            ->join('report.reporter', 'reporter')->addSelect('reporter')
            ->orderBy('report.createdAt', 'DESC');

        if ('pending' === $status) {
            $query->andWhere('report.status = :pending')->setParameter('pending', SharedItemReport::STATUS_PENDING);
        } elseif ('taken_down' === $status) {
            $query->andWhere('report.status = :takenDown')->setParameter('takenDown', SharedItemReport::STATUS_TAKEN_DOWN);
        } elseif ('resolved' === $status) {
            $query->andWhere('report.status IN (:resolved)')->setParameter('resolved', [
                SharedItemReport::STATUS_DISMISSED,
                SharedItemReport::STATUS_RESTORED,
            ]);
        }

        $pageValue = filter_var($request->query->get('page', '1'), FILTER_VALIDATE_INT);
        if (false === $pageValue || $pageValue < 1) {
            throw new BadRequestHttpException($this->translator->trans('admin.error.invalidReportPage'));
        }
        $limit = 20;
        $countQuery = clone $query;
        $total = (int) $countQuery->select('COUNT(report.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / $limit));
        $page = min($pageValue, $pages);
        $reports = $query->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery()->getResult();

        return $this->render('admin/reports.html.twig', [
            'reports' => $reports,
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    #[Route('/signalements/{id}/{decision}', name: 'report_decision', requirements: ['id' => '\d+', 'decision' => 'dismiss|take-down|restore'], methods: ['POST'])]
    public function decideReport(int $id, string $decision, Request $request): RedirectResponse
    {
        $this->requireCsrf($request, 'report-'.$decision.'-'.$id);
        $report = $this->em->getRepository(SharedItemReport::class)->find($id);
        if (!$report instanceof SharedItemReport) {
            throw $this->createNotFoundException();
        }

        $admin = $this->currentUser();
        $this->em->wrapInTransaction(function () use ($report, $admin, $decision): void {
            if ('dismiss' === $decision && SharedItemReport::STATUS_PENDING === $report->status) {
                $report->review(SharedItemReport::STATUS_DISMISSED, $admin);
                $action = 'admin.report.dismissed';
            } elseif ('take-down' === $decision && SharedItemReport::STATUS_PENDING === $report->status) {
                $this->assertShareCanBeTakenDown($report);
                $report->share->moderationDisabled = true;
                $report->review(SharedItemReport::STATUS_TAKEN_DOWN, $admin);
                $action = 'admin.share.taken_down';
            } elseif ('restore' === $decision && SharedItemReport::STATUS_TAKEN_DOWN === $report->status) {
                $this->assertShareCanBeRestored($report);
                $report->share->moderationDisabled = false;
                $report->review(SharedItemReport::STATUS_RESTORED, $admin);
                $action = 'admin.share.restored';
            } else {
                throw new ConflictHttpException($this->translator->trans('admin.report.error.state'));
            }

            $this->em->persist(new ActivityLog(
                $action,
                null,
                $admin,
                null,
                'shared_item_report',
                $report->id,
            ));
        });

        $this->addFlash('success', $this->translator->trans('admin.report.action.'.$decision.'.success'));
        $status = $request->request->get('status', 'pending');
        if (!\is_string($status) || !\in_array($status, ['pending', 'taken_down', 'resolved', 'all'], true)) {
            $status = 'pending';
        }

        return $this->redirectToRoute('admin_moderation_reports', ['status' => $status]);
    }

    private function assertShareCanBeTakenDown(SharedItemReport $report): void
    {
        $share = $report->share;
        if ($share->revoked || $share->moderationDisabled || $share->expiresAt <= new \DateTimeImmutable()
            || 'personal' === $share->item->getVisibility() || ItemStatus::Archive === $share->item->getStatus()
            || !$share->item->getOwnerProfile()->isAdult()) {
            throw new ConflictHttpException($this->translator->trans('admin.report.error.shareUnavailable'));
        }
    }

    private function assertShareCanBeRestored(SharedItemReport $report): void
    {
        $share = $report->share;
        if ($share->revoked || !$share->moderationDisabled || $share->expiresAt <= new \DateTimeImmutable()
            || 'personal' === $share->item->getVisibility() || ItemStatus::Archive === $share->item->getStatus()
            || !$share->item->getOwnerProfile()->isAdult()) {
            throw new ConflictHttpException($this->translator->trans('admin.report.error.shareUnavailable'));
        }
    }

    /**
     * @return list<User>
     */
    private function enabledSuperAdminsForUpdate(): array
    {
        $enabledUsers = $this->em->getRepository(User::class)->findBy(['enabled' => true], ['id' => 'ASC']);
        $admins = [];
        foreach ($enabledUsers as $user) {
            if (!$user->isSuperAdmin()) {
                continue;
            }
            if (!$this->em->getConnection()->getDatabasePlatform() instanceof SQLitePlatform) {
                $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($user);
            }
            if ($user->isEnabled() && $user->isSuperAdmin()) {
                $admins[] = $user;
            }
        }

        return $admins;
    }

    private function requireCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
