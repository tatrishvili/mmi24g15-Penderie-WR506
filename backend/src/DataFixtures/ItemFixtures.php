<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Household;
use App\Entity\Item;
use App\Entity\ItemPhoto;
use App\Entity\Profile;
use App\Entity\Room;
use App\Entity\StorageLocation;
use App\Enum\ItemCondition;
use App\Enum\ItemStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ItemFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $household = $this->getReference(HouseholdFixtures::HOUSEHOLD_REFERENCE, Household::class);

        $profiles = [
            'parent' => $this->getReference(ProfileFixtures::PARENT_PROFILE_REFERENCE, Profile::class),
            'partner' => $this->getReference(ProfileFixtures::PARTNER_PROFILE_REFERENCE, Profile::class),
            'child' => $this->getReference(ProfileFixtures::CHILD_PROFILE_REFERENCE, Profile::class),
        ];
        $rooms = [
            'chambre' => $this->getReference(PlaceFixtures::ROOM_CHAMBRE_REFERENCE, Room::class),
            'dressing' => $this->getReference(PlaceFixtures::ROOM_DRESSING_REFERENCE, Room::class),
        ];
        $locations = [
            'armoire' => $this->getReference(PlaceFixtures::LOCATION_ARMOIRE_REFERENCE, StorageLocation::class),
            'etagere' => $this->getReference(PlaceFixtures::LOCATION_ETAGERE_REFERENCE, StorageLocation::class),
            'carton' => $this->getReference(PlaceFixtures::LOCATION_CARTON_REFERENCE, StorageLocation::class),
        ];

        $items = [
            [
                'name' => 'Pull noir',
                'type' => 'pull',
                'category' => 'Pulls',
                'brand' => 'Zara',
                'color' => 'noir',
                'size' => 'M',
                'material' => 'laine',
                'condition' => ItemCondition::TresBon,
                'status' => ItemStatus::Disponible,
                'owner' => 'parent',
                'room' => 'chambre',
                'location' => 'armoire',
                'memory' => 'Porté tout l’hiver dernier, très chaud.',
                'photo' => ['file' => 'demo-pull-noir.jpg', 'rgb' => [40, 42, 51], 'label' => 'PULL NOIR'],
            ],
            [
                'name' => 'Robe d’été',
                'type' => 'robe',
                'category' => 'Robes',
                'brand' => 'Sézane',
                'color' => 'bleu',
                'size' => '38',
                'material' => 'coton',
                'condition' => ItemCondition::Neuf,
                'status' => ItemStatus::Disponible,
                'owner' => 'parent',
                'room' => 'dressing',
                'location' => 'etagere',
                'memory' => null,
                'photo' => ['file' => 'demo-robe-ete.jpg', 'rgb' => [64, 116, 180], 'label' => 'ROBE ETE'],
            ],
            [
                'name' => 'Sneakers blanches',
                'type' => 'chaussures',
                'category' => 'Chaussures',
                'brand' => 'Nike',
                'color' => 'blanc',
                'size' => '42',
                'material' => 'cuir',
                'condition' => ItemCondition::CommeNeuf,
                'status' => ItemStatus::Disponible,
                'owner' => 'partner',
                'room' => 'chambre',
                'location' => null,
                'memory' => null,
                'photo' => ['file' => 'demo-sneakers.jpg', 'rgb' => [232, 232, 232], 'label' => 'SNEAKERS'],
            ],
            [
                'name' => 'Jean brut',
                'type' => 'pantalon',
                'category' => 'Pantalons',
                'brand' => 'Levi’s',
                'color' => 'bleu',
                'size' => '40',
                'material' => 'denim',
                'condition' => ItemCondition::Bon,
                'status' => ItemStatus::Disponible,
                'owner' => 'parent',
                'room' => 'dressing',
                'location' => 'etagere',
                'memory' => null,
                'photo' => null,
            ],
            [
                'name' => 'Sac cabas',
                'type' => 'sac',
                'category' => 'Sacs',
                'brand' => 'Longchamp',
                'color' => 'beige',
                'size' => null,
                'material' => 'cuir',
                'condition' => ItemCondition::TresBon,
                'status' => ItemStatus::Loue,
                'owner' => 'partner',
                'room' => 'dressing',
                'location' => null,
                'memory' => null,
                'photo' => null,
            ],
            [
                'name' => 'Chemise à carreaux',
                'type' => 'autre',
                'category' => 'Chemises',
                'brand' => 'Uniqlo',
                'color' => 'rouge',
                'size' => 'L',
                'material' => 'coton',
                'condition' => ItemCondition::Bon,
                'status' => ItemStatus::Disponible,
                'owner' => 'parent',
                'room' => 'chambre',
                'location' => 'armoire',
                'memory' => null,
                'photo' => null,
            ],
            [
                'name' => 'Jupe plissée',
                'type' => 'jupe',
                'category' => 'Jupes',
                'brand' => '& Other Stories',
                'color' => 'kaki',
                'size' => '36',
                'material' => 'polyester',
                'condition' => ItemCondition::CommeNeuf,
                'status' => ItemStatus::Vendu,
                'owner' => 'partner',
                'room' => null,
                'location' => null,
                'memory' => null,
                'photo' => null,
            ],
            [
                'name' => 'Tee-shirt basique',
                'type' => 'tshirt',
                'category' => 'T-shirts',
                'brand' => 'COS',
                'color' => 'gris',
                'size' => 'L',
                'material' => 'coton',
                'condition' => ItemCondition::Neuf,
                'status' => ItemStatus::Disponible,
                'owner' => 'child',
                'room' => 'chambre',
                'location' => 'armoire',
                'memory' => null,
                'photo' => null,
            ],
            [
                'name' => 'Manteau d’hiver',
                'type' => 'autre',
                'category' => 'Manteaux',
                'brand' => 'Woolrich',
                'color' => 'noir',
                'size' => 'M',
                'material' => 'laine',
                'condition' => ItemCondition::TresBon,
                'status' => ItemStatus::Disponible,
                'owner' => 'parent',
                'room' => 'dressing',
                'location' => 'etagere',
                'memory' => null,
                'photo' => ['file' => 'demo-manteau.jpg', 'rgb' => [58, 50, 46], 'label' => 'MANTEAU'],
            ],
            [
                'name' => 'Ballon de foot',
                'type' => 'autre',
                'category' => 'Sport',
                'brand' => 'Adidas',
                'color' => 'blanc',
                'size' => '53',
                'material' => 'plastique',
                'condition' => ItemCondition::AReparer,
                'status' => ItemStatus::Disponible,
                'owner' => 'child',
                'room' => 'dressing',
                'location' => 'carton',
                'memory' => null,
                'photo' => null,
            ],
        ];

        foreach ($items as $spec) {
            $item = new Item($household, $profiles[$spec['owner']], $spec['name']);
            $item->setType($spec['type']);
            $item->setCategory($spec['category']);
            $item->setBrand($spec['brand']);
            $item->setColor($spec['color']);
            $item->setSize($spec['size']);
            $item->setMaterial($spec['material']);
            $item->setCondition($spec['condition']);
            $item->setStatus($spec['status']);
            $item->setRoom(null !== $spec['room'] ? $rooms[$spec['room']] : null);
            $item->setStorageLocation(null !== $spec['location'] ? $locations[$spec['location']] : null);
            $item->setMemory($spec['memory']);

            $manager->persist($item);

            if (null !== $spec['photo']) {
                $manager->persist(new ItemPhoto($item, $this->writePlaceholder($spec['photo']), true, 0));
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            HouseholdFixtures::class,
            ProfileFixtures::class,
            PlaceFixtures::class,
        ];
    }

    /**
     * @param array{file: string, rgb: array{int, int, int}, label: string} $photo
     */
    private function writePlaceholder(array $photo): string
    {
        $dir = \dirname(__DIR__, 2).'/var/uploads/items';

        if (!is_dir($dir) && false === @mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Photo storage is unavailable.');
        }

        $image = imagecreatetruecolor(640, 640);

        if (false === $image) {
            throw new \RuntimeException('Photo storage is unavailable.');
        }

        [$red, $green, $blue] = $photo['rgb'];
        $background = imagecolorallocate($image, $red, $green, $blue);

        if (false === $background) {
            throw new \RuntimeException('Photo storage is unavailable.');
        }

        imagefill($image, 0, 0, $background);

        $foreground = imagecolorallocate($image, 245, 245, 245);

        if (false === $foreground) {
            throw new \RuntimeException('Photo storage is unavailable.');
        }

        imagerectangle($image, 24, 24, 615, 615, $foreground);
        imagestring($image, 5, max(0, intdiv(640 - 6 * \strlen($photo['label']), 2)), 312, $photo['label'], $foreground);

        if (false === imagejpeg($image, $dir.'/'.$photo['file'], 86)) {
            throw new \RuntimeException('Photo storage is unavailable.');
        }

        imagedestroy($image);

        return 'items/'.$photo['file'];
    }
}
