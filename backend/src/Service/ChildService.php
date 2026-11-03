<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ChildAccount;
use App\Entity\ChildApproval;
use App\Entity\ChildCollection;
use App\Entity\ChildConnection;
use App\Entity\ChildPublication;
use App\Entity\GuardianLink;
use App\Entity\HouseholdMember;
use App\Entity\InboxNotification;
use App\Entity\Item;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\ProfileRole;
use App\Security\AuthResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ChildService
{
    public function __construct(private EntityManagerInterface $em, private FamilyAccess $access, private UserPasswordHasherInterface $hasher)
    {
    }

    public function parent(CurrentActor $actor, ?Profile $child = null): User
    {
        $user = $actor->requireUser();
        $actor->requireAdultProfile();
        if (!$user->isEmailVerified() || (null !== $child && !$this->access->isGuardian($user, $child))) {
            throw new AccessDeniedHttpException('Only the responsible mother or father can manage this child.');
        }

        return $user;
    }

    public function child(CurrentActor $actor, ?int $id = null): Profile
    {
        $profile = null === $id ? $actor->profile() : $this->em->getRepository(Profile::class)->find($id);
        if (!$profile instanceof Profile || $profile->isAdult() || $profile->getHousehold()->getId() !== $actor->requireHousehold()->getId()) {
            throw new NotFoundHttpException('Child not found.');
        }
        if ($actor->profile()?->getId() !== $profile->getId()) {
            $this->parent($actor, $profile);
        }

        return $profile;
    }

    public static function text(mixed $value, int $max = 100): string
    {
        if (!\is_string($value) || '' === trim($value) || mb_strlen(trim($value)) > $max) {
            throw new UnprocessableEntityHttpException('Enter a valid value.');
        }

        return trim($value);
    }

    public static function relation(mixed $value): string
    {
        if (!\in_array($value, ['mother', 'father'], true)) {
            throw new UnprocessableEntityHttpException('Choose mother or father.');
        }

        return $value;
    }

    public static function validatePassword(mixed $value): string
    {
        if (!\is_string($value) || mb_strlen($value) < 15 || mb_strlen($value) > 128
            || \in_array(strtolower($value), ['passwordpassword', '123456789012345', '1234567890123456', 'motdepasse123456', 'azertyuiop123456', 'qwertyuiop123456'], true)
            || 1 === preg_match('/^(.)\1+$/us', $value)) {
            throw new UnprocessableEntityHttpException('Choose a password of 15 to 128 characters; avoid common passwords.');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public function setup(CurrentActor $actor, array $data): ChildAccount
    {
        $parent = $this->parent($actor);
        $relationship = self::relation($data['relationship'] ?? null);
        $username = strtolower(self::text($data['username'] ?? null, 30));
        if (1 !== preg_match('/\A[a-z0-9_]{3,30}\z/', $username)) {
            throw new UnprocessableEntityHttpException('Use 3 to 30 letters, numbers or underscores.');
        }
        $password = self::validatePassword($data['password'] ?? null);
        if (null !== $this->em->getRepository(ChildAccount::class)->findOneBy(['username' => $username])) {
            throw new ConflictHttpException('Choose another child username.');
        }
        $age = $data['age'] ?? null;
        if (!\is_int($age) || $age < 12 || $age > 17) {
            throw new UnprocessableEntityHttpException('Child accounts are for ages 12 to 17.');
        }

        return $this->em->wrapInTransaction(function () use ($actor, $data, $parent, $relationship, $username, $password): ChildAccount {
            if (isset($data['profileId'])) {
                if (!\is_int($data['profileId'])) {
                    throw new UnprocessableEntityHttpException('Choose a valid child.');
                }
                $profile = $this->child($actor, $data['profileId']);
                $this->parent($actor, $profile);
                if (null !== $this->em->getRepository(ChildAccount::class)->findOneBy(['profile' => $profile])) {
                    throw new ConflictHttpException('This child already has an account.');
                }
            } else {
                $profile = new Profile($actor->requireHousehold(), self::text($data['name'] ?? null), ProfileRole::Child);
                $profile->createdBy = $parent;
                $this->em->persist($profile);
            }
            $link = $this->em->getRepository(GuardianLink::class)->findOneBy(['child' => $profile, 'parent' => $parent]);
            if (null === $link) {
                $this->em->persist(new GuardianLink($profile, $parent, $relationship, $parent));
            }
            $account = new ChildAccount($profile);
            $account->username = $username;
            $this->resetPassword($account, $password);
            $this->em->persist($account);
            $this->em->flush();

            return $account;
        });
    }

    public function resetPassword(ChildAccount $account, string $password): void
    {
        $account->passwordHash = $this->hasher->hashPassword($account, self::validatePassword($password));
        $account->mustChangePassword = true;
        $account->initialPasswordExpiresAt = new \DateTimeImmutable('+24 hours');
        ++$account->sessionVersion;
    }

    public function identity(CurrentActor $actor): string
    {
        $profile = $actor->profile();
        if (null !== $profile && !$profile->isAdult()) {
            return 'child:'.$profile->getId();
        }

        return 'adult:'.$actor->requireUser()->getId();
    }

    public function notify(string $recipient, string $kind, int $id): void
    {
        $this->em->persist(new InboxNotification($recipient, $kind, $id));
    }

    public function notifyParents(Profile $child, string $kind, int $id): void
    {
        foreach ($this->em->getRepository(GuardianLink::class)->findBy(['child' => $child, 'state' => 'active']) as $link) {
            if ($this->access->isGuardian($link->parent, $child)) {
                $this->notify('adult:'.$link->parent->getId(), $kind, $id);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function children(CurrentActor $actor): array
    {
        $parent = $this->parent($actor);
        $rows = [];
        foreach ($this->em->getRepository(Profile::class)->findBy(['household' => $actor->requireHousehold(), 'role' => ProfileRole::Child]) as $child) {
            if (!$this->access->isGuardian($parent, $child)) {
                continue;
            }
            $account = $this->em->getRepository(ChildAccount::class)->findOneBy(['profile' => $child]);
            $rows[] = ['profile' => AuthResponse::profilePayload($child), 'username' => $account?->username,
                'enabled' => $account instanceof ChildAccount ? $account->enabled : false,
                'parents' => array_map(static fn (GuardianLink $link): array => ['id' => $link->id, 'name' => $link->parent->getUsername(), 'relationship' => $link->relationship, 'state' => $link->state, 'canRevoke' => $link->invitedBy->getId() === $parent->getId() && $link->parent->getId() !== $parent->getId()], $this->em->getRepository(GuardianLink::class)->findBy(['child' => $child]))];
        }

        return $rows;
    }

    /** @param array<string, mixed> $data */
    public function inviteParent(CurrentActor $actor, Profile $child, array $data): GuardianLink
    {
        $parent = $this->parent($actor, $child);

        return $this->em->wrapInTransaction(function () use ($parent, $child, $data): GuardianLink {
            $this->em->lock($child, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $relative = $this->em->getRepository(User::class)->findOneBy(['username' => strtolower(self::text($data['username'] ?? null, 30)), 'emailVerified' => true]);
            if (!$relative instanceof User || $relative->getId() === $parent->getId()) {
                throw new UnprocessableEntityHttpException('Choose another verified parent account.');
            }
            $existing = $this->em->getRepository(GuardianLink::class)->findOneBy(['child' => $child, 'parent' => $relative]);
            if ($existing instanceof GuardianLink && \in_array($existing->state, ['active', 'pending'], true)) {
                throw new ConflictHttpException('This parent already has an invitation or access.');
            }
            if (\count($this->em->getRepository(GuardianLink::class)->findBy(['child' => $child, 'state' => ['active', 'pending']])) >= 2) {
                throw new ConflictHttpException('Only the two responsible parents can manage this account.');
            }
            $link = $existing ?? new GuardianLink($child, $relative, self::relation($data['relationship'] ?? null), $parent);
            $link->relationship = self::relation($data['relationship'] ?? null);
            $link->invitedBy = $parent;
            $link->state = 'pending';
            $link->expiresAt = new \DateTimeImmutable('+7 days');
            $this->em->persist($link);
            $this->em->flush();
            $this->notify('adult:'.$relative->getId(), 'parent_invitation', (int) $link->id);
            $this->em->flush();

            return $link;
        });
    }

    public function acceptParent(CurrentActor $actor, GuardianLink $link): void
    {
        $user = $actor->requireUser();
        if (!$user->isEmailVerified() || $link->parent->getId() !== $user->getId() || 'pending' !== $link->state
            || (null !== $link->expiresAt && $link->expiresAt < new \DateTimeImmutable())
            || !$this->access->isGuardian($link->invitedBy, $link->child)) {
            throw new AccessDeniedHttpException('This parent invitation is unavailable.');
        }
        $this->em->wrapInTransaction(function () use ($link, $user): void {
            $home = $link->child->getHousehold();
            if (null === $this->em->getRepository(HouseholdMember::class)->findOneBy(['user' => $user, 'household' => $home])) {
                $this->em->persist(new HouseholdMember($user, $home));
            }
            if (null === $this->em->getRepository(Profile::class)->findOneBy(['user' => $user, 'household' => $home])) {
                $this->em->persist(new Profile($home, $user->getFirstName(), ProfileRole::Partner, $user));
            }
            $link->state = 'active';
            $this->em->flush();
        });
    }

    /**
     * @return list<int>
     */
    public static function ids(mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value) || \count($value) > 100) {
            throw new UnprocessableEntityHttpException('Choose a valid list.');
        }
        foreach ($value as $id) {
            if (!\is_int($id) || $id < 1) {
                throw new UnprocessableEntityHttpException('Choose valid identifiers.');
            }
        }

        return array_values(array_unique($value));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function familyProposal(Item $item, array $data): array
    {
        $mode = $data['audience'] ?? 'owner';
        if (!\in_array($mode, ['owner', 'selected', 'household'], true)) {
            throw new UnprocessableEntityHttpException('Choose an item audience.');
        }
        if ('owner' !== $mode && ('personal' === $item->getVisibility() || 'archive' === $item->getStatus()->value)) {
            throw new ConflictHttpException('Personal or archived items cannot be shared.');
        }
        $profiles = $this->em->getRepository(Profile::class)->findBy(['household' => $item->getHousehold()], ['id' => 'ASC']);
        $ids = 'household' === $mode ? array_map(static fn (Profile $profile): int => (int) $profile->getId(), $profiles) : self::ids($data['profileIds'] ?? []);
        if ('selected' === $mode && [] === $ids) {
            throw new UnprocessableEntityHttpException('Choose at least one person.');
        }
        if ('owner' === $mode) {
            $ids = [];
        }
        $allowed = array_column(array_map(static fn (Profile $profile): array => ['id' => $profile->getId()], $profiles), 'id');
        if ([] !== array_diff($ids, $allowed)) {
            throw new UnprocessableEntityHttpException('Choose people from this household.');
        }

        return ['itemId' => $item->getId(), 'audience' => $mode, 'profileIds' => $ids,
            'recipients' => array_values(array_map(static fn (Profile $profile): array => ['id' => $profile->getId(), 'name' => $profile->getName()], array_filter($profiles, static fn (Profile $profile): bool => \in_array($profile->getId(), $ids, true)))),
            'preview' => FamilyAccess::snapshot($item)];
    }

    /** @param array<string, mixed> $proposal */
    public function applyFamilyAudience(Item $item, array $proposal, User $parent): void
    {
        $item->familyAudience = $proposal['audience'];
        $item->familyViewerIds = $proposal['profileIds'];
        $item->familyDigest = FamilyAccess::digest($item);
        $item->familyApprovedBy = $parent;
    }

    /** @param array<string, mixed> $proposal */
    public function request(Profile $child, string $kind, array $proposal): ChildApproval
    {
        $approval = new ChildApproval($child, $kind, $proposal, hash('sha256', json_encode($proposal, JSON_THROW_ON_ERROR)));
        $this->em->persist($approval);
        $this->em->flush();
        $this->notifyParents($child, 'approval', (int) $approval->id);
        $this->em->flush();

        return $approval;
    }

    public function reviewable(ChildApproval $approval): bool
    {
        if ('pending' === $approval->status && $approval->createdAt < new \DateTimeImmutable('-7 days')) {
            return false;
        }
        $account = $this->em->getRepository(ChildAccount::class)->findOneBy(['profile' => $approval->child]);
        if (!$account instanceof ChildAccount || !$this->access->childEnabled($account)) {
            return false;
        }
        try {
            if ('family_item' === $approval->kind) {
                $item = $this->em->getRepository(Item::class)->find($approval->proposal['itemId']);

                return $item instanceof Item && 'archive' !== $item->getStatus()->value && 'personal' !== $item->getVisibility() && FamilyAccess::snapshot($item) === $approval->proposal['preview'];
            }

            return $this->sourceSnapshot($approval->child, $approval->proposal['snapshot']['kind'], $approval->proposal['snapshot']['sourceId']) === $approval->proposal['snapshot'];
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    public function approvalPayload(ChildApproval $approval): array
    {
        return ['id' => $approval->id, 'version' => $approval->version, 'kind' => $approval->kind,
            'childName' => $approval->child->getName(), 'status' => $approval->status, 'reviewable' => $this->reviewable($approval),
            'proposal' => $approval->proposal, 'createdAt' => $approval->createdAt->format(DATE_ATOM)];
    }

    /** @param array<string, mixed> $data */
    public function setAudience(CurrentActor $actor, Item $item, array $data): ?ChildApproval
    {
        if (!$actor->canManageItem($item)) {
            throw new NotFoundHttpException('Item not found.');
        }
        if (($data['version'] ?? null) !== $item->sharingVersion) {
            throw new ConflictHttpException('This item changed. Reload it before sharing.');
        }
        $proposal = $this->familyProposal($item, $data);
        if ('owner' === $proposal['audience']) {
            $item->touch();
            $item->familyAudience = 'owner';
            $item->familyViewerIds = [];
            $item->familyDigest = null;
            $this->em->flush();

            return null;
        }
        if (false === $actor->profile()?->isAdult()) {
            $account = $this->em->getRepository(ChildAccount::class)->findOneBy(['profile' => $actor->profile()]);
            if (!$account instanceof ChildAccount || !$this->access->childEnabled($account)) {
                throw new AccessDeniedHttpException('Ask your parent to activate your child account first.');
            }
            $item->touch();
            $proposal = $this->familyProposal($item, $data);

            return $this->request($actor->profile(), 'family_item', $proposal);
        }
        if (true !== ($data['approved'] ?? null)) {
            throw new UnprocessableEntityHttpException('Review the displayed item and selected recipients before sharing.');
        }
        $this->applyFamilyAudience($item, $proposal, $actor->requireUser());
        $this->em->flush();

        return null;
    }

    /** @return list<array<string, mixed>> */
    public function collections(CurrentActor $actor, ?int $childId = null): array
    {
        $child = $this->child($actor, $childId);

        return array_map($this->collectionPayload(...), $this->em->getRepository(ChildCollection::class)->findBy(['owner' => $child], ['id' => 'DESC']));
    }

    /** @return array<string, mixed> */
    public function collectionPayload(ChildCollection $collection): array
    {
        return ['id' => $collection->id, 'name' => $collection->name, 'itemIds' => $collection->itemIds, 'version' => $collection->version];
    }

    /** @param array<string, mixed> $data */
    public function saveCollection(CurrentActor $actor, array $data, ?ChildCollection $collection = null): ChildCollection
    {
        $child = $this->child($actor, null !== $collection ? (int) $collection->owner->getId() : (\is_int($data['childId'] ?? null) ? $data['childId'] : null));
        if (null !== $collection && ($data['version'] ?? null) !== $collection->version) {
            throw new ConflictHttpException('Reload this collection before saving.');
        }
        $collection ??= new ChildCollection($child, self::text($data['name'] ?? null));
        $collection->name = self::text($data['name'] ?? $collection->name);
        $ids = self::ids($data['itemIds'] ?? $collection->itemIds);
        foreach ($ids as $id) {
            $item = $this->em->getRepository(Item::class)->find($id);
            if (!$item instanceof Item || $item->getOwnerProfile()->getId() !== $child->getId() || 'archive' === $item->getStatus()->value) {
                throw new UnprocessableEntityHttpException('Collections can contain only this child\'s active items.');
            }
        }
        $collection->itemIds = $ids;
        $this->em->persist($collection);
        $this->em->flush();

        return $collection;
    }

    public function connectionActive(Profile $child, string $recipient): bool
    {
        foreach ($this->em->getRepository(ChildConnection::class)->findBy(['state' => 'active']) as $connection) {
            $left = 'child:'.$connection->requester->getId();
            $right = null !== $connection->recipientChild ? 'child:'.$connection->recipientChild->getId() : 'adult:'.$connection->recipientRelative?->getId();
            if (($left === 'child:'.$child->getId() && $right === $recipient) || ($right === 'child:'.$child->getId() && $left === $recipient)) {
                if (null === $connection->requesterApprovedBy || !$this->access->isGuardian($connection->requesterApprovedBy, $connection->requester)) {
                    return false;
                }
                if (null !== $connection->recipientChild && (null === $connection->recipientApprovedBy || !$this->access->isGuardian($connection->recipientApprovedBy, $connection->recipientChild))) {
                    return false;
                }
                $requesterAccount = $this->em->getRepository(ChildAccount::class)->findOneBy(['profile' => $connection->requester]);
                if (!$requesterAccount instanceof ChildAccount || !$this->access->childEnabled($requesterAccount)) {
                    return false;
                }
                if (null !== $connection->recipientChild) {
                    $other = $this->em->getRepository(ChildAccount::class)->findOneBy(['profile' => $connection->recipientChild]);

                    return $other instanceof ChildAccount && $this->access->childEnabled($other);
                }

                return true === $connection->recipientRelative?->isEmailVerified();
            }
        }

        return false;
    }

    public function connectionProof(Profile $child, string $recipient): ?string
    {
        if (!$this->connectionActive($child, $recipient)) {
            return null;
        }
        foreach ($this->em->getRepository(ChildConnection::class)->findBy(['state' => 'active']) as $connection) {
            $left = 'child:'.$connection->requester->getId();
            $right = null !== $connection->recipientChild ? 'child:'.$connection->recipientChild->getId() : 'adult:'.$connection->recipientRelative?->getId();
            if (($left === 'child:'.$child->getId() && $right === $recipient) || ($right === 'child:'.$child->getId() && $left === $recipient)) {
                return $connection->id.':'.$connection->version;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function connectionPayload(ChildConnection $connection): array
    {
        return ['id' => $connection->id, 'requesterKey' => 'child:'.$connection->requester->getId(), 'recipientKey' => null !== $connection->recipientChild ? 'child:'.$connection->recipientChild->getId() : 'adult:'.$connection->recipientRelative?->getId(), 'requesterId' => $connection->requester->getId(), 'requesterName' => $connection->requester->getName(),
            'recipientId' => $connection->recipientChild?->getId(), 'recipientName' => $connection->recipientChild?->getName() ?? $connection->recipientRelative?->getUsername(),
            'state' => $connection->state, 'relative' => null !== $connection->recipientRelative,
            'requesterApproved' => $connection->requesterApproved, 'recipientApproved' => $connection->recipientApproved, 'recipientAccepted' => $connection->recipientAccepted];
    }

    /** @param array<string, mixed> $data */
    public function requestConnection(CurrentActor $actor, array $data): ChildConnection
    {
        $child = $this->child($actor, \is_int($data['childId'] ?? null) ? $data['childId'] : null);
        $username = strtolower(self::text($data['username'] ?? null, 30));
        $other = null;
        $relative = null;
        if (true === ($data['relative'] ?? false)) {
            $relative = $this->em->getRepository(User::class)->findOneBy(['username' => $username, 'emailVerified' => true]);
        } else {
            $account = $this->em->getRepository(ChildAccount::class)->findOneBy(['username' => $username]);
            if ($account instanceof ChildAccount && $this->access->childEnabled($account)) {
                $other = $account->profile;
            }
        }
        if ((null === $other && !$relative instanceof User) || $other?->getId() === $child->getId()) {
            throw new UnprocessableEntityHttpException('This contact cannot receive a request.');
        }
        foreach ($this->em->getRepository(ChildConnection::class)->findBy(['state' => ['pending', 'active']]) as $existing) {
            $same = $existing->requester->getId() === $child->getId()
                && $existing->recipientChild?->getId() === $other?->getId()
                && $existing->recipientRelative?->getId() === $relative?->getId();
            $reverse = null !== $other && $existing->requester->getId() === $other->getId() && $existing->recipientChild?->getId() === $child->getId();
            if ($same || $reverse) {
                return $existing;
            }
        }
        $connection = new ChildConnection($child, $other, $relative);
        $this->em->persist($connection);
        $this->em->flush();
        $this->notifyParents($child, 'connection', (int) $connection->id);
        if (null !== $other) {
            $this->notify('child:'.$other->getId(), 'connection', (int) $connection->id);
            $this->notifyParents($other, 'connection', (int) $connection->id);
        } else {
            $this->notify('adult:'.$relative->getId(), 'connection', (int) $connection->id);
        }
        $this->em->flush();

        return $connection;
    }

    public function maySeeConnection(CurrentActor $actor, ChildConnection $connection): bool
    {
        $profile = $actor->profile();
        if (null === $profile) {
            return false;
        }
        if (!$profile->isAdult()) {
            return \in_array($profile->getId(), [$connection->requester->getId(), $connection->recipientChild?->getId()], true);
        }
        $user = $actor->requireUser();

        return $user->getId() === $connection->recipientRelative?->getId() || $this->access->isGuardian($user, $connection->requester)
            || (null !== $connection->recipientChild && $this->access->isGuardian($user, $connection->recipientChild));
    }

    public function decideConnection(CurrentActor $actor, ChildConnection $connection, string $action): void
    {
        if (!$this->maySeeConnection($actor, $connection)) {
            throw new NotFoundHttpException('Connection not found.');
        }
        if ('revoke' === $action || 'reject' === $action) {
            $connection->state = 'revoked';
            $this->em->flush();

            return;
        }
        if ('pending' !== $connection->state) {
            throw new ConflictHttpException('This request is no longer pending.');
        }
        if ('accept' === $action) {
            if ($this->identity($actor) !== (null !== $connection->recipientChild ? 'child:'.$connection->recipientChild->getId() : 'adult:'.$connection->recipientRelative?->getId())) {
                throw new AccessDeniedHttpException('Only the invited contact can accept.');
            }
            $connection->recipientAccepted = true;
            if (null !== $connection->recipientRelative) {
                $connection->recipientApproved = true;
            }
        } elseif ('approve' === $action) {
            $parent = $this->parent($actor);
            if (!$this->access->isGuardian($parent, $connection->requester) && (null === $connection->recipientChild || !$this->access->isGuardian($parent, $connection->recipientChild))) {
                throw new AccessDeniedHttpException('Only the responsible mother or father can approve this connection.');
            }
            if ($this->access->isGuardian($parent, $connection->requester)) {
                $connection->requesterApproved = true;
                $connection->requesterApprovedBy = $parent;
            }
            if (null !== $connection->recipientChild && $this->access->isGuardian($parent, $connection->recipientChild)) {
                $connection->recipientApproved = true;
                $connection->recipientApprovedBy = $parent;
            }
        } else {
            throw new UnprocessableEntityHttpException('Choose accept, approve, reject or revoke.');
        }
        if ($connection->requesterApproved && $connection->recipientApproved && $connection->recipientAccepted) {
            $connection->state = 'active';
            $this->notify('child:'.$connection->requester->getId(), 'connection', (int) $connection->id);
        }
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    public function sourceSnapshot(Profile $child, string $kind, int $sourceId): array
    {
        $items = [];
        if ('item' === $kind) {
            $item = $this->em->getRepository(Item::class)->find($sourceId);
            if (!$item instanceof Item || $item->getOwnerProfile()->getId() !== $child->getId()) {
                throw new NotFoundHttpException('Item not found.');
            }
            $items = [$item];
            $name = $item->getName();
            $version = $item->sharingVersion;
        } elseif ('collection' === $kind) {
            $collection = $this->em->getRepository(ChildCollection::class)->find($sourceId);
            if (!$collection instanceof ChildCollection || $collection->owner->getId() !== $child->getId()) {
                throw new NotFoundHttpException('Collection not found.');
            }
            foreach ($collection->itemIds as $id) {
                $item = $this->em->getRepository(Item::class)->find($id);
                if (!$item instanceof Item || $item->getOwnerProfile()->getId() !== $child->getId()) {
                    throw new ConflictHttpException('Collection contents changed.');
                }
                $items[] = $item;
            }
            $name = $collection->name;
            $version = $collection->version;
        } else {
            throw new UnprocessableEntityHttpException('Choose an item or collection.');
        }
        foreach ($items as $item) {
            if ('personal' === $item->getVisibility() || 'archive' === $item->getStatus()->value) {
                throw new ConflictHttpException('Personal or archived content cannot be shared.');
            }
        }

        return ['kind' => $kind, 'sourceId' => $sourceId, 'version' => $version, 'name' => $name, 'items' => array_map(FamilyAccess::snapshot(...), $items)];
    }

    /** @param array<string, mixed> $data */
    public function requestPublication(CurrentActor $actor, array $data): ChildApproval
    {
        $child = $this->child($actor, \is_int($data['childId'] ?? null) ? $data['childId'] : null);
        $kind = self::text($data['kind'] ?? null, 15);
        if (!\is_int($data['sourceId'] ?? null)) {
            throw new UnprocessableEntityHttpException('Choose a source.');
        }
        $snapshot = $this->sourceSnapshot($child, $kind, $data['sourceId']);
        if (($data['version'] ?? null) !== $snapshot['version']) {
            throw new ConflictHttpException('Reload this content before requesting approval.');
        }
        $recipients = $data['recipients'] ?? [];
        if (!\is_array($recipients) || !array_is_list($recipients) || [] === $recipients || \count($recipients) > 50) {
            throw new UnprocessableEntityHttpException('Choose approved contacts.');
        }
        foreach ($recipients as $recipient) {
            if (!\is_string($recipient) || !$this->connectionActive($child, $recipient)) {
                throw new UnprocessableEntityHttpException('Choose approved contacts.');
            }
        }

        $names = [];
        foreach ($recipients as $recipient) {
            [$type, $id] = explode(':', $recipient, 2);
            $contact = 'child' === $type ? $this->em->getRepository(Profile::class)->find((int) $id) : $this->em->getRepository(User::class)->find((int) $id);
            $names[$recipient] = $contact instanceof Profile ? $contact->getName() : ($contact instanceof User ? $contact->getUsername() : '');
        }

        return $this->request($child, 'publication', ['snapshot' => $snapshot, 'recipientNames' => $names, 'recipients' => array_values(array_unique($recipients)), 'connectionProofs' => array_combine($recipients, array_map(fn (string $recipient): ?string => $this->connectionProof($child, $recipient), $recipients))]);
    }

    public function decide(CurrentActor $actor, ChildApproval $approval, bool $approve, int $version): void
    {
        $parent = $this->parent($actor, $approval->child);
        if ($approve && !$this->reviewable($approval)) {
            throw new ConflictHttpException('This content changed or child access was disabled. Ask for a new approval.');
        }
        if ('pending' !== $approval->status || $version !== $approval->version || ($approve && $approval->createdAt < new \DateTimeImmutable('-7 days'))) {
            throw new ConflictHttpException('This request expired or was already handled.');
        }
        $this->em->wrapInTransaction(function () use ($approval, $approve, $parent): void {
            if ($approve) {
                $proposal = $approval->proposal;
                if ('family_item' === $approval->kind) {
                    $item = $this->em->getRepository(Item::class)->find($proposal['itemId']);
                    if (!$item instanceof Item || $item->getOwnerProfile()->getId() !== $approval->child->getId()) {
                        throw new ConflictHttpException('This item is unavailable.');
                    }
                    $current = $this->familyProposal($item, ['audience' => $proposal['audience'], 'profileIds' => $proposal['profileIds']]);
                    $this->familyProposal($item, ['audience' => 'selected', 'profileIds' => $proposal['profileIds']]);
                    // Household approvals freeze the reviewed set, including if new members join.
                    $current['profileIds'] = $proposal['profileIds'];
                    $current['recipients'] = $proposal['recipients'];
                    if (hash('sha256', json_encode($current, JSON_THROW_ON_ERROR)) !== $approval->digest) {
                        throw new ConflictHttpException('This item changed. Ask for a new approval.');
                    }
                    $this->applyFamilyAudience($item, $proposal, $parent);
                } elseif ('publication' === $approval->kind) {
                    $snapshot = $this->sourceSnapshot($approval->child, $proposal['snapshot']['kind'], $proposal['snapshot']['sourceId']);
                    if ($snapshot !== $proposal['snapshot']) {
                        throw new ConflictHttpException('This content changed. Ask for a new approval.');
                    }
                    foreach ($proposal['recipients'] as $recipient) {
                        if (!$this->connectionActive($approval->child, $recipient) || $this->connectionProof($approval->child, $recipient) !== ($proposal['connectionProofs'][$recipient] ?? null)) {
                            throw new ConflictHttpException('This contact is no longer approved.');
                        }
                    }
                    $this->em->persist(new ChildPublication($approval, $proposal['recipients'], $snapshot));
                }
            }
            $approval->status = $approve ? 'approved' : 'rejected';
            $approval->decidedAt = new \DateTimeImmutable();
            $approval->decidedBy = $parent;
            $this->notify('child:'.$approval->child->getId(), 'decision', (int) $approval->id);
            $this->em->flush();
        });
    }

    public function canReadPublication(CurrentActor $actor, ChildPublication $publication): bool
    {
        $approval = $publication->approval;
        if ($publication->revoked || 'approved' !== $approval->status || null === $approval->decidedBy
            || !$this->access->isGuardian($approval->decidedBy, $approval->child)) {
            return false;
        }
        $identity = $this->identity($actor);
        $owner = 'child:'.$approval->child->getId() === $identity;
        $parent = $actor->tokenUser() instanceof User && !$actor->isRestrictedProfile() && $this->access->isGuardian($actor->requireUser(), $approval->child);
        if (!$owner && !$parent && (!\in_array($identity, $publication->recipients, true) || !$this->connectionActive($approval->child, $identity))) {
            return false;
        }
        if (!$owner && !$parent && $this->connectionProof($approval->child, $identity) !== ($approval->proposal['connectionProofs'][$identity] ?? null)) {
            return false;
        }
        try {
            return $this->sourceSnapshot($approval->child, $publication->snapshot['kind'], $publication->snapshot['sourceId']) === $publication->snapshot;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            return false;
        }
    }
}
