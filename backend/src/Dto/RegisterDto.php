<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class RegisterDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Username is required.')]
        #[Assert\Length(min: 3, max: 30)]
        #[Assert\Regex(pattern: '/\A[a-z0-9_]+\z/', message: 'Use 3 to 30 lowercase letters, numbers, or underscores.')]
        public ?string $username = null,
        #[Assert\NotBlank(message: 'E-mail is required.')]
        #[Assert\Email(message: 'E-mail is not valid.')]
        #[Assert\Length(max: 180)]
        public ?string $email = null,
        #[Assert\NotBlank(message: 'Date of birth is required.')]
        #[Assert\Date(message: 'Enter a valid date of birth.')]
        #[Assert\Regex(pattern: '/\A\d{4}-\d{2}-\d{2}\z/', message: 'Use the YYYY-MM-DD date format.')]
        public ?string $birthDate = null,
        #[Assert\NotBlank(message: 'Password is required.')]
        #[Assert\Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters.')]
        #[Assert\Length(max: 255)]
        public ?string $password = null,
        #[Assert\NotBlank(message: 'Password confirmation is required.')]
        public ?string $passwordConfirm = null,
    ) {
    }

    #[Assert\Callback]
    public function validateAdultAge(ExecutionContextInterface $context): void
    {
        if (null === $this->birthDate || '' === $this->birthDate) {
            return;
        }

        $birthDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->birthDate);
        $dateErrors = \DateTimeImmutable::getLastErrors();

        if (
            false === $birthDate
            || (false !== $dateErrors && (0 !== $dateErrors['warning_count'] || 0 !== $dateErrors['error_count']))
        ) {
            return;
        }

        if ($birthDate > (new \DateTimeImmutable('today'))->modify('-18 years')) {
            $context->buildViolation('You must be at least 18 to create an adult account. Minors need a parent-managed profile.')
                ->atPath('birthDate')
                ->addViolation();
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            username: self::usernameOrNull($data['username'] ?? null),
            email: self::stringOrNull($data['email'] ?? null),
            birthDate: self::stringOrNull($data['birthDate'] ?? null),
            password: self::stringOrNull($data['password'] ?? null),
            passwordConfirm: self::stringOrNull($data['passwordConfirm'] ?? null),
        );
    }

    private static function usernameOrNull(mixed $value): ?string
    {
        return \is_string($value) ? strtolower(trim($value)) : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
