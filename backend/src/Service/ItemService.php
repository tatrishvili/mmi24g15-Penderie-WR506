<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateItemDto;
use App\Dto\UpdateItemDto;
use App\Entity\Household;
use App\Entity\Item;
use App\Entity\ItemPhoto;
use App\Entity\Room;
use App\Entity\StorageLocation;
use App\Enum\ItemCondition;
use App\Enum\ItemStatus;
use App\Exception\ValidationFailedHttpException;
use App\Security\AuthResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ItemService
{
    private const MAX_PHOTOS_PER_ITEM = 6;
    private const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

    /** @var array<string, string> */
    private const PHOTO_EXTENSIONS = [
        'jpeg' => 'jpg',
        'png' => 'png',
        'webp' => 'webp',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private string $uploadDir,
    ) {
    }

    /**
     * Finds an item of the ACTIVE household. Unknown ids and foreign
     * households both answer 404 (no existence leak = IDOR-safe).
     */
    public function findHouseholdItem(CurrentActor $actor, int $id): Item
    {
        $household = $actor->requireHousehold();

        $item = $this->em->getRepository(Item::class)->findOneBy(['id' => $id, 'household' => $household]);

        if (!$item instanceof Item) {
            throw new NotFoundHttpException('Item not found.');
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(CurrentActor $actor, CreateItemDto $dto): array
    {
        $violations = $this->validator->validate($dto);

        if (\count($violations) > 0) {
            throw ValidationFailedHttpException::fromViolations($violations);
        }

        $household = $actor->requireHousehold();
        $owner = $actor->profile() ?? $actor->requireAdultProfile();

        $item = new Item($household, $owner, trim((string) $dto->name));
        $this->applyLocation($item, $household, $dto->roomId, $dto->storageLocationId);
        $item->setType(self::cleanOrNull($dto->type));
        $item->setCategory(self::cleanOrNull($dto->category));
        $this->apply($item, $dto->brand, $dto->color, $dto->size, $dto->material, $dto->condition, $dto->status, $dto->memory, $dto->notes);
        $this->em->persist($item);
        $this->em->flush();

        if (null !== $dto->photos) {
            $this->addPhotos($item, $dto->photos);
            $this->em->flush();
        }

        return ['item' => self::itemPayload($item)];
    }

    /**
     * @return array<string, mixed>
     */
    public function update(Item $item, UpdateItemDto $dto): array
    {
        $violations = $this->validator->validate($dto);

        if (\count($violations) > 0) {
            throw ValidationFailedHttpException::fromViolations($violations);
        }

        if ($dto->has('name')) {
            $item->setName(trim((string) $dto->name));
        }

        if ($dto->has('type')) {
            $item->setType(self::cleanOrNull($dto->type));
        }

        if ($dto->has('category')) {
            $item->setCategory(self::cleanOrNull($dto->category));
        }

        if ($dto->has('roomId') || $dto->has('storageLocationId')) {
            $currentRoomId = $item->getRoom()?->getId();
            $roomId = $dto->has('roomId')
                ? $dto->roomId
                : ($dto->has('storageLocationId') && null !== $dto->storageLocationId
                    ? null
                    : $currentRoomId);
            $roomChanged = $dto->has('roomId') && $dto->roomId !== $currentRoomId;
            $storageLocationId = $dto->has('storageLocationId')
                ? $dto->storageLocationId
                : ($roomChanged ? null : $item->getStorageLocation()?->getId());

            $this->applyLocation(
                $item,
                $item->getHousehold(),
                $roomId,
                $storageLocationId,
            );
        }

        if ($dto->has('brand')) {
            $item->setBrand(self::cleanOrNull($dto->brand));
        }

        if ($dto->has('color')) {
            $item->setColor(self::cleanOrNull($dto->color));
        }

        if ($dto->has('size')) {
            $item->setSize(self::cleanOrNull($dto->size));
        }

        if ($dto->has('material')) {
            $item->setMaterial(self::cleanOrNull($dto->material));
        }

        if ($dto->has('condition') && null !== $dto->condition) {
            $condition = ItemCondition::tryFrom($dto->condition);

            if (null === $condition) {
                throw new UnprocessableEntityHttpException('Invalid condition.');
            }

            $item->setCondition($condition);
        }

        if ($dto->has('status') && null !== $dto->status) {
            $status = ItemStatus::tryFrom($dto->status);

            if (null === $status) {
                throw new UnprocessableEntityHttpException('Invalid status.');
            }

            $item->setStatus($status);
        }

        if ($dto->has('memory')) {
            $item->setMemory(self::cleanOrNull($dto->memory));
        }

        if ($dto->has('notes')) {
            $item->setNotes(self::cleanOrNull($dto->notes));
        }

        $item->touch();
        $this->em->flush();

        return ['item' => self::itemPayload($item)];
    }

    public function delete(Item $item): void
    {
        $paths = [];

        foreach ($item->getPhotos() as $photo) {
            $paths[] = $photo->getPath();
        }

        $this->em->remove($item);
        $this->em->flush();

        foreach ($paths as $path) {
            $this->deleteFile($path);
        }
    }

    /**
     * @param list<mixed> $photos base64 data URLs
     *
     * @return array<string, mixed>
     */
    public function addPhotos(Item $item, array $photos): array
    {
        if ([] === $photos) {
            throw new UnprocessableEntityHttpException('No photos provided.');
        }

        $existing = \count($item->getPhotos());

        if ($existing + \count($photos) > self::MAX_PHOTOS_PER_ITEM) {
            throw new UnprocessableEntityHttpException('Too many photos (6 maximum per item).');
        }

        $created = [];
        $sortOrder = 0;

        foreach ($item->getPhotos() as $photo) {
            $sortOrder = max($sortOrder, $photo->getSortOrder() + 1);
        }

        $hasPrimary = false;

        foreach ($item->getPhotos() as $photo) {
            if ($photo->isPrimary()) {
                $hasPrimary = true;

                break;
            }
        }

        foreach ($photos as $index => $dataUrl) {
            if (!\is_string($dataUrl)) {
                throw new UnprocessableEntityHttpException(\sprintf('Photo %d must be a base64 data URL.', $index + 1));
            }

            $path = $this->storePhoto($dataUrl, $index);
            $primary = !$hasPrimary && 0 === $index;
            $photo = new ItemPhoto($item, $path, $primary, $sortOrder + $index);
            $this->em->persist($photo);
            $created[] = $photo;

            if ($primary) {
                $hasPrimary = true;
            }
        }

        $this->em->flush();

        return ['photos' => array_map(self::photoPayload(...), $created)];
    }

    public function removePhoto(Item $item, int $photoId): void
    {
        $photo = $this->em->getRepository(ItemPhoto::class)->findOneBy(['id' => $photoId, 'item' => $item]);

        if (!$photo instanceof ItemPhoto) {
            throw new NotFoundHttpException('Photo not found.');
        }

        $wasPrimary = $photo->isPrimary();
        $path = $photo->getPath();
        $this->em->remove($photo);
        $this->em->flush();
        $this->deleteFile($path);

        if ($wasPrimary) {
            $next = $this->em->getRepository(ItemPhoto::class)->findOneBy(['item' => $item], ['sortOrder' => 'ASC']);

            if ($next instanceof ItemPhoto) {
                $next->setPrimary(true);
                $this->em->flush();
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemPayload(Item $item): array
    {
        return [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'type' => $item->getType(),
            'category' => $item->getCategory(),
            'room' => null === $item->getRoom() ? null : [
                'id' => $item->getRoom()->getId(),
                'name' => $item->getRoom()->getName(),
                'place' => [
                    'id' => $item->getRoom()->getPlace()->getId(),
                    'name' => $item->getRoom()->getPlace()->getName(),
                ],
            ],
            'storageLocation' => null === $item->getStorageLocation() ? null : [
                'id' => $item->getStorageLocation()->getId(),
                'name' => $item->getStorageLocation()->getName(),
                'path' => self::storagePath($item->getStorageLocation()),
            ],
            'brand' => $item->getBrand(),
            'color' => $item->getColor(),
            'size' => $item->getSize(),
            'material' => $item->getMaterial(),
            'condition' => $item->getCondition()->value,
            'status' => $item->getStatus()->value,
            'qrToken' => $item->getQrToken(),
            'memory' => $item->getMemory(),
            'notes' => $item->getNotes(),
            'ownerProfile' => AuthResponse::profilePayload($item->getOwnerProfile()),
            'photos' => array_map(self::photoPayload(...), [...$item->getPhotos()]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function photoPayload(ItemPhoto $photo): array
    {
        return [
            'id' => $photo->getId(),
            'url' => \sprintf('/api/items/%d/photos/%d', $photo->getItem()->getId(), $photo->getId()),
            'isPrimary' => $photo->isPrimary(),
        ];
    }

    private function apply(
        Item $item,
        ?string $brand,
        ?string $color,
        ?string $size,
        ?string $material,
        ?string $condition,
        ?string $status,
        ?string $memory,
        ?string $notes,
    ): void {
        $item->setBrand(self::cleanOrNull($brand));
        $item->setColor(self::cleanOrNull($color));
        $item->setSize(self::cleanOrNull($size));
        $item->setMaterial(self::cleanOrNull($material));
        $item->setMemory(self::cleanOrNull($memory));
        $item->setNotes(self::cleanOrNull($notes));

        if (null !== $condition) {
            $item->setCondition(ItemCondition::from($condition));
        }

        if (null !== $status) {
            $item->setStatus(ItemStatus::from($status));
        }
    }

    private static function cleanOrNull(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $clean = trim($value);

        return '' === $clean ? null : $clean;
    }

    private function applyLocation(Item $item, Household $household, ?int $roomId, ?int $storageLocationId): void
    {
        $room = null;
        $storage = null;

        if (null !== $storageLocationId) {
            $storage = $this->em->getRepository(StorageLocation::class)->findOneBy([
                'id' => $storageLocationId,
                'household' => $household,
            ]);

            if (!$storage instanceof StorageLocation) {
                throw new UnprocessableEntityHttpException('Storage location is not available in this space.');
            }

            $room = $storage->getRoom();

            if (null !== $roomId && $room->getId() !== $roomId) {
                throw new UnprocessableEntityHttpException('The selected storage does not belong to the selected room.');
            }
        } elseif (null !== $roomId) {
            $room = $this->em->getRepository(Room::class)->findOneBy([
                'id' => $roomId,
                'household' => $household,
            ]);

            if (!$room instanceof Room) {
                throw new UnprocessableEntityHttpException('Room is not available in this space.');
            }
        }

        $item->setRoom($room);
        $item->setStorageLocation($storage);
    }

    /**
     * @return list<string>
     */
    private static function storagePath(StorageLocation $storage): array
    {
        $path = [];

        for ($location = $storage; null !== $location; $location = $location->getParent()) {
            $path[] = $location->getName();
        }

        return array_reverse($path);
    }

    private function storePhoto(string $dataUrl, int $index): string
    {
        if (1 !== preg_match('#^data:image/(jpeg|png|webp);base64,#', $dataUrl, $matches)) {
            throw new UnprocessableEntityHttpException(\sprintf('Photo %d must be a jpeg, png or webp data URL.', $index + 1));
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if (false === $binary || \strlen($binary) > self::MAX_PHOTO_BYTES) {
            throw new UnprocessableEntityHttpException(\sprintf('Photo %d is invalid or too large (5 MB max).', $index + 1));
        }

        if (false === @getimagesizefromstring($binary)) {
            throw new UnprocessableEntityHttpException(\sprintf('Photo %d is not a readable image.', $index + 1));
        }

        $dir = rtrim($this->uploadDir, '/').'/items';

        if (!is_dir($dir) && false === @mkdir($dir, 0755, true)) {
            throw new UnprocessableEntityHttpException('Photo storage is unavailable.');
        }

        $filename = bin2hex(random_bytes(12)).'.'.self::PHOTO_EXTENSIONS[$matches[1]];

        if (false === @file_put_contents($dir.'/'.$filename, $binary)) {
            throw new UnprocessableEntityHttpException('Photo storage is unavailable.');
        }

        return 'items/'.$filename;
    }

    private function deleteFile(string $path): void
    {
        if (!str_starts_with($path, 'items/')) {
            return;
        }

        $file = rtrim($this->uploadDir, '/').'/items/'.substr($path, \strlen('items/'));

        if (is_file($file)) {
            @unlink($file);
        }
    }
}
