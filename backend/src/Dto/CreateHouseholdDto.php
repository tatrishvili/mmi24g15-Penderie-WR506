<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateHouseholdDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Household name is required.')]
        #[Assert\Length(min: 2, max: 255)]
        public ?string $householdName = null,
        #[Assert\NotBlank(message: 'First name is required.')]
        #[Assert\Length(min: 1, max: 100)]
        public ?string $firstName = null,
        #[Assert\Length(max: 120)]
        public ?string $placeName = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            householdName: self::stringOrNull($data['householdName'] ?? null),
            firstName: self::stringOrNull($data['firstName'] ?? null),
            placeName: self::stringOrNull($data['placeName'] ?? null),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
