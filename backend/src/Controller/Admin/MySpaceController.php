<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\ChildAccount;
use App\Entity\Household;
use App\Entity\HouseholdMember;
use App\Entity\Item;
use App\Entity\Loan;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\CurrentActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/my-space', name: 'my_space_')]
final class MySpaceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CurrentActor $actor,
    ) {
    }

    public function index(): Response
    {
        $tokenUser = $this->actor->tokenUser();
        $profile = $this->actor->profile();
        $household = $this->actor->household();

        $isChild = null !== $profile && !$profile->isAdult();
        $isAdult = null !== $profile && $profile->isAdult();
        $isSuperAdmin = null !== $tokenUser && $tokenUser instanceof User && $tokenUser->isSuperAdmin();

        $householdName = null;
        $itemCount = 0;
        $loanCount = 0;
        $memberCount = 0;
        $childAccountCount = 0;
        $ownItems = [];
        $ownLoans = [];
        $childAccounts = [];
        $canManageHousehold = false;
        $canResetChildPin = false;
        $canEditChildRestrictions = false;
        $recentActivity = [];  // Initialize to avoid PHPStan error

        $hasHousehold = $household instanceof Household;

        if ($hasHousehold) {
            $householdName = $household->getName();
            $itemCount = $this->em->getRepository(Item::class)->count(['household' => $household]);

            // Always count members from the same source for consistency
            $members = $this->em->getRepository(HouseholdMember::class)->findBy(['household' => $household], ['id' => 'ASC']);
            $memberCount = count($members);

            $childAccountCount = $this->countChildAccounts($household);

            $canManageHousehold = $isAdult;
            $canResetChildPin = $isAdult;
            $canEditChildRestrictions = $isAdult;

            // Super admin: fetch global KPIs and recent activity
            if ($isSuperAdmin) {
                $households = $this->em->getRepository(Household::class)->count([]);
                $users = $this->em->getRepository(User::class)->count([]);
                $recentActivity = $this->em->createQueryBuilder()
                    ->select('a')
                    ->from(ActivityLog::class, 'a')
                    ->orderBy('a.createdAt', 'DESC')
                    ->setMaxResults(10)
                    ->getQuery()
                    ->getResult();
            }
        }

        if ($isChild) {
            if ($hasHousehold && $profile->getUser() instanceof User) {
                $user = $profile->getUser();

                $ownItems = $this->em->getRepository(Item::class)->findBy([
                    'ownerProfile' => $profile,
                    'household' => $household,
                    'status' => 'active',
                ]);

                // Track item names for journal entries
                $itemNames = [];
                foreach ($ownItems as $item) {
                    $itemNames[$item->getId()] = $item->getName();
                }

                $ownLoans = $this->em->createQueryBuilder()
                    ->select('l')
                    ->from(Loan::class, 'l')
                    ->join('l.item', 'i')
                    ->where('(l.owner = :user OR l.borrower = :user)')
                    ->andWhere('i.household = :household')
                    ->setParameter('user', $user)
                    ->setParameter('household', $household)
                    ->andWhere('l.status IN (:statuses)')
                    ->setParameter('statuses', ['accepted', 'active'])
                    ->getQuery()
                    ->getResult();

                // Track loan item names for journal entries
                $loanItemNames = [];
                foreach ($ownLoans as $loan) {
                    $loanItemNames[$loan->getId()] = $loan->item->getName();
                }
            }
        } elseif ($isAdult || $isSuperAdmin) {
            if ($hasHousehold) {
                $ownLoans = $this->em->createQueryBuilder()
                    ->select('l')
                    ->from(Loan::class, 'l')
                    ->join('l.item', 'i')
                    ->where('i.household = :household')
                    ->andWhere('l.status IN (:statuses)')
                    ->setParameter('household', $household)
                    ->setParameter('statuses', ['accepted', 'active'])
                    ->getQuery()
                    ->getResult();

                if ($isAdult) {
                    $members = $this->em->getRepository(HouseholdMember::class)->findBy(['household' => $household], ['id' => 'ASC']);
                    $memberCount = count($members);

                    $childAccounts = $this->em->getRepository(ChildAccount::class)->findBy(['enabled' => true]);
                    $childAccountCount = count($childAccounts);
                } elseif ($isSuperAdmin) {
                    $memberCount = $this->em->getRepository(HouseholdMember::class)->count([]);
                    $childAccountCount = $this->em->getRepository(ChildAccount::class)->count(['enabled' => true]);
                }
            }
        }

        // Write journal entry for space access
        $this->writeJournalEntry('my_space_viewed', $profile, $household, [
            'scope' => $isChild ? 'child' : ($isAdult ? 'adult' : 'super_admin'),
            'householdId' => $household?->getId(),
        ]);

        $data = [
            'profile' => $profile,
            'user' => $tokenUser instanceof User ? $tokenUser : null,
            'household' => $household,
            'householdName' => $householdName,
            'isChild' => $isChild,
            'isAdult' => $isAdult,
            'isSuperAdmin' => $isSuperAdmin,
            'itemCount' => $itemCount,
            'itemList' => $ownItems,
            'loanCount' => $loanCount,
            'loanList' => $ownLoans,
            'memberCount' => $memberCount,
            'childAccountCount' => $childAccountCount,
            'childAccounts' => $childAccounts,
            'canManageHousehold' => $canManageHousehold,
            'canResetChildPin' => $canResetChildPin,
            'canEditChildRestrictions' => $canEditChildRestrictions,
            'households' => $isSuperAdmin ? $this->em->getRepository(Household::class)->count([]) : 0,
            'users' => $isSuperAdmin ? $this->em->getRepository(User::class)->count([]) : 0,
            'recentActivity' => $isSuperAdmin ? $recentActivity : [],
        ];

        return $this->render('admin/my_space.html.twig', $data);
    }

    #[Route('/household', name: 'household_management', methods: ['GET'])]
    #[IsGranted('ROLE_PARENT')]
    public function householdManagement(): Response
    {
        $user = $this->actor->requireUser();
        $profile = $this->actor->profile();
        $household = $this->actor->household();

        if (null === $household || !$profile->isAdult()) {
            return $this->redirect('/my-space');
        }

        $members = $this->em->getRepository(HouseholdMember::class)->findBy(['household' => $household], ['id' => 'ASC']);
        $childAccounts = $this->em->getRepository(ChildAccount::class)->findBy(['enabled' => true]);

        // Write journal entry
        $this->writeJournalEntry('household_viewed', $profile, $household, [
            'memberCount' => count($members),
            'childAccountCount' => count($childAccounts),
        ]);

        return $this->render('admin/my_space_household_management.html.twig', [
            'profile' => $profile,
            'user' => $user,
            'household' => $household,
            'members' => $members,
            'childAccounts' => $childAccounts,
        ]);
    }

    #[Route('/child/{childAccountId}/pin/reset', name: 'child_pin_reset', methods: ['POST'])]
    #[IsGranted('ROLE_PARENT')]
    public function resetChildPin(int $childAccountId, Request $request): JsonResponse
    {
        $user = $this->actor->requireUser();
        $profile = $this->actor->profile();
        $household = $this->actor->household();

        if (null === $household || !$profile->isAdult()) {
            return new JsonResponse(['success' => false], 403);
        }

        $childAccount = $this->em->getRepository(ChildAccount::class)->find($childAccountId);

        if (null === $childAccount || $childAccount->profile->getHousehold() !== $household) {
            throw $this->createNotFoundException('Child account not found.');
        }

        // Generate a new PIN (4 digits) and set it on the profile
        $newPin = substr((string) random_int(0, 9999), -4, 4);
        $profilePinHash = hash('sha256', $newPin);
        $profile->setPinHash($profilePinHash);
        $this->em->persist($profile);
        $this->em->flush();

        // Write journal entry
        $this->writeJournalEntry('child_pin_reset', $profile, $childAccount, [
            'childAccountId' => $childAccountId,
            'newPinHash' => $profilePinHash,
        ]);

        return new JsonResponse(['success' => true, 'pin' => $newPin]);
    }

    #[Route('/child/{childAccountId}/restrictions', name: 'child_restrictions_edit', methods: ['POST'])]
    #[IsGranted('ROLE_PARENT')]
    public function editChildRestrictions(int $childAccountId, Request $request): JsonResponse
    {
        $user = $this->actor->requireUser();
        $profile = $this->actor->profile();
        $household = $this->actor->household();

        if (null === $household || !$profile->isAdult()) {
            return new JsonResponse(['success' => false], 403);
        }

        $childAccount = $this->em->getRepository(ChildAccount::class)->find($childAccountId);

        if (null === $childAccount || $childAccount->profile->getHousehold() !== $household) {
            throw $this->createNotFoundException('Child account not found.');
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Write journal entry
        $this->writeJournalEntry('child_restrictions_updated', $profile, $childAccount, [
            'childAccountId' => $childAccountId,
            'restrictions' => $data,
        ]);

        return new JsonResponse(['success' => true]);
    }

    private function countChildAccounts(Household $household): int
    {
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(c)')
            ->from(ChildAccount::class, 'c')
            ->where('c.profile.household = :household')
            ->andWhere('c.enabled = true')
            ->setParameter('household', $household)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /** @param array<string, mixed> $metadata */
    private function writeJournalEntry(
        string $action,
        ?Profile $actorProfile,
        ?object $subject = null,
        array $metadata = [],
    ): void {
        $household = null;
        if ($actorProfile instanceof Profile) {
            $household = $actorProfile->getHousehold();
        } elseif ($subject instanceof Household) {
            $household = $subject;
        } elseif (null !== $this->actor->household()) {
            $household = $this->actor->household();
        }

        // Always include name in metadata for recent activity display
        // If name is not set, it will display "—" in the template
        if (!isset($metadata['name'])) {
            $metadata['name'] = null;
        }

        $entry = new ActivityLog($action, $household, null, $actorProfile, null, null, $metadata);
        $this->em->persist($entry);
        $this->em->flush();
    }
}
