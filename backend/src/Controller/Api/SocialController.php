<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Item;
use App\Entity\ItemShare;
use App\Entity\Loan;
use App\Entity\SocialWorkspace;
use App\Entity\User;
use App\Enum\ItemStatus;
use App\Service\CurrentActor;
use App\Service\ItemService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/social')]
final class SocialController
{
    public function __construct(private CurrentActor $actor, private EntityManagerInterface $em, private ItemService $items,
        #[Autowire(service: 'limiter.sharing')]
        private RateLimiterFactory $sharingLimiter,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    private function adult(): User
    {
        $user = $this->actor->requireUser();
        $profile = $this->actor->profile();
        if (!$user->isEmailVerified() || (null !== $profile && !$profile->isAdult())) {
            throw new AccessDeniedHttpException('Un compte adulte vérifié est nécessaire.');
        }
        $this->actor->requireAdultProfile();

        return $user;
    }

    private function workspace(): SocialWorkspace
    {
        $owner = $this->adult();
        $household = $this->actor->requireHousehold();
        $workspace = $this->em->getRepository(SocialWorkspace::class)->findOneBy(['owner' => $owner, 'household' => $household]);
        if (!$workspace instanceof SocialWorkspace) {
            $workspace = new SocialWorkspace($owner, $household);
            $this->em->persist($workspace);
        }

        return $workspace;
    }

    private function ownedItem(int $id): Item
    {
        $user = $this->adult();
        $item = $this->items->findHouseholdItem($this->actor, $id);
        if ($item->getOwnerProfile()->getUser()?->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('Seul le propriétaire adulte peut partager cet article. Le partage des articles d’enfants nécessite une autorisation parentale dédiée.');
        }

        return $item;
    }

    private function text(mixed $value, int $max, bool $required = false): string
    {
        if (!\is_string($value) || mb_strlen(trim($value)) > $max || ($required && '' === trim($value))) {
            throw new UnprocessableEntityHttpException('Veuillez vérifier les informations saisies.');
        }

        return trim($value);
    }

    private function image(mixed $value): string
    {
        $value = $this->text($value, 1500000);
        if ('' === $value) {
            return '';
        }
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#s', $value, $matches)) {
            throw new UnprocessableEntityHttpException('Format de photo invalide.');
        }
        $bytes = base64_decode($matches[2], true);
        if (false === $bytes || false === @getimagesizefromstring($bytes)) {
            throw new UnprocessableEntityHttpException('Photo invalide.');
        }

        return $value;
    }

    #[Route('/workspace', methods: ['GET'])]
    public function getWorkspace(): JsonResponse
    {
        $workspace = $this->workspace();
        $this->em->flush();

        return $this->workspaceResponse($workspace);
    }

    private function workspaceResponse(SocialWorkspace $workspace): JsonResponse
    {
        return new JsonResponse([
            'profile' => array_merge(['displayName' => $workspace->owner->getFirstName(), 'bio' => '', 'photo' => ''], $workspace->profile),
            'username' => $workspace->owner->getUsername(),
            'collections' => $workspace->collections,
            'pins' => $workspace->pins,
            'version' => $workspace->version,
        ]);
    }

    #[Route('/workspace', methods: ['PATCH'])]
    public function updateWorkspace(Request $request): JsonResponse
    {
        $workspace = $this->workspace();
        $body = $request->toArray();
        if (($body['version'] ?? null) !== $workspace->version) {
            throw new ConflictHttpException('Les données ont changé. Rechargez cette page avant de réessayer.');
        }
        if (isset($body['profile']) && \is_array($body['profile'])) {
            $profile = $body['profile'];
            $workspace->profile = [
                'displayName' => $this->text($profile['displayName'] ?? '', 100, true),
                'bio' => $this->text($profile['bio'] ?? '', 500),
                'photo' => $this->image($profile['photo'] ?? ''),
            ];
        }
        if (isset($body['pins'])) {
            if (!\is_array($body['pins']) || \count($body['pins']) > 12) {
                throw new UnprocessableEntityHttpException('Douze épingles maximum.');
            }
            $allowed = array_column($workspace->collections, 'id');
            foreach ($body['pins'] as $pin) {
                if (!\is_string($pin) || !\in_array($pin, $allowed, true)) {
                    throw new UnprocessableEntityHttpException('Collection inconnue.');
                }
            }
            $workspace->pins = array_values(array_unique($body['pins']));
        }
        $this->em->flush();

        return $this->workspaceResponse($workspace);
    }

    #[Route('/collections', methods: ['POST'])]
    public function createCollection(Request $request): JsonResponse
    {
        $workspace = $this->workspace();
        if (\count($workspace->collections) >= 200) {
            throw new UnprocessableEntityHttpException('Limite de collections atteinte.');
        }
        $body = $request->toArray();
        $workspace->collections[] = [
            'id' => bin2hex(random_bytes(12)),
            'name' => $this->text($body['name'] ?? '', 100, true),
            'cover' => $this->image($body['cover'] ?? ''),
            'visibility' => 'personal',
            'itemIds' => [],
        ];
        $this->em->flush();

        return $this->workspaceResponse($workspace);
    }

    #[Route('/collections/{collectionId}/items/{itemId}', methods: ['PUT', 'DELETE'], requirements: ['itemId' => '\d+'])]
    public function collectionItem(string $collectionId, int $itemId, Request $request): JsonResponse
    {
        $workspace = $this->workspace();
        $this->items->findHouseholdItem($this->actor, $itemId);
        $found = false;
        foreach ($workspace->collections as &$collection) {
            if ($collection['id'] === $collectionId) {
                $ids = \is_array($collection['itemIds']) ? $collection['itemIds'] : [];
                $ids = array_values(array_filter($ids, static fn ($id) => $id !== $itemId));
                if ('PUT' === $request->getMethod()) {
                    $ids[] = $itemId;
                }
                $collection['itemIds'] = $ids;
                $found = true;
            }
        }
        unset($collection);
        if (!$found) {
            throw new NotFoundHttpException();
        }
        $this->em->flush();

        return $this->workspaceResponse($workspace);
    }

    #[Route('/items/{id}/visibility', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function visibility(int $id, Request $request): JsonResponse
    {
        $item = $this->ownedItem($id);
        $visibility = $request->toArray()['visibility'] ?? '';
        if (!\in_array($visibility, ['personal', 'private'], true)) {
            throw new UnprocessableEntityHttpException('La publication par audience n’est pas encore disponible. Utilisez un lien révocable pour un partage explicite.');
        }
        $item->setVisibility($visibility);
        if ('personal' === $visibility) {
            foreach ($this->em->getRepository(ItemShare::class)->findBy(['item' => $item]) as $share) {
                $share->revoked = true;
            }
        }
        $this->em->flush();

        return new JsonResponse(['visibility' => $visibility]);
    }

    #[Route('/shares', methods: ['GET'])]
    public function shares(): JsonResponse
    {
        $shares = $this->em->getRepository(ItemShare::class)->findBy(['owner' => $this->adult()], ['id' => 'DESC']);
        $shares = array_filter($shares, fn (ItemShare $share) => $share->item->getHousehold()->getId() === $this->actor->requireHousehold()->getId());

        return new JsonResponse(['shares' => array_values(array_map(static fn (ItemShare $share) => [
            'id' => $share->id, 'itemId' => $share->item->getId(), 'itemName' => $share->item->getName(),
            'revoked' => $share->revoked, 'verifiedOnly' => $share->verifiedOnly,
            'expiresAt' => $share->expiresAt->format(DATE_ATOM), 'createdAt' => $share->createdAt->format(DATE_ATOM),
        ], $shares))]);
    }

    #[Route('/items/{id}/shares', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function createShare(int $id, Request $request): JsonResponse
    {
        $item = $this->ownedItem($id);
        if ('personal' === $item->getVisibility() || ItemStatus::Archive === $item->getStatus()) {
            throw new AccessDeniedHttpException('Un article personnel ou archivé ne peut pas être partagé.');
        }
        $body = $request->toArray();
        if (true !== ($body['approved'] ?? false)) {
            throw new UnprocessableEntityHttpException('Confirmez les informations rendues visibles.');
        }
        $token = bin2hex(random_bytes(32));
        $share = new ItemShare($this->adult(), $item, $token);
        $share->verifiedOnly = true === ($body['verifiedOnly'] ?? false);
        $this->em->persist($share);
        $this->em->flush();

        return new JsonResponse(['id' => $share->id, 'path' => '/partage/'.$token, 'expiresAt' => $share->expiresAt->format(DATE_ATOM)], 201, ['Cache-Control' => 'no-store']);
    }

    #[Route('/shares/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function revokeShare(int $id): JsonResponse
    {
        $share = $this->em->getRepository(ItemShare::class)->findOneBy(['id' => $id, 'owner' => $this->adult()]);
        if (!$share instanceof ItemShare) {
            throw new NotFoundHttpException();
        }
        $share->revoked = true;
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    private function validShare(string $token): ItemShare
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new NotFoundHttpException();
        }
        $share = $this->em->getRepository(ItemShare::class)->findOneBy(['tokenHash' => hash('sha256', $token)]);
        if (!$share instanceof ItemShare || $share->revoked || $share->expiresAt < new \DateTimeImmutable()
            || 'personal' === $share->item->getVisibility() || ItemStatus::Archive === $share->item->getStatus()
            || !$share->item->getOwnerProfile()->isAdult()) {
            throw new NotFoundHttpException('Partage indisponible.');
        }
        if ($share->verifiedOnly && !($this->actor->tokenUser() instanceof User && $this->actor->requireUser()->isEmailVerified())) {
            throw new AccessDeniedHttpException('Connectez-vous avec un compte vérifié pour consulter ce partage.');
        }

        return $share;
    }

    #[Route('/shared/{token}', methods: ['GET'])]
    public function shared(string $token, Request $request): JsonResponse
    {
        if (!$this->sharingLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'Trop de consultations. Réessayez dans une minute.');
        }
        $share = $this->validShare($token);
        $item = $share->item;

        return new JsonResponse(['item' => [
            'name' => $share->presentation['name'] ?? '', 'type' => $share->presentation['type'] ?? null, 'color' => $share->presentation['color'] ?? null,
            'photoUrl' => isset($share->presentation['photoId']) ? '/api/social/shared/'.$token.'/photo' : null,
            'owner' => $share->owner->getUsername(), 'available' => ItemStatus::Disponible === $item->getStatus(),
        ]], 200, ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    #[Route('/shared/{token}/photo', methods: ['GET'])]
    public function sharedPhoto(string $token): \Symfony\Component\HttpFoundation\Response
    {
        $share = $this->validShare($token);
        $photo = $this->em->getRepository(\App\Entity\ItemPhoto::class)->findOneBy(['id' => $share->presentation['photoId'] ?? 0, 'item' => $share->item]);
        if (!$photo instanceof \App\Entity\ItemPhoto) {
            throw new NotFoundHttpException();
        }
        $path = $this->projectDir.'/var/uploads/'.$photo->getPath();
        if (!is_file($path)) {
            throw new NotFoundHttpException();
        }
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg',
        };

        $bytes = file_get_contents($path);
        $image = false !== $bytes ? @imagecreatefromstring($bytes) : false;
        if (false === $image) {
            throw new NotFoundHttpException('Photo indisponible.');
        }
        ob_start();
        match ($mime) {
            'image/png' => imagepng($image), 'image/webp' => imagewebp($image, null, 85), default => imagejpeg($image, null, 85),
        };
        $safeBytes = (string) ob_get_clean();
        imagedestroy($image);

        return new \Symfony\Component\HttpFoundation\Response($safeBytes, 200, [
            'Content-Type' => $mime, 'Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    #[Route('/loans', methods: ['GET'])]
    public function loans(): JsonResponse
    {
        $user = $this->adult();
        $loans = $this->em->getRepository(Loan::class)->createQueryBuilder('l')
            ->where('l.owner = :user OR l.borrower = :user')->setParameter('user', $user)->orderBy('l.id', 'DESC')->getQuery()->getResult();

        return new JsonResponse(['loans' => array_map(fn (Loan $loan) => $this->loanPayload($loan, $user), $loans)]);
    }

    #[Route('/shared/{token}/loans', methods: ['POST'])]
    public function requestLoan(string $token, Request $request): JsonResponse
    {
        $borrower = $this->adult();
        $share = $this->validShare($token);
        if ($borrower->getId() === $share->owner->getId()) {
            throw new UnprocessableEntityHttpException('Vous êtes déjà propriétaire de cet article.');
        }
        if (ItemStatus::Disponible !== $share->item->getStatus()) {
            throw new ConflictHttpException('Cet article n’est pas disponible.');
        }
        $body = $request->toArray();
        $due = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->text($body['dueAt'] ?? '', 10, true));
        if (false === $due || $due->format('Y-m-d') !== $body['dueAt'] || $due < new \DateTimeImmutable('today')) {
            throw new UnprocessableEntityHttpException('Choisissez une date de retour valide.');
        }
        $existing = $this->em->getRepository(Loan::class)->findOneBy(['item' => $share->item, 'borrower' => $borrower, 'status' => 'pending']);
        if ($existing instanceof Loan) {
            throw new ConflictHttpException('Une demande est déjà en attente.');
        }
        $loan = new Loan($share->item, $share->owner, $borrower, $due, $this->text($body['message'] ?? '', 2000));
        $loan->itemName = (string) ($share->presentation['name'] ?? $share->item->getName());
        $this->em->persist($loan);
        $this->em->flush();

        return new JsonResponse(['loan' => $this->loanPayload($loan, $borrower)], 201);
    }

    #[Route('/loans/{id}/{action}', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function transition(int $id, string $action): JsonResponse
    {
        $user = $this->adult();
        $loan = $this->em->getRepository(Loan::class)->find($id);
        if (!$loan instanceof Loan || $loan->owner->getId() !== $user->getId()) {
            throw new NotFoundHttpException();
        }
        $this->em->wrapInTransaction(function () use ($loan, $action): void {
            $item = $loan->item;
            if (null === $item) {
                throw new ConflictHttpException('Article indisponible.');
            }
            if (!$this->em->getConnection()->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
                $this->em->lock($item, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($item);
            }
            $this->em->refresh($loan);
            if ('accept' === $action && 'pending' === $loan->status) {
                if (ItemStatus::Disponible !== $item->getStatus()) {
                    throw new ConflictHttpException('Un autre prêt est déjà actif.');
                }
                $loan->status = 'accepted';
                $loan->acceptedAt = new \DateTimeImmutable();
                $item->setStatus(ItemStatus::Loue);
            } elseif ('decline' === $action && 'pending' === $loan->status) {
                $loan->status = 'declined';
            } elseif ('handover' === $action && 'accepted' === $loan->status) {
                $loan->status = 'active';
                $loan->handedAt = new \DateTimeImmutable();
            } elseif ('return' === $action && \in_array($loan->status, ['accepted', 'active'], true)) {
                $loan->status = 'returned';
                $loan->returnedAt = new \DateTimeImmutable();
                $item->setStatus(ItemStatus::Disponible);
            } else {
                throw new ConflictHttpException('Cette action n’est plus possible.');
            }
        });

        return new JsonResponse(['loan' => $this->loanPayload($loan, $user)]);
    }

    /** @return array<string, mixed> */
    private function loanPayload(Loan $loan, User $viewer): array
    {
        return [
            'id' => $loan->id, 'itemId' => $viewer->getId() === $loan->owner->getId() ? $loan->item?->getId() : null,
            'itemName' => $loan->itemName, 'owner' => $loan->owner->getUsername(), 'borrower' => $loan->borrower->getUsername(),
            'isOwner' => $viewer->getId() === $loan->owner->getId(), 'status' => $loan->status, 'message' => $loan->message,
            'dueAt' => $loan->dueAt->format('Y-m-d'), 'requestedAt' => $loan->requestedAt->format(DATE_ATOM),
            'acceptedAt' => $loan->acceptedAt?->format(DATE_ATOM), 'handedAt' => $loan->handedAt?->format(DATE_ATOM),
            'returnedAt' => $loan->returnedAt?->format(DATE_ATOM),
        ];
    }
}
