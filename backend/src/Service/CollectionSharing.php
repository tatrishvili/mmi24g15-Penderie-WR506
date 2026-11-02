<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CollectionPublication;
use App\Entity\Follow;
use App\Entity\FriendRequest;
use App\Entity\Item;
use App\Entity\ItemPhoto;
use App\Entity\SocialWorkspace;
use App\Entity\User;
use App\Enum\FriendRequestStatus;
use App\Enum\ItemStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Explicit collection projections never grant access to inventory records. */
final class CollectionSharing
{
    public function __construct(private EntityManagerInterface $em, private CommunityIdentityService $identities)
    {
    }

    public function audience(mixed $value): string
    {
        if (!\is_string($value) || !\in_array($value, ['personal', 'friends', 'followers', 'app'], true)) {
            throw new UnprocessableEntityHttpException('Invalid collection audience.');
        }

        return $value;
    }

    public function eligible(Item $item, SocialWorkspace $workspace): bool
    {
        return $item->getHousehold()->getId() === $workspace->household->getId()
            && $item->getOwnerProfile()->isAdult()
            && $item->getOwnerProfile()->getUser()?->getId() === $workspace->owner->getId()
            && 'personal' !== $item->getVisibility()
            && ItemStatus::Archive !== $item->getStatus();
    }

    /** @param array<string, mixed> $collection
     * @return array<string, mixed>
     */
    public function snapshot(SocialWorkspace $workspace, array $collection): array
    {
        $ids = $collection['itemIds'] ?? [];
        $entries = [];
        foreach (\is_array($ids) ? $ids : [] as $id) {
            $item = \is_int($id) ? $this->em->find(Item::class, $id) : null;
            if (!$item instanceof Item || !$this->eligible($item, $workspace)) {
                continue;
            }
            $photo = $item->getPhotos()->first();
            $entries[] = ['sourceId' => $item->getId(), 'photoId' => $photo instanceof ItemPhoto ? $photo->getId() : null,
                'name' => $item->getName(), 'type' => $item->getType(), 'color' => $item->getColor()];
        }
        $cover = $collection['cover'] ?? '';
        $coverBytes = \is_string($cover) && str_contains($cover, ',') ? base64_decode(explode(',', $cover, 2)[1], true) : false;
        $coverImage = false !== $coverBytes ? @imagecreatefromstring($coverBytes) : false;
        $safeCover = '';
        if (false !== $coverImage) {
            ob_start();
            imagepng($coverImage);
            $safeCover = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
            imagedestroy($coverImage);
        }

        return ['name' => $collection['name'], 'cover' => $safeCover, 'items' => $entries];
    }

    /** @param array<string, mixed> $snapshot */
    public function digest(array $snapshot): string
    {
        return hash('sha256', (string) json_encode($snapshot));
    }

    /** @param array<string, mixed> $collection */
    public function publish(SocialWorkspace $workspace, array $collection, string $audience): void
    {
        $record = $this->em->find(CollectionPublication::class, $collection['id']);
        if ('personal' === $audience) {
            if ($record instanceof CollectionPublication && $record->workspace === $workspace) {
                $this->em->remove($record);
            }

            return;
        }
        $snapshot = $this->snapshot($workspace, $collection);
        if ($record instanceof CollectionPublication) {
            $record->audience = $audience;
            $record->presentation = $snapshot;
            $record->updatedAt = new \DateTimeImmutable();
        } else {
            $this->em->persist(new CollectionPublication($collection['id'], $workspace, $audience, $snapshot));
        }
    }

    public function revokeItem(Item $item): void
    {
        /** @var list<CollectionPublication> $publications */
        $publications = $this->em->getRepository(CollectionPublication::class)->createQueryBuilder('p')
            ->join('p.workspace', 'w')->where('w.household = :household')
            ->setParameter('household', $item->getHousehold())->getQuery()->getResult();
        foreach ($publications as $publication) {
            $presentation = $publication->presentation;
            foreach ($presentation['items'] ?? [] as $key => $entry) {
                if ($entry['sourceId'] === $item->getId()) {
                    unset($presentation['items'][$key]);
                }
            }
            $publication->presentation = $presentation;
        }
    }

    public function canView(CollectionPublication $publication, User $viewer): bool
    {
        $owner = $publication->workspace->owner;
        if (!$owner->isEmailVerified()) {
            return false;
        }
        if ($owner->getId() === $viewer->getId() || 'app' === $publication->audience) {
            return true;
        }
        $friend = $this->em->getRepository(FriendRequest::class)->findOneBy([
            'pairKey' => FriendRequest::pairKey($owner, $viewer), 'status' => FriendRequestStatus::Accepted,
        ]);
        if ('friends' === $publication->audience) {
            return null !== $friend;
        }

        return 'followers' === $publication->audience && (null !== $friend || null !== $this->em->getRepository(Follow::class)->findOneBy(['follower' => $viewer, 'followed' => $owner]));
    }

    /** @return array<string, mixed> */
    public function projection(CollectionPublication $publication): array
    {
        $items = [];
        foreach ($publication->presentation['items'] ?? [] as $key => $entry) {
            $item = $this->em->find(Item::class, $entry['sourceId']);
            if (!$item instanceof Item || !$this->eligible($item, $publication->workspace)) {
                continue;
            }
            $photo = isset($entry['photoId']) ? $this->em->find(ItemPhoto::class, $entry['photoId']) : null;
            $items[] = ['name' => $entry['name'], 'type' => $entry['type'], 'color' => $entry['color'],
                'photoUrl' => $photo instanceof ItemPhoto && $photo->getItem()->getId() === $item->getId() ? '/api/social/collection-publications/'.$publication->id.'/photos/'.$key : null];
        }

        $identity = $this->identities->presentation($publication->workspace->owner);

        return ['id' => $publication->id, 'name' => $publication->presentation['name'], 'cover' => $publication->presentation['cover'],
            'visibility' => $publication->audience, 'owner' => $publication->workspace->owner->getUsername(),
            'ownerDisplayName' => $identity['displayName'],
            'ownerPhotoUrl' => $identity['photoUrl'], 'items' => $items];
    }
}
