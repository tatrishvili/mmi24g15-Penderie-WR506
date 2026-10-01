<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'E-mail is required.')]
        #[Assert\Email(message: 'E-mail is not valid.')]
        #[Assert\Length(max: 180)]
        public ?string $email = null,
        #[Assert\NotBlank(message: 'Password is required.')]
        #[Assert\Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters.')]
        #[Assert\Length(max: 255)]
        public ?string $password = null,
        #[Assert\NotBlank(message: 'Password confirmation is required.')]
        public ?string $passwordConfirm = null,
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
            email: self::stringOrNull($data['email'] ?? null),
            password: self::stringOrNull($data['password'] ?? null),
            passwordConfirm: self::stringOrNull($data['passwordConfirm'] ?? null),
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
