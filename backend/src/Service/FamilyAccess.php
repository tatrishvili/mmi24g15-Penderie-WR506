<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ChildAccount;
use App\Entity\GuardianLink;
use App\Entity\HouseholdMember;
use App\Entity\Item;
use App\Entity\Profile;
use App\Entity\Room;
use App\Entity\User;
use App\Enum\ProfileRole;
use Doctrine\ORM\EntityManagerInterface;

final class FamilyAccess
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function isGuardian(User $user, Profile $child): bool
    {
        if (!$user->isEmailVerified() || $child->isAdult()) {
            return false;
        }
        $member = $this->em->getRepository(HouseholdMember::class)->findOneBy(['user' => $user, 'household' => $child->getHousehold()]);
        if (null === $member) {
            return false;
        }
        $links = $this->em->getRepository(GuardianLink::class)->findBy(['child' => $child]);
        foreach ($links as $link) {
            if ('active' === $link->state && $link->parent->getId() === $user->getId()) {
                return true;
            }
        }
        if ([] !== $links) {
            return false;
        }
        if (null !== $child->createdBy) {
            return $child->createdBy->getId() === $user->getId();
        }
        // Legacy profiles have no recorded creator. Only the original parent may
        // explicitly activate them; household membership does not imply parenthood.
        $first = $this->em->getRepository(Profile::class)->findOneBy(['household' => $child->getHousehold(), 'role' => ProfileRole::Parent], ['id' => 'ASC']);

        return $first instanceof Profile && $first->getUser()?->getId() === $user->getId();
    }

    public function childEnabled(ChildAccount $account): bool
    {
        if (!$account->enabled || $account->profile->isAdult()) {
            return false;
        }
        foreach ($this->em->getRepository(GuardianLink::class)->findBy(['child' => $account->profile, 'state' => 'active']) as $link) {
            if ($this->isGuardian($link->parent, $account->profile)) {
                return true;
            }
        }

        return false;
    }

    public function canManage(CurrentActor $actor, Item $item): bool
    {
        $profile = $actor->profile();
        if (null === $profile) {
            return false;
        }
        if (!$profile->isAdult()) {
            return $profile->getId() === $item->getOwnerProfile()->getId();
        }
        $user = $actor->requireUser();
        if (!$item->getOwnerProfile()->isAdult()) {
            return $this->isGuardian($user, $item->getOwnerProfile());
        }
        if ($item->getOwnerProfile()->getUser()?->getId() === $user->getId()) {
            return true;
        }

        return 'legacy' === $item->familyAudience && 'personal' !== $item->getVisibility();
    }

    public function canView(CurrentActor $actor, Item $item): bool
    {
        if ($this->canManage($actor, $item)) {
            return true;
        }
        $profile = $actor->profile();
        if (null === $profile || $profile->getHousehold()->getId() !== $item->getHousehold()->getId()) {
            return false;
        }
        if ('archive' === $item->getStatus()->value || 'personal' === $item->getVisibility()) {
            return false;
        }
        if (!$item->getOwnerProfile()->isAdult() && (null === $item->familyApprovedBy || !$this->isGuardian($item->familyApprovedBy, $item->getOwnerProfile()))) {
            return false;
        }

        return \in_array($profile->getId(), $item->familyViewerIds, true)
            && null !== $item->familyDigest && hash_equals($item->familyDigest, self::digest($item));
    }

    public static function canUseRoom(Profile $profile, Room $room): bool
    {
        return $profile->getHousehold()->getId() === $room->getHousehold()->getId()
            && ($profile->isAdult() || $room->common || \in_array($profile->getId(), $room->assignedProfileIds, true));
    }

    /** @return array<string, mixed> */
    public static function snapshot(Item $item): array
    {
        return ['id' => $item->getId(), 'version' => $item->sharingVersion, 'name' => $item->getName(),
            'type' => $item->getType(), 'color' => $item->getColor(), 'brand' => $item->getBrand(),
            'author' => $item->getAuthor(), 'artist' => $item->getArtist(), 'publisher' => $item->getPublisher(),
            'condition' => $item->getCondition()->value,
            'photos' => array_map(static fn ($photo): array => ItemService::photoPayload($photo), [...$item->getPhotos()])];
    }

    public static function digest(Item $item): string
    {
        return hash('sha256', json_encode(self::snapshot($item), JSON_THROW_ON_ERROR));
    }
}
