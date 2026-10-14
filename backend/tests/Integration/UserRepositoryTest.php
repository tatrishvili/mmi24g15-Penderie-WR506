<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\IntegrationTestCase;

/**
 * The fixture dataset seeds three accounts (see src/DataFixtures/UserFixtures);
 * the demo parent matches the credentials documented in the README.
 */
final class UserRepositoryTest extends IntegrationTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = static::getContainer()->get(UserRepository::class);
    }

    public function testFindsTheDemoAccountByEmail(): void
    {
        $user = $this->repository->findOneBy(['email' => 'penderie.test.20261009@example.com']);

        self::assertInstanceOf(User::class, $user);
        self::assertSame('penderie_test', $user->getUsername());
        self::assertSame('Camille', $user->getFirstName());
        self::assertTrue($user->isEmailVerified());
    }

    public function testUnknownEmailReturnsNull(): void
    {
        self::assertNull($this->repository->findOneBy(['email' => 'nobody@example.test']));
    }

    public function testThePasswordIsStoredAsAHashNeverAsPlainText(): void
    {
        $user = $this->repository->findOneBy(['email' => 'penderie.test.20261009@example.com']);
        self::assertInstanceOf(User::class, $user);

        self::assertNotSame('PenderieTest!2026', $user->getPassword());
        self::assertStringStartsWith('$', $user->getPassword());
    }

    public function testUsernameIsUniqueAcrossAccounts(): void
    {
        $user = $this->repository->findOneBy(['username' => 'noa_penderie']);

        self::assertInstanceOf(User::class, $user);
        self::assertSame('penderie.noa@example.com', $user->getEmail());
        self::assertCount(3, $this->repository->findAll());
    }
}
