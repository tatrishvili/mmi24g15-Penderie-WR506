<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CreateItemDto;
use App\Dto\UpdateItemDto;
use App\Entity\Item;
use App\Entity\ItemPhoto;
use App\Repository\ItemRepository;
use App\Service\CurrentActor;
use App\Service\ItemService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/items')]
final class ItemController extends AbstractController
{
    public function __construct(
        private ItemService $items,
        private CurrentActor $actor,
        private EntityManagerInterface $em,
        private ItemRepository $repository,
    ) {
    }

    #[Route('', name: 'api_items_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $household = $this->actor->requireHousehold();

        $query = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('perPage', 20)));

        $result = $this->repository->searchInHousehold($household, $query, $status, $page, $perPage, $this->actor, $request->query->has('roomId') ? $request->query->getInt('roomId') : null);

        return new JsonResponse([
            'items' => array_map(fn (Item $item): array => ItemService::payloadFor($this->actor, $item), $result['items']),
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    #[Route('', name: 'api_items_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->items->create($this->actor, CreateItemDto::fromArray($this->jsonBody($request)));

        return new JsonResponse($payload, Response::HTTP_CREATED);
    }

    #[Route('/qr/{token}', name: 'api_items_by_qr', methods: ['GET'])]
    public function byQr(string $token): JsonResponse
    {
        $household = $this->actor->requireHousehold();

        $item = $this->em->getRepository(Item::class)->findOneBy(['qrToken' => $token, 'household' => $household]);

        if (!$item instanceof Item || !ItemService::canAccess($this->actor, $item)) {
            throw new NotFoundHttpException('Item not found.');
        }

        return new JsonResponse(['item' => ItemService::payloadFor($this->actor, $item)]);
    }

    #[Route('/{id}', name: 'api_items_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->items->findHouseholdItem($this->actor, $id);

        return new JsonResponse(['item' => ItemService::payloadFor($this->actor, $item)]);
    }

    #[Route('/{id}', name: 'api_items_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->items->findManagedHouseholdItem($this->actor, $id);
        $body = $this->jsonBody($request);
        if (false === $this->actor->profile()?->isAdult() && isset($body['status']) && 'archive' !== $body['status']) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Cette action est réservée aux adultes.');
        }

        $this->items->update($item, UpdateItemDto::fromArray($body), $this->actor);

        return new JsonResponse(['item' => ItemService::payloadFor($this->actor, $item)]);
    }

    #[Route('/{id}', name: 'api_items_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        if (false === $this->actor->profile()?->isAdult()) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('La suppression définitive est réservée aux adultes.');
        }
        $item = $this->items->findManagedHouseholdItem($this->actor, $id);
        $this->items->delete($item);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/photos', name: 'api_items_photos_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addPhotos(int $id, Request $request): JsonResponse
    {
        $item = $this->items->findManagedHouseholdItem($this->actor, $id);
        $body = $this->jsonBody($request);

        if (!\is_array($body['photos'] ?? null)) {
            throw new UnprocessableEntityHttpException('Photos must be a list of base64 data URLs.');
        }

        return new JsonResponse($this->items->addPhotos($item, array_values($body['photos'])), Response::HTTP_CREATED);
    }

    #[Route('/{id}/photos/{photoId}', name: 'api_items_photos_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'photoId' => '\d+'])]
    public function deletePhoto(int $id, int $photoId): JsonResponse
    {
        $item = $this->items->findManagedHouseholdItem($this->actor, $id);
        $this->items->removePhoto($item, $photoId);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/photos/{photoId}', name: 'api_items_photos_show', methods: ['GET'], requirements: ['id' => '\d+', 'photoId' => '\d+'])]
    public function showPhoto(int $id, int $photoId): Response
    {
        $item = $this->items->findHouseholdItem($this->actor, $id);

        $photo = $this->em->getRepository(ItemPhoto::class)->findOneBy(['id' => $photoId, 'item' => $item]);

        if (!$photo instanceof ItemPhoto) {
            throw new NotFoundHttpException('Photo not found.');
        }

        $file = rtrim((string) $this->getParameter('kernel.project_dir'), '/').'/var/uploads/'.$photo->getPath();

        if (!is_file($file)) {
            throw new NotFoundHttpException('Photo not found.');
        }

        $mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        $content = file_get_contents($file);

        if (false === $content) {
            throw new NotFoundHttpException('Photo not found.');
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $mime,
            'Content-Length' => (string) \strlen($content),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(Request $request): array
    {
        try {
            $data = $request->toArray();
        } catch (JsonException) {
            throw new UnprocessableEntityHttpException('Request body must be valid JSON.');
        }

        return $data;
    }
}
