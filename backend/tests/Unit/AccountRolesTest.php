<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class AccountRolesTest extends TestCase
{
    public function testAdultAccountKeepsRoleUserByDefault(): void
    {
        $user = new User('camille@example.com', 'Camille');

        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertFalse($user->isSuperAdmin());
    }

    public function testSuperAdminRoleCanBeGrantedAndRevoked(): void
    {
        $user = new User('camille@example.com', 'Camille');

        $user->promote('ROLE_SUPER_ADMIN');
        self::assertTrue($user->isSuperAdmin());
        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertContains('ROLE_SUPER_ADMIN', $user->getRoles());

        $user->demote('ROLE_SUPER_ADMIN');
        self::assertFalse($user->isSuperAdmin());
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testUnknownRolesAreIgnoredAndDuplicatesRemoved(): void
    {
        $user = new User('camille@example.com', 'Camille');
        $user->setRoles(['ROLE_USER', 'ROLE_USER', 'not-a-role', 'ROLE_SUPER_ADMIN']);

        self::assertSame(['ROLE_USER', 'ROLE_SUPER_ADMIN'], $user->getRoles());
    }

    public function testLoginTimestampIsRecorded(): void
    {
        $user = new User('camille@example.com', 'Camille');
        self::assertNull($user->getLastLoginAt());

        $at = new \DateTimeImmutable('2026-11-15 10:00:00');
        $user->markLogin($at);

        self::assertSame($at, $user->getLastLoginAt());
    }
}
