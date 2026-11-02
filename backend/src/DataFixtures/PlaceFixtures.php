<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Household;
use App\Entity\Place;
use App\Entity\Room;
use App\Entity\StorageLocation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PlaceFixtures extends Fixture implements DependentFixtureInterface
{
    public const PLACE_MAISON_REFERENCE = 'place-maison';
    public const PLACE_GARAGE_REFERENCE = 'place-garage';
    public const ROOM_CHAMBRE_REFERENCE = 'room-chambre';
    public const ROOM_DRESSING_REFERENCE = 'room-dressing';
    public const LOCATION_ARMOIRE_REFERENCE = 'loc-armoire';
    public const LOCATION_ETAGERE_REFERENCE = 'loc-etagere';
    public const LOCATION_CARTON_REFERENCE = 'loc-carton';

    public function load(ObjectManager $manager): void
    {
        $household = $this->getReference(HouseholdFixtures::HOUSEHOLD_REFERENCE, Household::class);

        $maison = new Place($household, 'Maison', true);
        $garage = new Place($household, 'Garage', false);

        $chambre = new Room($household, $maison, 'Chambre');
        $dressing = new Room($household, $maison, 'Dressing');

        $armoire = new StorageLocation($household, $chambre, 'Armoire');
        $etagere = new StorageLocation($household, $dressing, 'Étagère');
        $carton = new StorageLocation($household, $dressing, 'Carton sous l’été', $etagere);

        $manager->persist($maison);
        $manager->persist($garage);
        $manager->persist($chambre);
        $manager->persist($dressing);
        $manager->persist($armoire);
        $manager->persist($etagere);
        $manager->persist($carton);
        $manager->flush();

        $this->addReference(self::PLACE_MAISON_REFERENCE, $maison);
        $this->addReference(self::PLACE_GARAGE_REFERENCE, $garage);
        $this->addReference(self::ROOM_CHAMBRE_REFERENCE, $chambre);
        $this->addReference(self::ROOM_DRESSING_REFERENCE, $dressing);
        $this->addReference(self::LOCATION_ARMOIRE_REFERENCE, $armoire);
        $this->addReference(self::LOCATION_ETAGERE_REFERENCE, $etagere);
        $this->addReference(self::LOCATION_CARTON_REFERENCE, $carton);
    }

    public function getDependencies(): array
    {
        return [HouseholdFixtures::class];
    }
}
