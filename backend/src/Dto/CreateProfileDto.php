<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateProfileDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Role is required.')]
        #[Assert\Choice(choices: ['child', 'partner'], message: 'Role must be "child" or "partner".')]
        public ?string $role = null,
        #[Assert\NotBlank(message: 'Name is required.')]
        #[Assert\Length(min: 1, max: 100)]
        public ?string $name = null,
        #[Assert\Length(max: 6)]
        public ?string $pin = null,
        #[Assert\Length(max: 180)]
        public ?string $email = null,
        #[Assert\Length(max: 255)]
        public ?string $password = null,
        #[Assert\Length(max: 7)]
        public ?string $avatarColor = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            role: self::stringOrNull($data['role'] ?? null),
            name: self::stringOrNull($data['name'] ?? null),
            pin: self::stringOrNull($data['pin'] ?? null),
            email: self::stringOrNull($data['email'] ?? null),
            password: self::stringOrNull($data['password'] ?? null),
            avatarColor: self::stringOrNull($data['avatarColor'] ?? null),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
