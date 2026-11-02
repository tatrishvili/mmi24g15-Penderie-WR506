<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CollectionPublication;
use App\Entity\Follow;
use App\Entity\FriendRequest;
use App\Entity\Item;
use App\Entity\ItemPhoto;
use App\Entity\SocialWorkspace;
use App\Entity\User;
use App\Enum\FriendRequestStatus;
use App\Service\CollectionSharing;
use App\Service\CommunityIdentityService;
use App\Service\CurrentActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/social')]
final class CollectionPublicationController
{
    public function __construct(private CurrentActor $actor, private EntityManagerInterface $em, private CollectionSharing $sharing, private CommunityIdentityService $identities,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    private function adult(): User
    {
        $user = $this->actor->requireUser();
        $this->actor->requireAdultProfile();
        if (!$user->isEmailVerified() || (null !== $this->actor->profile() && !$this->actor->profile()->isAdult())) {
            throw new AccessDeniedHttpException();
        }

        return $user;
    }

    private function ownWorkspace(): SocialWorkspace
    {
        $workspace = $this->em->getRepository(SocialWorkspace::class)->findOneBy(['owner' => $this->adult(), 'household' => $this->actor->requireHousehold()]);
        if (!$workspace instanceof SocialWorkspace) {
            throw new NotFoundHttpException();
        }

        return $workspace;
    }

    /** @return array<string, mixed> */
    private function collection(SocialWorkspace $workspace, string $id): array
    {
        foreach ($workspace->collections as $collection) {
            if ($collection['id'] === $id) {
                return $collection;
            }
        }
        throw new NotFoundHttpException();
    }

    private function response(mixed $body): JsonResponse
    {
        return new JsonResponse($body, 200, ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    #[Route('/collections/{id}/preview', methods: ['GET'])]
    public function preview(string $id): JsonResponse
    {
        $workspace = $this->ownWorkspace();
        $collection = $this->collection($workspace, $id);
        $snapshot = $this->sharing->snapshot($workspace, $collection);
        $items = array_map(static fn (array $entry) => ['name' => $entry['name'], 'type' => $entry['type'], 'color' => $entry['color'],
            'photoUrl' => isset($entry['photoId']) ? '/api/items/'.$entry['sourceId'].'/photos/'.$entry['photoId'] : null], $snapshot['items']);

        return $this->response(['digest' => $this->sharing->digest($snapshot), 'name' => $snapshot['name'], 'cover' => $snapshot['cover'],
            'items' => $items, 'excludedCount' => \count($collection['itemIds']) - \count($items)]);
    }

    #[Route('/collections/{id}/audience', methods: ['PUT'])]
    public function audience(string $id, Request $request): JsonResponse
    {
        $workspace = $this->ownWorkspace();
        $collection = $this->collection($workspace, $id);
        $body = $request->toArray();
        if (($body['version'] ?? null) !== $workspace->version) {
            throw new ConflictHttpException('Reload the collection before publishing.');
        }
        $audience = $this->sharing->audience($body['visibility'] ?? null);
        if ('personal' !== $audience) {
            if (true !== ($body['approved'] ?? false)) {
                throw new UnprocessableEntityHttpException('Approve the visible collection presentation.');
            }
            $digest = $this->sharing->digest($this->sharing->snapshot($workspace, $collection));
            if (!\is_string($body['digest'] ?? null) || !hash_equals($digest, $body['digest'])) {
                throw new ConflictHttpException('The collection changed. Review the preview again.');
            }
        }
        $this->sharing->publish($workspace, $collection, $audience);
        foreach ($workspace->collections as &$entry) {
            if ($entry['id'] === $id) {
                $entry['visibility'] = $audience;
            }
        }
        unset($entry);
        $this->em->flush();

        return $this->response(['visibility' => $audience, 'version' => $workspace->version]);
    }

    private function visible(string $id): CollectionPublication
    {
        $viewer = $this->adult();
        $publication = $this->em->find(CollectionPublication::class, $id);
        if (!$publication instanceof CollectionPublication || !$this->sharing->canView($publication, $viewer)) {
            throw new NotFoundHttpException();
        }

        return $publication;
    }

    private function profileOwner(string $username): User
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['username' => strtolower(trim($username)), 'emailVerified' => true]);
        if (!$owner instanceof User) {
            throw new NotFoundHttpException();
        }

        return $owner;
    }

    #[Route('/profiles/{username}', methods: ['GET'])]
    public function profile(string $username): JsonResponse
    {
        $viewer = $this->adult();
        $owner = $this->profileOwner($username);
        $self = $viewer->getId() === $owner->getId();
        $friend = !$self && null !== $this->em->getRepository(FriendRequest::class)->findOneBy([
            'pairKey' => FriendRequest::pairKey($viewer, $owner), 'status' => FriendRequestStatus::Accepted,
        ]);
        $following = !$self && null !== $this->em->getRepository(Follow::class)->findOneBy(['follower' => $viewer, 'followed' => $owner]);

        $identity = $this->identities->find($owner);

        // Read approved snapshots only; existing private bios never become public implicitly.
        return $this->response(['profile' => ['username' => $owner->getUsername(), 'self' => $self, 'friend' => $friend, 'following' => $following,
            'bio' => null !== $identity && $identity->bioShared ? $identity->bio : '',
            'connections' => $this->identities->counts($owner),
            ...$this->identities->presentation($owner)]]);
    }

    #[Route('/profiles/{username}/photo', methods: ['GET'])]
    public function profilePhoto(string $username): Response
    {
        $this->adult();
        $identity = $this->identities->find($this->profileOwner($username));
        if (null === $identity || '' === $identity->photo) {
            throw new NotFoundHttpException();
        }

        return new Response((string) base64_decode($identity->photo, true), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer']);
    }

    #[Route('/collection-publications', methods: ['GET'])]
    public function discover(Request $request): JsonResponse
    {
        $viewer = $this->adult();
        $page = max(1, $request->query->getInt('page', 1));
        $scope = $request->query->getString('scope', 'community');
        if (!\in_array($scope, ['community', 'following'], true)) {
            throw new UnprocessableEntityHttpException('Invalid feed scope.');
        }
        // Apply audience filtering before pagination: private metadata never enters the feed.
        $query = $this->em->getRepository(CollectionPublication::class)->createQueryBuilder('p')
            ->join('p.workspace', 'w')->join('w.owner', 'u')
            ->where('u.emailVerified = true')
            ->andWhere("p.audience = 'app' OR w.owner = :viewer OR (p.audience IN ('friends', 'followers') AND EXISTS (SELECT fr.id FROM App\\Entity\\FriendRequest fr WHERE ((fr.sender = u AND fr.recipient = :viewer) OR (fr.recipient = u AND fr.sender = :viewer)) AND fr.status = :accepted)) OR (p.audience = 'followers' AND EXISTS (SELECT f.id FROM App\\Entity\\Follow f WHERE f.follower = :viewer AND f.followed = u))")
            ->setParameter('viewer', $viewer)->setParameter('accepted', 'accepted');
        if ('following' === $scope) {
            $query->andWhere('w.owner != :viewer')
                ->andWhere('EXISTS (SELECT followed.id FROM App\\Entity\\Follow followed WHERE followed.follower = :viewer AND followed.followed = u) OR EXISTS (SELECT friendship.id FROM App\\Entity\\FriendRequest friendship WHERE ((friendship.sender = u AND friendship.recipient = :viewer) OR (friendship.recipient = u AND friendship.sender = :viewer)) AND friendship.status = :accepted)');
        }
        $username = $request->query->getString('owner');
        if ('' !== $username) {
            $query->andWhere('w.owner = :owner')->setParameter('owner', $this->profileOwner($username));
        }
        $query->orderBy('p.updatedAt', 'DESC')->addOrderBy('p.id', 'ASC')->setFirstResult(($page - 1) * 24)->setMaxResults(25);
        /** @var list<CollectionPublication> $records */
        $records = $query->getQuery()->getResult();
        $hasMore = \count($records) > 24;
        $collections = array_map(fn (CollectionPublication $record) => $this->sharing->projection($record), array_slice($records, 0, 24));

        return $this->response(['collections' => $collections, 'page' => $page, 'hasMore' => $hasMore]);
    }

    #[Route('/collection-publications/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        return $this->response(['collection' => $this->sharing->projection($this->visible($id))]);
    }

    #[Route('/collection-publications/{id}/photos/{key}', methods: ['GET'], requirements: ['key' => '\d+'])]
    public function photo(string $id, int $key): Response
    {
        $publication = $this->visible($id);
        $entry = $publication->presentation['items'][$key] ?? null;
        $item = \is_array($entry) ? $this->em->find(Item::class, $entry['sourceId']) : null;
        $photo = \is_array($entry) && isset($entry['photoId']) ? $this->em->find(ItemPhoto::class, $entry['photoId']) : null;
        if (!$item instanceof Item || !$this->sharing->eligible($item, $publication->workspace) || !$photo instanceof ItemPhoto || $photo->getItem()->getId() !== $item->getId()) {
            throw new NotFoundHttpException();
        }
        $path = $this->projectDir.'/var/uploads/'.$photo->getPath();
        $bytes = is_file($path) ? file_get_contents($path) : false;
        $image = false !== $bytes ? @imagecreatefromstring($bytes) : false;
        if (false === $image) {
            throw new NotFoundHttpException();
        }
        ob_start();
        imagepng($image);
        $safe = (string) ob_get_clean();
        imagedestroy($image);

        return new Response($safe, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
