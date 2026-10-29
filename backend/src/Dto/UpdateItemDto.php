<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateItemDto
{
    /** @var list<string> fields present in the request (PATCH semantics) */
    private array $present = [];

    /** @param list<string> $present */
    public function __construct(
        #[Assert\Length(min: 1, max: 255)]
        public ?string $name = null,
        #[Assert\Length(max: 50)]
        public ?string $type = null,
        #[Assert\Length(max: 120)]
        public ?string $category = null,
        #[Assert\Range(min: 1)]
        public ?int $roomId = null,
        #[Assert\Range(min: 1)]
        public ?int $storageLocationId = null,
        #[Assert\Length(max: 120)]
        public ?string $brand = null,
        #[Assert\Length(max: 60)]
        public ?string $color = null,
        #[Assert\Length(max: 30)]
        public ?string $size = null,
        #[Assert\Length(max: 120)]
        public ?string $material = null,
        #[Assert\Choice(choices: ['neuf', 'comme_neuf', 'tres_bon', 'bon', 'a_reparer', 'abime'])]
        public ?string $condition = null,
        #[Assert\Choice(choices: ['disponible', 'loue', 'vendu', 'donne', 'archive'])]
        public ?string $status = null,
        public ?string $memory = null,
        public ?string $notes = null,
        array $present = [],
    ) {
        $this->present = $present;
    }

    public function has(string $field): bool
    {
        return \in_array($field, $this->present, true);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $fields = ['name', 'type', 'category', 'roomId', 'storageLocationId', 'brand', 'color', 'size', 'material', 'condition', 'status', 'memory', 'notes'];

        return new self(
            name: self::stringOrNull($data['name'] ?? null),
            type: self::stringOrNull($data['type'] ?? null),
            category: self::stringOrNull($data['category'] ?? null),
            roomId: self::intOrNull($data['roomId'] ?? null),
            storageLocationId: self::intOrNull($data['storageLocationId'] ?? null),
            brand: self::stringOrNull($data['brand'] ?? null),
            color: self::stringOrNull($data['color'] ?? null),
            size: self::stringOrNull($data['size'] ?? null),
            material: self::stringOrNull($data['material'] ?? null),
            condition: self::stringOrNull($data['condition'] ?? null),
            status: self::stringOrNull($data['status'] ?? null),
            memory: self::stringOrNull($data['memory'] ?? null),
            notes: self::stringOrNull($data['notes'] ?? null),
            present: array_values(array_intersect($fields, array_keys($data))),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return null === $value ? null : (\is_int($value) ? $value : 0);
    }
}
