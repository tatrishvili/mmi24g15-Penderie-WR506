<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const PARENT_REFERENCE = 'user-parent';
    public const PARTNER_REFERENCE = 'user-partner';
    public const FRIEND_REFERENCE = 'user-friend';

    public const DEMO_PASSWORD = 'PenderieTest!2026';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $parent = new User(
            'penderie.test.20261009@example.com',
            'Camille',
            'penderie_test',
            new \DateTimeImmutable('1990-05-12'),
        );
        $parent->setPassword($this->hasher->hashPassword($parent, self::DEMO_PASSWORD));

        $partner = new User(
            'penderie.partner@example.com',
            'Alex',
            'penderie_partner',
            new \DateTimeImmutable('1991-08-23'),
        );
        $partner->setPassword($this->hasher->hashPassword($partner, self::DEMO_PASSWORD));

        $friend = new User(
            'penderie.noa@example.com',
            'Noa',
            'noa_penderie',
            new \DateTimeImmutable('1992-03-04'),
        );
        $friend->setPassword($this->hasher->hashPassword($friend, self::DEMO_PASSWORD));

        $manager->persist($parent);
        $manager->persist($partner);
        $manager->persist($friend);
        $manager->flush();

        $this->addReference(self::PARENT_REFERENCE, $parent);
        $this->addReference(self::PARTNER_REFERENCE, $partner);
        $this->addReference(self::FRIEND_REFERENCE, $friend);
    }
}
