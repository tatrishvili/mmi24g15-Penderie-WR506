<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\ChildAccount;
use App\Entity\ChildApproval;
use App\Entity\ChildCollection;
use App\Entity\ChildConnection;
use App\Entity\ChildPublication;
use App\Entity\GuardianLink;
use App\Entity\InboxNotification;
use App\Entity\Item;
use App\Entity\ItemPhoto;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\ChildService;
use App\Service\CurrentActor;
use App\Service\FamilyAccess;
use App\Service\ItemService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ChildController extends AbstractController
{
    public function __construct(private CurrentActor $actor, private ChildService $children, private EntityManagerInterface $em)
    {
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException) {
            throw new UnprocessableEntityHttpException('Send a valid JSON object.');
        }
    }

    /** @param array<string, mixed>|list<mixed> $data */
    private function response(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/auth/child-login', methods: ['POST'])]
    public function login(): Response
    {
        throw new \LogicException('Handled by the child authenticator.');
    }

    #[Route('/api/auth/child-password', methods: ['POST'])]
    public function password(Request $request, UserPasswordHasherInterface $hasher, \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface $tokens): Response
    {
        $account = $this->actor->tokenUser();
        if (!$account instanceof ChildAccount) {
            throw new AccessDeniedHttpException('A child session is required.');
        }
        $data = $this->body($request);
        if (!\is_string($data['currentPassword'] ?? null) || !$hasher->isPasswordValid($account, $data['currentPassword'])) {
            throw new AccessDeniedHttpException('The current password is incorrect.');
        }
        $password = ChildService::validatePassword($data['password'] ?? null);
        if ($hasher->isPasswordValid($account, $password)) {
            throw new UnprocessableEntityHttpException('Choose a different password.');
        }
        $this->children->resetPassword($account, $password);
        $account->mustChangePassword = false;
        $account->initialPasswordExpiresAt = null;
        $this->em->flush();
        // Credential changes deliberately revoke this session too. Sign in again
        // with the new password; other devices must not retain old access.
        $tokens->setToken(null);
        $request->getSession()->invalidate();

        return $this->response(['message' => 'Password changed. Sign in with your new password.']);
    }

    #[Route('/api/children', methods: ['GET'])]
    public function list(): Response
    {
        return $this->response(['children' => $this->children->children($this->actor)]);
    }

    #[Route('/api/children', methods: ['POST'])]
    public function create(Request $request): Response
    {
        try {
            $account = $this->children->setup($this->actor, $this->body($request));
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            throw new ConflictHttpException('Choose another child username.');
        }

        return $this->response(['profileId' => $account->profile->getId(), 'username' => $account->username], 201);
    }

    #[Route('/api/children/{id}/credentials', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reset(int $id, Request $request): Response
    {
        $child = $this->children->child($this->actor, $id);
        $this->children->parent($this->actor, $child);
        $account = $this->em->getRepository(ChildAccount::class)->findOneBy(['profile' => $child]);
        if (!$account instanceof ChildAccount) {
            throw new NotFoundHttpException('Activate this child account first.');
        }
        $this->children->resetPassword($account, ChildService::validatePassword($this->body($request)['password'] ?? null));
        $this->em->flush();

        return $this->response(['message' => 'Password reset. Existing sessions are revoked.']);
    }

    #[Route('/api/children/{id}/access', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function access(int $id, Request $request): Response
    {
        $child = $this->children->child($this->actor, $id);
        $this->children->parent($this->actor, $child);
        $account = $this->em->getRepository(ChildAccount::class)->findOneBy(['profile' => $child]);
        if (!$account instanceof ChildAccount) {
            throw new NotFoundHttpException('Child account not found.');
        }
        $enabled = $this->body($request)['enabled'] ?? null;
        if (!\is_bool($enabled)) {
            throw new UnprocessableEntityHttpException('Choose enabled or disabled.');
        }
        $account->enabled = $enabled;
        ++$account->sessionVersion;
        $this->em->flush();

        return $this->response(['enabled' => $enabled]);
    }

    #[Route('/api/children/{id}/parents', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function invite(int $id, Request $request): Response
    {
        $link = $this->children->inviteParent($this->actor, $this->children->child($this->actor, $id), $this->body($request));

        return $this->response(['id' => $link->id], 201);
    }

    #[Route('/api/parent-invitations/{id}/accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function acceptParent(int $id): Response
    {
        $link = $this->em->getRepository(GuardianLink::class)->find($id);
        if (!$link instanceof GuardianLink) {
            throw new NotFoundHttpException('Invitation not found.');
        }
        $this->children->acceptParent($this->actor, $link);

        return $this->response(['accepted' => true]);
    }

    #[Route('/api/parent-invitations/{id}/decline', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function declineParent(int $id): Response
    {
        $user = $this->actor->requireUser();
        $link = $this->em->getRepository(GuardianLink::class)->find($id);
        if (!$link instanceof GuardianLink || $link->parent->getId() !== $user->getId() || 'pending' !== $link->state) {
            throw new NotFoundHttpException('Invitation not found.');
        }
        $link->state = 'declined';
        $this->em->flush();

        return $this->response(['declined' => true]);
    }

    #[Route('/api/parent-links/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function revokeParent(int $id): Response
    {
        $link = $this->em->getRepository(GuardianLink::class)->find($id);
        if (!$link instanceof GuardianLink) {
            throw new NotFoundHttpException('Parent link not found.');
        }
        $parent = $this->children->parent($this->actor, $link->child);
        if ($link->invitedBy->getId() === $link->parent->getId() || $link->invitedBy->getId() !== $parent->getId()) {
            throw new AccessDeniedHttpException('Only the inviting parent can revoke this additional parent.');
        }
        $link->state = 'revoked';
        foreach ($this->em->getRepository(Item::class)->findBy(['ownerProfile' => $link->child, 'familyApprovedBy' => $link->parent]) as $item) {
            $item->familyDigest = null;
            $item->familyViewerIds = [];
            $item->familyAudience = 'owner';
            $item->touch();
        }
        foreach ($this->em->getRepository(ChildApproval::class)->findBy(['child' => $link->child, 'decidedBy' => $link->parent]) as $approval) {
            $approval->status = 'withdrawn';
        }
        foreach ($this->em->getRepository(ChildConnection::class)->findAll() as $connection) {
            if (($connection->requester->getId() === $link->child->getId() && $connection->requesterApprovedBy?->getId() === $link->parent->getId())
                || ($connection->recipientChild?->getId() === $link->child->getId() && $connection->recipientApprovedBy?->getId() === $link->parent->getId())) {
                $connection->state = 'revoked';
            }
        }

        $account = $this->em->getRepository(ChildAccount::class)->findOneBy(['profile' => $link->child]);
        if ($account instanceof ChildAccount) {
            ++$account->sessionVersion;
        }
        $this->em->flush();

        return $this->response(['revoked' => true]);
    }

    #[Route('/api/family/members', methods: ['GET'])]
    public function members(): Response
    {
        $profiles = $this->em->getRepository(Profile::class)->findBy(['household' => $this->actor->requireHousehold()], ['id' => 'ASC']);

        return $this->response(['members' => array_map(static fn (Profile $profile): array => ['id' => $profile->getId(), 'name' => $profile->getName(), 'role' => $profile->getRole()->value], $profiles)]);
    }

    #[Route('/api/items/{id}/family-audience', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function audience(int $id, Request $request, ItemService $items): Response
    {
        $item = $items->findManagedHouseholdItem($this->actor, $id);
        $approval = $this->children->setAudience($this->actor, $item, $this->body($request));

        return $this->response(['item' => ItemService::payloadFor($this->actor, $item), 'approval' => null !== $approval ? $this->children->approvalPayload($approval) : null], null !== $approval ? 202 : 200);
    }

    #[Route('/api/child-collections', methods: ['GET'])]
    public function collections(Request $request): Response
    {
        return $this->response(['collections' => $this->children->collections($this->actor, $request->query->has('childId') ? $request->query->getInt('childId') : null)]);
    }

    #[Route('/api/child-collections', methods: ['POST'])]
    public function createCollection(Request $request): Response
    {
        return $this->response(['collection' => $this->children->collectionPayload($this->children->saveCollection($this->actor, $this->body($request)))], 201);
    }

    #[Route('/api/child-collections/{id}', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function updateCollection(int $id, Request $request): Response
    {
        $collection = $this->em->getRepository(ChildCollection::class)->find($id);
        if (!$collection instanceof ChildCollection) {
            throw new NotFoundHttpException('Collection not found.');
        }

        return $this->response(['collection' => $this->children->collectionPayload($this->children->saveCollection($this->actor, $this->body($request), $collection))]);
    }

    #[Route('/api/child-connections', methods: ['GET'])]
    public function connections(): Response
    {
        $connections = array_filter($this->em->getRepository(ChildConnection::class)->findAll(), fn (ChildConnection $connection): bool => $this->children->maySeeConnection($this->actor, $connection));

        return $this->response(['connections' => array_values(array_map($this->children->connectionPayload(...), $connections))]);
    }

    #[Route('/api/child-connections', methods: ['POST'])]
    public function createConnection(Request $request): Response
    {
        return $this->response(['connection' => $this->children->connectionPayload($this->children->requestConnection($this->actor, $this->body($request)))], 201);
    }

    #[Route('/api/child-connections/{id}', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function connectionDecision(int $id, Request $request): Response
    {
        $connection = $this->em->getRepository(ChildConnection::class)->find($id);
        if (!$connection instanceof ChildConnection) {
            throw new NotFoundHttpException('Connection not found.');
        }
        $this->children->decideConnection($this->actor, $connection, ChildService::text($this->body($request)['action'] ?? null, 15));

        return $this->response(['connection' => $this->children->connectionPayload($connection)]);
    }

    #[Route('/api/child-approvals', methods: ['POST'])]
    public function requestApproval(Request $request): Response
    {
        $approval = $this->children->requestPublication($this->actor, $this->body($request));

        return $this->response(['approval' => $this->children->approvalPayload($approval)], 202);
    }

    #[Route('/api/child-approvals/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function approval(int $id): Response
    {
        $approval = $this->findApproval($id);

        return $this->response(['approval' => $this->children->approvalPayload($approval)]);
    }

    private function findApproval(int $id): ChildApproval
    {
        $approval = $this->em->getRepository(ChildApproval::class)->find($id);
        if (!$approval instanceof ChildApproval) {
            throw new NotFoundHttpException('Approval not found.');
        }
        if ('child:'.$approval->child->getId() !== $this->children->identity($this->actor)) {
            $this->children->parent($this->actor, $approval->child);
        }

        return $approval;
    }

    #[Route('/api/child-approvals/{id}/decision', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function decide(int $id, Request $request): Response
    {
        $approval = $this->findApproval($id);
        $data = $this->body($request);
        if (!\is_bool($data['approve'] ?? null) || !\is_int($data['version'] ?? null)) {
            throw new UnprocessableEntityHttpException('Send a decision and the reviewed version.');
        }
        try {
            $this->children->decide($this->actor, $approval, $data['approve'], $data['version']);
        } catch (\Doctrine\ORM\OptimisticLockException) {
            throw new ConflictHttpException('This request was already handled. Reload your notifications.');
        }

        return $this->response(['approval' => $this->children->approvalPayload($approval)]);
    }

    #[Route('/api/child-approvals/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function withdrawApproval(int $id): Response
    {
        $approval = $this->findApproval($id);
        $approval->status = 'withdrawn';
        if ('family_item' === $approval->kind) {
            $item = $this->em->getRepository(Item::class)->find($approval->proposal['itemId']);
            if ($item instanceof Item && $item->familyDigest === hash('sha256', json_encode($approval->proposal['preview'], JSON_THROW_ON_ERROR))) {
                $item->touch();
                $item->familyDigest = null;
                $item->familyViewerIds = [];
                $item->familyAudience = 'owner';
            }
        }
        $this->em->flush();

        return $this->response(['withdrawn' => true]);
    }

    #[Route('/api/notifications', methods: ['GET'])]
    public function notifications(Request $request): Response
    {
        $recipient = $this->children->identity($this->actor);
        $rows = $this->em->getRepository(InboxNotification::class)->findBy(['recipient' => $recipient], ['id' => 'DESC']);
        $before = $request->query->getInt('before', PHP_INT_MAX);
        $page = array_slice(array_values(array_filter($rows, static fn (InboxNotification $row): bool => $row->id < $before)), 0, 30);
        $notifications = [];
        foreach ($page as $row) {
            $detail = null;
            try {
                if (\in_array($row->kind, ['approval', 'decision'], true)) {
                    $detail = $this->children->approvalPayload($this->findApproval($row->referenceId));
                    $detail['canDecide'] = $this->actor->tokenUser() instanceof User && !$this->actor->isRestrictedProfile() && 'pending' === $detail['status'];
                } elseif ('parent_invitation' === $row->kind) {
                    $link = $this->em->getRepository(GuardianLink::class)->find($row->referenceId);
                    if ($link instanceof GuardianLink && 'adult:'.$link->parent->getId() === $recipient) {
                        $detail = ['id' => $link->id, 'childName' => $link->child->getName(), 'relationship' => $link->relationship, 'state' => $link->state];
                    }
                } elseif ('connection' === $row->kind) {
                    $connection = $this->em->getRepository(ChildConnection::class)->find($row->referenceId);
                    if ($connection instanceof ChildConnection && $this->children->maySeeConnection($this->actor, $connection)) {
                        $detail = $this->children->connectionPayload($connection);
                    }
                }
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                $detail = null;
            }
            $notifications[] = ['id' => $row->id, 'kind' => $row->kind, 'referenceId' => $row->referenceId, 'read' => null !== $row->readAt,
                'createdAt' => $row->createdAt->format(DATE_ATOM), 'detail' => $detail];
        }

        return $this->response(['notifications' => $notifications, 'unread' => \count(array_filter($rows, static fn (InboxNotification $row): bool => null === $row->readAt)),
            'nextBefore' => 30 === \count($page) ? $page[29]->id : null]);
    }

    #[Route('/api/notifications/{id}/read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markRead(int $id): Response
    {
        $row = $this->em->getRepository(InboxNotification::class)->findOneBy(['id' => $id, 'recipient' => $this->children->identity($this->actor)]);
        if (!$row instanceof InboxNotification) {
            throw new NotFoundHttpException('Notification not found.');
        }
        $row->readAt ??= new \DateTimeImmutable();
        $this->em->flush();

        return $this->response(['read' => true]);
    }

    /** @return array<string, mixed> */
    private function publicationPayload(ChildPublication $publication): array
    {
        return ['id' => $publication->id, 'canRevoke' => $this->children->identity($this->actor) === 'child:'.$publication->approval->child->getId() || ($this->actor->tokenUser() instanceof User && !$this->actor->isRestrictedProfile() && (new FamilyAccess($this->em))->isGuardian($this->actor->requireUser(), $publication->approval->child)), 'name' => $publication->snapshot['name'], 'kind' => $publication->snapshot['kind'],
            'items' => array_map(static function (array $entry, int $index) use ($publication): array {
                unset($entry['id'], $entry['version']);
                $entry['photos'] = array_map(static fn (array $photo, int $photoIndex): array => ['url' => '/api/child-publications/'.$publication->id.'/photos/'.$index.'/'.$photoIndex], $entry['photos'], array_keys($entry['photos']));

                return $entry;
            }, $publication->snapshot['items'], array_keys($publication->snapshot['items']))];
    }

    #[Route('/api/child-publications', methods: ['GET'])]
    public function publications(): Response
    {
        $rows = array_filter($this->em->getRepository(ChildPublication::class)->findBy([], ['id' => 'DESC']), fn (ChildPublication $publication): bool => $this->children->canReadPublication($this->actor, $publication));

        return $this->response(['publications' => array_values(array_map($this->publicationPayload(...), $rows))]);
    }

    #[Route('/api/child-publications/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function revokePublication(int $id): Response
    {
        $row = $this->em->getRepository(ChildPublication::class)->find($id);
        if (!$row instanceof ChildPublication) {
            throw new NotFoundHttpException('Publication not found.');
        }
        if ($this->children->identity($this->actor) !== 'child:'.$row->approval->child->getId()) {
            $this->children->parent($this->actor, $row->approval->child);
        }
        $row->revoked = true;
        $this->em->flush();

        return $this->response(['revoked' => true]);
    }

    #[Route('/api/child-publications/{id}/photos/{entry}/{photo}', methods: ['GET'], requirements: ['id' => '\d+', 'entry' => '\d+', 'photo' => '\d+'])]
    public function publicationPhoto(int $id, int $entry, int $photo): Response
    {
        $row = $this->em->getRepository(ChildPublication::class)->find($id);
        if (!$row instanceof ChildPublication || !$this->children->canReadPublication($this->actor, $row)) {
            throw new NotFoundHttpException('Photo not found.');
        }
        $item = $row->snapshot['items'][$entry] ?? null;
        if (!\is_array($item) || !isset($item['photos'][$photo]['id'])) {
            throw new NotFoundHttpException('Photo not found.');
        }

        return $this->photoFile((int) $item['id'], (int) $item['photos'][$photo]['id']);
    }

    #[Route('/api/child-approvals/{id}/photos/{entry}/{photo}', methods: ['GET'], requirements: ['id' => '\d+', 'entry' => '\d+', 'photo' => '\d+'])]
    public function approvalPhoto(int $id, int $entry, int $photo): Response
    {
        $approval = $this->findApproval($id);
        $item = 'family_item' === $approval->kind ? ($approval->proposal['preview'] ?? null) : ($approval->proposal['snapshot']['items'][$entry] ?? null);
        if (!\is_array($item) || !isset($item['photos'][$photo]['id'])) {
            throw new NotFoundHttpException('Photo not found.');
        }
        $source = $this->em->getRepository(Item::class)->find($item['id']);
        if (!$source instanceof Item || $source->getOwnerProfile()->getId() !== $approval->child->getId() || FamilyAccess::snapshot($source) !== $item) {
            throw new NotFoundHttpException('Reviewed photo is no longer available.');
        }

        return $this->photoFile((int) $item['id'], (int) $item['photos'][$photo]['id']);
    }

    private function photoFile(int $itemId, int $photoId): Response
    {
        $photo = $this->em->getRepository(ItemPhoto::class)->findOneBy(['id' => $photoId, 'item' => $itemId]);
        if (!$photo instanceof ItemPhoto) {
            throw new NotFoundHttpException('Photo not found.');
        }
        $path = $this->getParameter('kernel.project_dir').'/var/uploads/'.$photo->getPath();
        if (!is_file($path)) {
            throw new NotFoundHttpException('Photo not found.');
        }
        $content = file_get_contents($path);
        if (false === $content) {
            throw new NotFoundHttpException('Photo not found.');
        }
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg',
        };

        return new Response($content, 200, ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
