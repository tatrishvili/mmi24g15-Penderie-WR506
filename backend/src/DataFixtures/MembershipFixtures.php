<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Household;
use App\Entity\HouseholdMember;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MembershipFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $household = $this->getReference(HouseholdFixtures::HOUSEHOLD_REFERENCE, Household::class);
        $parent = $this->getReference(UserFixtures::PARENT_REFERENCE, User::class);
        $partner = $this->getReference(UserFixtures::PARTNER_REFERENCE, User::class);

        $manager->persist(new HouseholdMember($parent, $household));
        $manager->persist(new HouseholdMember($partner, $household));
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            HouseholdFixtures::class,
        ];
    }
}
