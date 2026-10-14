<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Place;
use App\Entity\Room;
use App\Entity\StorageLocation;
use App\Service\CurrentActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class PlaceController extends AbstractController
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/places', name: 'api_places_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $household = $this->actor->requireHousehold();

        return new JsonResponse([
            'places' => array_map(
                fn (Place $place): array => $this->placePayload($place),
                $this->em->getRepository(Place::class)->findBy(['household' => $household], ['id' => 'ASC']),
            ),
        ]);
    }

    #[Route('/places', name: 'api_places_create', methods: ['POST'])]
    public function createPlace(Request $request): JsonResponse
    {
        $household = $this->actor->requireHousehold();
        $name = $this->nameFromRequest($request);
        $place = new Place($household, $name);
        $this->em->persist($place);
        $this->em->flush();

        return new JsonResponse(['place' => $this->placePayload($place)], 201);
    }

    #[Route('/places/{placeId}/rooms', name: 'api_places_create_room', methods: ['POST'], requirements: ['placeId' => '\d+'])]
    public function createRoom(int $placeId, Request $request): JsonResponse
    {
        $household = $this->actor->requireHousehold();
        $place = $this->em->getRepository(Place::class)->findOneBy(['id' => $placeId, 'household' => $household]);

        if (!$place instanceof Place) {
            throw new NotFoundHttpException('Place not found.');
        }

        $room = new Room($household, $place, $this->nameFromRequest($request));
        $this->em->persist($room);
        $this->em->flush();

        return new JsonResponse(['room' => $this->roomPayload($room)], 201);
    }

    #[Route('/rooms/{roomId}/storage-locations', name: 'api_rooms_create_storage', methods: ['POST'], requirements: ['roomId' => '\d+'])]
    public function createStorageLocation(int $roomId, Request $request): JsonResponse
    {
        $household = $this->actor->requireHousehold();
        $room = $this->em->getRepository(Room::class)->findOneBy(['id' => $roomId, 'household' => $household]);

        if (!$room instanceof Room) {
            throw new NotFoundHttpException('Room not found.');
        }

        $data = $this->jsonBody($request);
        $name = $this->validatedName($data['name'] ?? null);
        $parentId = $data['parentId'] ?? null;
        $parent = null;

        if (null !== $parentId) {
            if (!\is_int($parentId) || $parentId < 1) {
                throw new UnprocessableEntityHttpException('parentId must be a positive integer.');
            }

            $parent = $this->em->getRepository(StorageLocation::class)->findOneBy([
                'id' => $parentId,
                'room' => $room,
                'household' => $household,
            ]);

            if (!$parent instanceof StorageLocation) {
                throw new NotFoundHttpException('Parent storage location not found in this room.');
            }
        }

        $storage = new StorageLocation($household, $room, $name, $parent);
        $this->em->persist($storage);
        $this->em->flush();

        return new JsonResponse(['storageLocation' => $this->storagePayload($storage)], 201);
    }

    /** @return array<string, mixed> */
    private function placePayload(Place $place): array
    {
        $rooms = $this->em->getRepository(Room::class)->findBy(['place' => $place], ['id' => 'ASC']);

        return [
            'id' => $place->getId(),
            'name' => $place->getName(),
            'isDefault' => $place->isDefault(),
            'rooms' => array_map(fn (Room $room): array => $this->roomPayload($room), $rooms),
        ];
    }

    /** @return array<string, mixed> */
    private function roomPayload(Room $room): array
    {
        $roots = $this->em->getRepository(StorageLocation::class)->findBy([
            'room' => $room,
            'parent' => null,
        ], ['id' => 'ASC']);

        return [
            'id' => $room->getId(),
            'name' => $room->getName(),
            'storageLocations' => array_map(fn (StorageLocation $storage): array => $this->storagePayload($storage, true), $roots),
        ];
    }

    /** @return array<string, mixed> */
    private function storagePayload(StorageLocation $storage, bool $includeChildren = false): array
    {
        $payload = [
            'id' => $storage->getId(),
            'name' => $storage->getName(),
            'parentId' => $storage->getParent()?->getId(),
        ];

        if ($includeChildren) {
            $payload['children'] = array_map(
                fn (StorageLocation $child): array => $this->storagePayload($child, true),
                $this->em->getRepository(StorageLocation::class)->findBy(['parent' => $storage], ['id' => 'ASC']),
            );
        }

        return $payload;
    }

    private function nameFromRequest(Request $request): string
    {
        $data = $this->jsonBody($request);

        return $this->validatedName($data['name'] ?? null);
    }

    private function validatedName(mixed $name): string
    {
        if (!\is_string($name) || '' === trim($name) || mb_strlen(trim($name)) > 120) {
            throw new UnprocessableEntityHttpException('Name must contain between 1 and 120 characters.');
        }

        return trim($name);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException) {
            throw new UnprocessableEntityHttpException('Request body must be valid JSON.');
        }
    }
}
