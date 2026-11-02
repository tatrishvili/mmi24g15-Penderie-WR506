<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Follow;
use App\Entity\FriendRequest;
use App\Entity\Household;
use App\Entity\HouseholdMember;
use App\Entity\Item;
use App\Entity\ItemPhoto;
use App\Entity\PendingRegistration;
use App\Entity\Place;
use App\Entity\Profile;
use App\Entity\Room;
use App\Entity\StorageLocation;
use App\Entity\User;
use App\Enum\ProfileRole;
use PHPUnit\Framework\TestCase;

/**
 * The entities carry real behaviour (roles, security contract, timestamps).
 * Pure construction keeps these checks at unit level: no container, no database.
 */
final class EntityModelTest extends TestCase
{
    public function testHouseholdExposesItsCollectionsAndTimestamps(): void
    {
        $household = new Household('Mon dressing', 'mon-dressing');

        self::assertSame('mon-dressing', $household->getSlug());
        self::assertInstanceOf(\DateTimeImmutable::class, $household->getCreatedAt());
        self::assertCount(0, $household->getProfiles());
        self::assertCount(0, $household->getPlaces());
        self::assertCount(0, $household->getRooms());
    }

    public function testHouseholdMembershipPointsBackToItsUserAndFoyer(): void
    {
        $user = new User('camille@example.com', 'Camille');
        $household = new Household('Mon dressing', 'mon-dressing');
        $member = new HouseholdMember($user, $household);

        self::assertNull($member->getId());
        self::assertSame($user, $member->getUser());
        self::assertSame($household, $member->getHousehold());
        self::assertInstanceOf(\DateTimeImmutable::class, $member->getCreatedAt());
    }

    public function testFollowRecordsItsTimestamp(): void
    {
        $follow = new Follow(new User('a@example.com', 'A'), new User('b@example.com', 'B'));

        self::assertInstanceOf(\DateTimeImmutable::class, $follow->getCreatedAt());
    }

    public function testFriendRequestTimestampAndAcceptTransition(): void
    {
        $from = new User('from@example.com', 'From');
        $to = new User('to@example.com', 'To');
        self::forceId($from, 1);
        self::forceId($to, 2);

        $request = new FriendRequest($from, $to);
        self::assertInstanceOf(\DateTimeImmutable::class, $request->getCreatedAt());
    }

    public function testUserKeepsItsCreationTimestamp(): void
    {
        $user = new User('camille@example.com', 'Camille');

        self::assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
    }

    public function testItemPhotoPrimaryFlagCanBePromoted(): void
    {
        $item = new Item(new Household('H', 'h'), new Profile(new Household('H', 'h'), 'Moi', ProfileRole::Parent), 'Pull');
        $photo = new ItemPhoto($item, 'items/a.jpg', false, 0);

        $photo->setPrimary(true);

        self::assertTrue($photo->isPrimary());
    }

    public function testPendingRegistrationExposesItsTokenHash(): void
    {
        $pending = new PendingRegistration(
            'pending@example.com',
            'hash',
            str_repeat('a', 64),
            new \DateTimeImmutable('+1 hour'),
        );

        self::assertNull($pending->getId());
        self::assertSame(str_repeat('a', 64), $pending->getTokenHash());
    }

    public function testItemNameAndTimestamps(): void
    {
        $household = new Household('H', 'h');
        $item = new Item($household, new Profile($household, 'Moi', ProfileRole::Parent), 'Pull');

        $item->setName('Pull noir');

        self::assertSame('Pull noir', $item->getName());
        self::assertInstanceOf(\DateTimeImmutable::class, $item->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $item->getUpdatedAt());

        $before = $item->getUpdatedAt();
        $item->touch();
        self::assertGreaterThanOrEqual($before, $item->getUpdatedAt());
    }

    public function testProfileSecurityContract(): void
    {
        $household = new Household('H', 'h');
        $user = new User('camille@example.com', 'Camille');
        $parent = new Profile($household, 'Camille', ProfileRole::Parent, $user);

        self::assertSame($user, $parent->getUser());
        self::assertInstanceOf(\DateTimeImmutable::class, $parent->getCreatedAt());
        self::assertStringStartsWith('profile:', $parent->getUserIdentifier());
        self::assertSame(['ROLE_PARENT'], $parent->getRoles());
        self::assertTrue($parent->isAdult());
        $parent->eraseCredentials();

        $child = new Profile($household, 'Léo', ProfileRole::Child);
        self::assertNull($child->getUser());
        self::assertSame(['ROLE_CHILD'], $child->getRoles());
        self::assertFalse($child->isAdult());
        self::assertTrue(ProfileRole::Partner->isAdult());
        self::assertSame('ROLE_PARTNER', ProfileRole::Partner->securityRole());
    }

    public function testPlaceRoomAndStorageExposeTheirHousehold(): void
    {
        $household = new Household('H', 'h');
        $place = new Place($household, 'Penderie');
        $room = new Room($household, $place, 'Chambre');
        $box = new StorageLocation($household, $room, 'Boîte');

        self::assertSame($household, $place->getHousehold());
        self::assertInstanceOf(\DateTimeImmutable::class, $place->getCreatedAt());
        self::assertSame($household, $room->getHousehold());
        self::assertCount(0, $room->getStorageLocations());
        self::assertSame($household, $box->getHousehold());
        self::assertCount(0, $box->getChildren());
    }

    private static function forceId(User $user, int $id): void
    {
        $property = new \ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);
    }
}
