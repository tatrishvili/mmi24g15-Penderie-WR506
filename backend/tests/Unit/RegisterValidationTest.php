<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dto\RegisterDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The registration rules live on RegisterDto as validator attributes,
 * so they can be checked without booting Symfony (no container, no database).
 */
final class RegisterValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testAValidRegistrationPasses(): void
    {
        $violations = $this->validator->validate(self::validDto());

        self::assertCount(0, $violations, $this->messages($violations));
    }

    public function testUsernameMustBeThreeToThirtyLowercaseSlugCharacters(): void
    {
        $this->assertViolates(self::validDto(username: 'ab'), 'username');
        $this->assertViolates(self::validDto(username: 'Has Space'), 'username');
        $this->assertViolates(self::validDto(username: str_repeat('a', 31)), 'username');
        $this->assertViolates(self::validDto(username: null), 'username');
    }

    public function testEmailMustBePresentAndValid(): void
    {
        $this->assertViolates(self::validDto(email: 'not-an-email'), 'email');
        $this->assertViolates(self::validDto(email: null), 'email');
    }

    public function testPasswordMustBeAtLeastEightCharacters(): void
    {
        $this->assertViolates(self::validDto(password: 'short'), 'password');
        $this->assertViolates(self::validDto(password: null), 'password');
    }

    public function testBirthDateMustUseTheIsoFormat(): void
    {
        $this->assertViolates(self::validDto(birthDate: '12-05-1990'), 'birthDate');
        $this->assertViolates(self::validDto(birthDate: 'not-a-date'), 'birthDate');
    }

    public function testAccountCreatorsMustBeAtLeastEighteenYearsOld(): void
    {
        $minor = (new \DateTimeImmutable('today'))->modify('-10 years')->format('Y-m-d');

        $violations = $this->validator->validate(self::validDto(birthDate: $minor));

        self::assertGreaterThan(0, \count($violations), 'A 10-year-old must be rejected.');

        $messages = $this->messages($violations);
        self::assertStringContainsString('at least 18', $messages);
    }

    public function testAdultAgeCheckSkipsAnAbsentBirthDate(): void
    {
        $violations = $this->validator->validate(self::validDto(birthDate: null));

        self::assertCount(1, $violations, $this->messages($violations));
        self::assertStringContainsString('Date of birth is required', $this->messages($violations));
    }

    private function assertViolates(RegisterDto $dto, string $path): void
    {
        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, \count($violations), \sprintf('Expected a violation on "%s".', $path));

        $paths = array_map(
            static fn (ConstraintViolationInterface $violation): string => (string) $violation->getPropertyPath(),
            iterator_to_array($violations),
        );
        self::assertContains($path, $paths, $this->messages($violations));
    }

    private function messages(ConstraintViolationListInterface $violations): string
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getPropertyPath().': '.$violation->getMessage();
        }

        return implode(' | ', $messages);
    }

    private static function validDto(
        ?string $username = 'alice_test',
        ?string $email = 'alice@example.test',
        ?string $birthDate = '1990-05-12',
        ?string $password = 'secret123',
        ?string $passwordConfirm = 'secret123',
    ): RegisterDto {
        return new RegisterDto(
            username: $username,
            email: $email,
            birthDate: $birthDate,
            password: $password,
            passwordConfirm: $passwordConfirm,
        );
    }
}
