<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Household;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class HouseholdFixtures extends Fixture
{
    public const HOUSEHOLD_REFERENCE = 'household-demo';

    public function load(ObjectManager $manager): void
    {
        $household = new Household('Mon dressing', 'mon-dressing');

        $manager->persist($household);
        $manager->flush();

        $this->addReference(self::HOUSEHOLD_REFERENCE, $household);
    }
}
