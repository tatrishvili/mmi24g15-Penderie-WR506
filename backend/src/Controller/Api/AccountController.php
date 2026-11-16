<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\ActivityDismissal;
use App\Entity\ActivityLog;
use App\Entity\ChildAccount;
use App\Entity\Household;
use App\Entity\HouseholdMember;
use App\Entity\Item;
use App\Entity\ItemShare;
use App\Entity\Loan;
use App\Entity\Place;
use App\Entity\Profile;
use App\Entity\Room;
use App\Entity\User;
use App\Service\CurrentActor;
use App\Service\FamilyAccess;
use App\Service\ItemService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/api/me', name: 'api_me_')]
final class AccountController
{
    private const ACTIVITY_CHANGE_FIELDS = [
        'name', 'type', 'category', 'room', 'storageLocation', 'author', 'artist', 'publisher',
        'brand', 'color', 'size', 'material', 'condition', 'status', 'memory', 'notes',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CurrentActor $actor,
    ) {
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $user = $this->actor->requireUser();
        $household = $this->actor->household();
        $since = new \DateTimeImmutable('-30 days');

        $member = $household instanceof Household
            ? $this->em->getRepository(HouseholdMember::class)->findOneBy(['user' => $user, 'household' => $household])
            : null;
        $activeLoans = 0;
        if ($household instanceof Household) {
            $activeLoans = (int) $this->em->createQuery(
                'SELECT COUNT(l.id) FROM '.Loan::class.' l JOIN l.item i WHERE i.household = :household AND (l.owner = :user OR l.borrower = :user) AND l.status IN (\'accepted\', \'active\')',
            )->setParameters(['household' => $household, 'user' => $user])->getSingleScalarResult();
        }

        return new JsonResponse([
            'household' => $household instanceof Household
                ? ['name' => $household->getName(), 'slug' => $household->getSlug()]
                : null,
            'memberSince' => $member?->getCreatedAt()->format(\DATE_ATOM),
            'profiles' => $this->count('SELECT COUNT(p.id) FROM App\\Entity\\Profile p WHERE p.household = :household', $household),
            'places' => $this->count('SELECT COUNT(p.id) FROM App\\Entity\\Place p WHERE p.household = :household', $household),
            'items' => $this->count('SELECT COUNT(i.id) FROM App\\Entity\\Item i WHERE i.household = :household', $household),
            'members' => $this->count('SELECT COUNT(m.id) FROM App\\Entity\\HouseholdMember m WHERE m.household = :household', $household),
            'activeLoans' => $activeLoans,
            'activity30d' => (int) $this->em->createQuery(
                'SELECT COUNT(a.id) FROM '.ActivityLog::class.' a WHERE a.actorUser = :user AND a.createdAt >= :since',
            )->setParameters(['user' => $user, 'since' => $since])->getSingleScalarResult(),
        ]);
    }

    #[Route('/space', name: 'space', methods: ['GET'])]
    public function space(): JsonResponse
    {
        $household = $this->actor->requireHousehold();
        $profile = $this->actor->profile();
        if (null === $profile) {
            $profile = $this->actor->requireAdultProfile();
        }
        $isChild = !$profile->isAdult();
        $user = $isChild ? null : $this->actor->requireUser();

        /** @var list<Item> $householdItems */
        $householdItems = $this->em->getRepository(Item::class)->findBy(['household' => $household], ['id' => 'DESC']);
        $items = array_values(array_filter(
            $householdItems,
            fn (Item $item): bool => $isChild
                ? $item->getOwnerProfile()->getId() === $profile->getId()
                : $this->actor->canViewItem($item),
        ));

        $roomItemCounts = [];
        $profileItemCounts = [];
        $itemPayloads = [];
        foreach ($items as $item) {
            $roomId = $item->getRoom()?->getId();
            if (null !== $roomId) {
                $roomItemCounts[$roomId] = ($roomItemCounts[$roomId] ?? 0) + 1;
            }
            $ownerId = $item->getOwnerProfile()->getId();
            if (null !== $ownerId) {
                $profileItemCounts[$ownerId] = ($profileItemCounts[$ownerId] ?? 0) + 1;
            }

            $itemPayload = ItemService::payloadFor($this->actor, $item);
            $room = \is_array($itemPayload['room'] ?? null) ? $itemPayload['room'] : null;
            $place = null !== $room && \is_array($room['place'] ?? null) ? $room['place'] : null;
            $storage = \is_array($itemPayload['storageLocation'] ?? null) ? $itemPayload['storageLocation'] : null;
            $locationParts = array_filter([
                \is_array($place) && \is_string($place['name'] ?? null) ? $place['name'] : null,
                \is_array($room) && \is_string($room['name'] ?? null) ? $room['name'] : null,
                \is_array($storage) && \is_array($storage['path'] ?? null) ? implode(' / ', $storage['path']) : null,
            ]);
            $photos = \is_array($itemPayload['photos'] ?? null) ? $itemPayload['photos'] : [];
            $photoUrl = null;
            foreach ($photos as $photo) {
                if (\is_array($photo) && \is_string($photo['url'] ?? null)) {
                    $photoUrl = $photo['url'];
                    if (true === ($photo['isPrimary'] ?? false)) {
                        break;
                    }
                }
            }

            $itemId = $item->getId();
            if (null === $itemId) {
                continue;
            }
            $itemPayloads[] = [
                'id' => $itemId,
                'name' => $item->getName(),
                'status' => $item->getStatus()->value,
                'photoUrl' => $photoUrl,
                'place' => implode(' · ', $locationParts),
                'roomId' => $roomId,
                'ownerProfileId' => $ownerId,
            ];
        }

        $profiles = [];
        $places = [];
        if (!$isChild) {
            /** @var list<Profile> $householdProfiles */
            $householdProfiles = $this->em->getRepository(Profile::class)->findBy(['household' => $household], ['id' => 'ASC']);
            foreach ($householdProfiles as $householdProfile) {
                $profileId = $householdProfile->getId();
                if (null === $profileId) {
                    continue;
                }
                $profiles[] = [
                    'id' => $profileId,
                    'name' => $householdProfile->getName(),
                    'role' => $householdProfile->getRole()->value,
                    'itemCount' => $profileItemCounts[$profileId] ?? 0,
                ];
            }

            /** @var list<Place> $householdPlaces */
            $householdPlaces = $this->em->getRepository(Place::class)->findBy(['household' => $household], ['id' => 'ASC']);
            foreach ($householdPlaces as $place) {
                /** @var list<Room> $rooms */
                $rooms = $this->em->getRepository(Room::class)->findBy(['place' => $place], ['id' => 'ASC']);
                $roomPayloads = [];
                foreach ($rooms as $room) {
                    $roomId = $room->getId();
                    if (null === $roomId) {
                        continue;
                    }
                    $roomPayloads[] = [
                        'id' => $roomId,
                        'name' => $room->getName(),
                        'itemCount' => $roomItemCounts[$roomId] ?? 0,
                    ];
                }
                $placeId = $place->getId();
                if (null === $placeId) {
                    continue;
                }
                $places[] = [
                    'id' => $placeId,
                    'name' => $place->getName(),
                    'rooms' => $roomPayloads,
                ];
            }
        }

        $loans = $this->activeLoans($household, $profile, $isChild, $user);
        $members = $isChild ? [] : $this->householdAccounts($household);
        $shares = $isChild ? [] : $this->activeShares($household, $user);

        return new JsonResponse([
            'household' => ['id' => $household->getId(), 'name' => $household->getName()],
            'isChild' => $isChild,
            'canManage' => !$isChild,
            'stats' => [
                'items' => \count($itemPayloads),
                'places' => \count($places),
                'profiles' => \count($profiles),
                'activeLoans' => \count($loans),
            ],
            'items' => $itemPayloads,
            'places' => $places,
            'profiles' => $profiles,
            'members' => $members,
            'loans' => $loans,
            'shares' => $shares,
            'activity' => $this->recentActivity($household, $profile, $isChild),
        ]);
    }

    #[Route('/activity', name: 'activity', methods: ['GET'])]
    public function activity(): JsonResponse
    {
        $household = $this->actor->requireHousehold();
        $profile = $this->actor->profile();
        if (null === $profile) {
            $profile = $this->actor->requireAdultProfile();
        }

        return new JsonResponse($this->recentActivity($household, $profile, !$profile->isAdult()));
    }

    #[Route('/space/activity/{id}', name: 'space_activity_dismiss', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function dismissActivity(int $id): JsonResponse
    {
        $household = $this->actor->requireHousehold();
        $profile = $this->actor->profile();
        if (null === $profile) {
            $profile = $this->actor->requireAdultProfile();
        }
        $isChild = !$profile->isAdult();
        $actorKey = $this->activityActorKey($profile);
        $entry = $this->em->getRepository(ActivityLog::class)->findOneBy([
            'id' => $id,
            'household' => $household,
        ]);
        if (!$entry instanceof ActivityLog) {
            throw new NotFoundHttpException('Activity not found.');
        }

        $repository = $this->em->getRepository(ActivityDismissal::class);
        if ($repository->findOneBy(['actorKey' => $actorKey, 'activityLog' => $entry]) instanceof ActivityDismissal) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $visible = false;
        foreach ($this->recentActivity($household, $profile, $isChild) as $activity) {
            if ($activity['id'] === $id) {
                $visible = true;

                break;
            }
        }
        if (!$visible) {
            throw new NotFoundHttpException('Activity not found.');
        }

        $this->em->persist(new ActivityDismissal($actorKey, $entry));
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return list<array{id: int, action: string, actor: string, objectName: string, createdAt: string, changes: list<string>, itemId: ?int, canEdit: bool}>
     */
    private function recentActivity(Household $household, Profile $profile, bool $isChild): array
    {
        $actions = $isChild
            ? ['item.created', 'item.updated', 'item.deleted', 'item.photo.created', 'item.photo.deleted']
            : [
                'item.created', 'item.updated', 'item.deleted', 'item.photo.created', 'item.photo.deleted',
                'household.created', 'household.updated', 'household.selected',
                'loan.created', 'loan.updated', 'share.created', 'share.deleted',
            ];
        $query = $this->em->createQueryBuilder()
            ->select('a')
            ->from(ActivityLog::class, 'a')
            ->where('a.household = :household')
            ->andWhere('a.action IN (:actions)')
            ->setParameter('household', $household)
            ->setParameter('actions', $actions);
        $dismissedActivities = $this->em->createQueryBuilder()
            ->select('IDENTITY(d.activityLog)')
            ->from(ActivityDismissal::class, 'd')
            ->where('d.actorKey = :dismissalActor');
        $query->andWhere($query->expr()->notIn('a.id', $dismissedActivities->getDQL()))
            ->setParameter('dismissalActor', $this->activityActorKey($profile));
        if ($isChild) {
            $query->andWhere('a.actorProfile = :profile')
                ->setParameter('profile', $profile);
        }

        /** @var list<ActivityLog> $entries */
        $entries = $query
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        $activity = [];
        foreach ($entries as $entry) {
            $objectName = $this->activityObjectName($entry, $household, $profile, $isChild);
            if (null === $objectName || '' === trim($objectName)) {
                continue;
            }
            $item = $this->activityItem($entry, $household);
            $actorName = $entry->getActorProfile()?->getName()
                ?? $entry->getActorUser()?->getFirstName()
                ?? '';
            if ('' === trim($actorName)) {
                continue;
            }
            $id = $entry->getId();
            if (null === $id) {
                continue;
            }

            $changes = [];
            $metadata = $entry->getMetadata();
            if (\is_array($metadata['changes'] ?? null)) {
                foreach ($metadata['changes'] as $change) {
                    if (\is_string($change)
                        && \in_array($change, self::ACTIVITY_CHANGE_FIELDS, true)
                        && !\in_array($change, $changes, true)
                    ) {
                        $changes[] = $change;
                    }
                }
            }

            $activity[] = [
                'id' => $id,
                'action' => $entry->getAction(),
                'actor' => $actorName,
                'objectName' => $objectName,
                'createdAt' => $entry->getCreatedAt()->format(\DATE_ATOM),
                'changes' => $changes,
                'itemId' => $item instanceof Item ? $item->getId() : null,
                'canEdit' => $item instanceof Item && $this->actor->canManageItem($item),
            ];
            if (\count($activity) >= 20) {
                break;
            }
        }

        return $activity;
    }

    private function activityItem(ActivityLog $entry, Household $household): ?Item
    {
        $action = $entry->getAction();
        $subjectId = $entry->getSubjectId();
        if (str_starts_with($action, 'item.')) {
            $item = null === $subjectId ? null : $this->em->getRepository(Item::class)->findOneBy([
                'id' => $subjectId,
                'household' => $household,
            ]);

            return $item instanceof Item && $this->actor->canViewItem($item) ? $item : null;
        }

        if ('share.created' === $action) {
            $item = null === $subjectId ? null : $this->em->getRepository(Item::class)->findOneBy([
                'id' => $subjectId,
                'household' => $household,
            ]);

            return $item instanceof Item && $this->actor->canViewItem($item) ? $item : null;
        }

        if ('share.deleted' === $action) {
            $share = null === $subjectId ? null : $this->em->getRepository(ItemShare::class)->find($subjectId);

            return $share instanceof ItemShare
                && $share->item->getHousehold()->getId() === $household->getId()
                && $this->actor->canViewItem($share->item)
                    ? $share->item
                    : null;
        }

        if (str_starts_with($action, 'loan.')) {
            $loan = null === $subjectId ? null : $this->em->getRepository(Loan::class)->find($subjectId);
            $item = $loan instanceof Loan ? $loan->item : null;

            return $item instanceof Item
                && $item->getHousehold()->getId() === $household->getId()
                && $this->actor->canViewItem($item)
                    ? $item
                    : null;
        }

        return null;
    }

    private function activityActorKey(Profile $profile): string
    {
        if (!$profile->isAdult()) {
            $profileId = $profile->getId();
            if (null === $profileId) {
                throw new \LogicException('The active child profile must be persisted.');
            }

            return 'child:'.$profileId;
        }

        $userId = $this->actor->requireUser()->getId();
        if (null === $userId) {
            throw new \LogicException('The authenticated account must be persisted.');
        }

        return 'adult:'.$userId;
    }

    private function activityObjectName(ActivityLog $entry, Household $household, Profile $profile, bool $isChild): ?string
    {
        $action = $entry->getAction();
        if (str_starts_with($action, 'household.')) {
            return $household->getName();
        }

        $subjectId = $entry->getSubjectId();
        $metadata = $entry->getMetadata();
        if (str_starts_with($action, 'item.')) {
            $item = null === $subjectId ? null : $this->em->getRepository(Item::class)->findOneBy([
                'id' => $subjectId,
                'household' => $household,
            ]);
            if ($item instanceof Item) {
                return $this->actor->canViewItem($item) ? $item->getName() : null;
            }

            if (!$this->activityActorIsCurrent($entry, $profile, $isChild)) {
                return null;
            }

            return \is_string($metadata['objectName'] ?? null) ? $metadata['objectName'] : null;
        }

        if ('share.created' === $action) {
            $item = null === $subjectId ? null : $this->em->getRepository(Item::class)->findOneBy([
                'id' => $subjectId,
                'household' => $household,
            ]);

            return $item instanceof Item && $this->actor->canViewItem($item) ? $item->getName() : null;
        }
        if ('share.deleted' === $action) {
            $share = null === $subjectId ? null : $this->em->getRepository(ItemShare::class)->find($subjectId);

            return $share instanceof ItemShare
                && $share->item->getHousehold()->getId() === $household->getId()
                && $this->actor->canViewItem($share->item)
                    ? $share->item->getName()
                    : null;
        }
        if (str_starts_with($action, 'loan.')) {
            $loan = null === $subjectId ? null : $this->em->getRepository(Loan::class)->find($subjectId);
            if (!$loan instanceof Loan) {
                return null;
            }
            if (null !== $loan->item) {
                return $loan->item->getHousehold()->getId() === $household->getId()
                    && $this->actor->canViewItem($loan->item)
                        ? $loan->itemName
                        : null;
            }

            return $this->activityActorIsCurrent($entry, $profile, $isChild) ? $loan->itemName : null;
        }

        return null;
    }

    private function activityActorIsCurrent(ActivityLog $entry, Profile $profile, bool $isChild): bool
    {
        if ($isChild) {
            return $entry->getActorProfile()?->getId() === $profile->getId();
        }

        $user = $this->actor->tokenUser();

        return $user instanceof User && $entry->getActorUser()?->getId() === $user->getId();
    }

    /**
     * @return list<array{id: string, name: string, role: string, lastLoginAt: string|null}>
     */
    private function householdAccounts(Household $household): array
    {
        $accounts = [];
        /** @var list<HouseholdMember> $members */
        $members = $this->em->getRepository(HouseholdMember::class)->findBy(['household' => $household], ['id' => 'ASC']);
        foreach ($members as $member) {
            $user = $member->getUser();
            $userId = $user->getId();
            if (null === $userId || !$user->isEnabled() || !$user->isEmailVerified()) {
                continue;
            }
            $profile = $this->em->getRepository(Profile::class)->findOneBy([
                'user' => $user,
                'household' => $household,
            ]);
            $accounts[] = [
                'id' => 'user-'.$userId,
                'name' => $profile instanceof Profile ? $profile->getName() : $user->getFirstName(),
                'role' => $profile instanceof Profile ? $profile->getRole()->value : 'parent',
                'lastLoginAt' => $user->getLastLoginAt()?->format(\DATE_ATOM),
            ];
        }

        /** @var list<ChildAccount> $children */
        $children = $this->em->getRepository(ChildAccount::class)->findBy(['enabled' => true]);
        foreach ($children as $child) {
            if (null === $child->id
                || $child->profile->getHousehold()->getId() !== $household->getId()
                || !(new FamilyAccess($this->em))->childEnabled($child)) {
                continue;
            }
            $accounts[] = [
                'id' => 'child-'.$child->id,
                'name' => $child->profile->getName(),
                'role' => 'child',
                'lastLoginAt' => $this->lastChildLogin($child->profile)?->format(\DATE_ATOM),
            ];
        }

        return $accounts;
    }

    private function lastChildLogin(Profile $profile): ?\DateTimeImmutable
    {
        $entry = $this->em->createQueryBuilder()
            ->select('a')
            ->from(ActivityLog::class, 'a')
            ->where('a.actorProfile = :profile')
            ->andWhere('a.action = :action')
            ->setParameter('profile', $profile)
            ->setParameter('action', 'auth.login')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $entry instanceof ActivityLog ? $entry->getCreatedAt() : null;
    }

    /**
     * @return list<array{id: int, itemName: string, borrower: string, since: string, canReturn: bool}>
     */
    private function activeLoans(Household $household, Profile $profile, bool $isChild, ?User $user): array
    {
        $query = $this->em->createQueryBuilder()
            ->select('l')
            ->from(Loan::class, 'l')
            ->join('l.item', 'i')
            ->where('i.household = :household')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('household', $household)
            ->setParameter('statuses', ['accepted', 'active']);
        if ($isChild) {
            $query->andWhere('i.ownerProfile = :profile')
                ->setParameter('profile', $profile);
        }

        /** @var list<Loan> $loans */
        $loans = $query->orderBy('l.id', 'DESC')->getQuery()->getResult();

        $loans = array_values(array_filter($loans, fn (Loan $loan): bool => null !== $loan->item
            && $this->actor->canViewItem($loan->item)
            && (!$isChild || $loan->item->getOwnerProfile()->getId() === $profile->getId())));
        $payloads = [];
        foreach ($loans as $loan) {
            if (null === $loan->id) {
                continue;
            }
            $payloads[] = [
                'id' => $loan->id,
                'itemName' => $loan->itemName,
                'borrower' => $loan->borrower->getUsername() ?? $loan->borrower->getFirstName(),
                'since' => ($loan->handedAt ?? $loan->acceptedAt ?? $loan->requestedAt)->format(\DATE_ATOM),
                'canReturn' => !$isChild && null !== $user && $loan->owner->getId() === $user->getId(),
            ];
        }

        return $payloads;
    }

    /**
     * @return list<array{id: int, itemName: string, expiresAt: string}>
     */
    private function activeShares(Household $household, ?User $user): array
    {
        if (null === $user) {
            return [];
        }
        $now = new \DateTimeImmutable();
        /** @var list<ItemShare> $shares */
        $shares = $this->em->getRepository(ItemShare::class)->findBy(['owner' => $user, 'revoked' => false], ['id' => 'DESC']);
        $active = array_filter($shares, static fn (ItemShare $share): bool => $share->item->getHousehold()->getId() === $household->getId()
            && !$share->moderationDisabled
            && $share->expiresAt > $now);

        $payloads = [];
        foreach ($active as $share) {
            if (null === $share->id) {
                continue;
            }
            $payloads[] = [
                'id' => $share->id,
                'itemName' => $share->item->getName(),
                'expiresAt' => $share->expiresAt->format(\DATE_ATOM),
            ];
        }

        return $payloads;
    }

    private function count(string $dql, ?object $household): int
    {
        if (null === $household) {
            return 0;
        }

        return (int) $this->em->createQuery($dql)->setParameter('household', $household)->getSingleScalarResult();
    }
}
