<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Household;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\ProfileRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileFixtures extends Fixture implements DependentFixtureInterface
{
    public const PARENT_PROFILE_REFERENCE = 'profile-parent';
    public const PARTNER_PROFILE_REFERENCE = 'profile-partner';
    public const CHILD_PROFILE_REFERENCE = 'profile-child';

    public const DEMO_PIN = '1234';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $household = $this->getReference(HouseholdFixtures::HOUSEHOLD_REFERENCE, Household::class);
        $parentUser = $this->getReference(UserFixtures::PARENT_REFERENCE, User::class);
        $partnerUser = $this->getReference(UserFixtures::PARTNER_REFERENCE, User::class);

        $parent = new Profile($household, 'Camille', ProfileRole::Parent, $parentUser);
        $parent->setAvatarColor('#D97757');

        $partner = new Profile($household, 'Alex', ProfileRole::Partner, $partnerUser);
        $partner->setAvatarColor('#4F7BD9');

        $child = new Profile($household, 'Léo', ProfileRole::Child);
        $child->setPinHash($this->hasher->hashPassword($child, self::DEMO_PIN));
        $child->setAvatarColor('#3DBA7D');

        $manager->persist($parent);
        $manager->persist($partner);
        $manager->persist($child);
        $manager->flush();

        $this->addReference(self::PARENT_PROFILE_REFERENCE, $parent);
        $this->addReference(self::PARTNER_PROFILE_REFERENCE, $partner);
        $this->addReference(self::CHILD_PROFILE_REFERENCE, $child);
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            HouseholdFixtures::class,
        ];
    }
}
