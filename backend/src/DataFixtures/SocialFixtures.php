<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Follow;
use App\Entity\FriendRequest;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class SocialFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $parent = $this->getReference(UserFixtures::PARENT_REFERENCE, User::class);
        $partner = $this->getReference(UserFixtures::PARTNER_REFERENCE, User::class);
        $friend = $this->getReference(UserFixtures::FRIEND_REFERENCE, User::class);

        $manager->persist(new Follow($parent, $friend));
        $manager->persist(new Follow($friend, $parent));
        $manager->persist(new Follow($partner, $parent));
        $manager->flush();

        $accepted = new FriendRequest($parent, $friend);
        $accepted->accept();
        $manager->persist($accepted);

        $manager->persist(new FriendRequest($partner, $friend));
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
