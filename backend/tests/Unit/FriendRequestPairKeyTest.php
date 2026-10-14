<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\FriendRequest;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class FriendRequestPairKeyTest extends TestCase
{
    public function testPairKeyJoinsTheTwoIdsInAscendingOrder(): void
    {
        $low = $this->persistedUser('a@example.test', 'A', 3);
        $high = $this->persistedUser('b@example.test', 'B', 7);

        self::assertSame('3:7', FriendRequest::pairKey($low, $high));
        self::assertSame('3:7', FriendRequest::pairKey($high, $low));
    }

    public function testPairKeyRefusesAccountsThatHaveNotBeenPersisted(): void
    {
        $unpersisted = new User('a@example.test', 'A');
        $persisted = $this->persistedUser('b@example.test', 'B', 1);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Friend requests require persisted accounts.');

        FriendRequest::pairKey($unpersisted, $persisted);
    }

    public function testConstructorComputesThePairKeyAndStartsPending(): void
    {
        $sender = $this->persistedUser('a@example.test', 'A', 12);
        $recipient = $this->persistedUser('b@example.test', 'B', 5);

        $request = new FriendRequest($sender, $recipient);

        self::assertSame('5:12', $request->getPairKey());
        self::assertSame('pending', $request->getStatus()->value);
        self::assertSame($sender, $request->getSender());
        self::assertSame($recipient, $request->getRecipient());
    }

    private function persistedUser(string $email, string $firstName, int $id): User
    {
        $user = new User($email, $firstName);

        $property = new \ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        return $user;
    }
}
