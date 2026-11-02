<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Household;
use App\Repository\ItemRepository;
use App\Tests\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Runs the real foyer list query (ItemRepository::searchInHousehold) against
 * the fixture dataset: 10 items in the demo household with known statuses
 * (8 disponible, 1 loué, 1 vendu).
 */
final class ItemRepositoryTest extends IntegrationTestCase
{
    private ItemRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = static::getContainer()->get(ItemRepository::class);
    }

    public function testTheDemoHouseholdSeedsTenItems(): void
    {
        $result = $this->repository->searchInHousehold($this->demoHousehold(), '', '', 1, 20);

        self::assertSame(10, $result['total']);
        self::assertCount(10, $result['items']);
    }

    public function testSearchMatchesNameBrandOrColour(): void
    {
        $pulls = $this->repository->searchInHousehold($this->demoHousehold(), 'pull', '', 1, 20);
        self::assertSame(1, $pulls['total']);
        self::assertSame('Pull noir', $pulls['items'][0]->getName());

        $black = $this->repository->searchInHousehold($this->demoHousehold(), 'noir', '', 1, 20);
        self::assertSame(2, $black['total'], 'Pull noir (name) and Manteau d’hiver (colour).');
    }

    public function testStatusFilterReturnsOnlyThatStatus(): void
    {
        $sold = $this->repository->searchInHousehold($this->demoHousehold(), '', 'vendu', 1, 20);
        self::assertSame(1, $sold['total']);
        self::assertSame('Jupe plissée', $sold['items'][0]->getName());

        $lent = $this->repository->searchInHousehold($this->demoHousehold(), '', 'loue', 1, 20);
        self::assertSame(1, $lent['total']);

        $available = $this->repository->searchInHousehold($this->demoHousehold(), '', 'disponible', 1, 20);
        self::assertSame(8, $available['total']);
    }

    public function testPaginationKeepsTheTotal(): void
    {
        $firstPage = $this->repository->searchInHousehold($this->demoHousehold(), '', '', 1, 4);

        self::assertSame(10, $firstPage['total']);
        self::assertCount(4, $firstPage['items']);

        $lastPage = $this->repository->searchInHousehold($this->demoHousehold(), '', '', 3, 4);

        self::assertCount(2, $lastPage['items']);
    }

    public function testAnotherHouseholdSeesNoneOfThoseItems(): void
    {
        $other = new Household('Autre foyer', 'autre-foyer');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($other);
        $em->flush();

        $result = $this->repository->searchInHousehold($other, '', '', 1, 20);

        self::assertSame(0, $result['total']);
        self::assertCount(0, $result['items']);
    }

    private function demoHousehold(): Household
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $household = $em->getRepository(Household::class)->findOneBy(['slug' => 'mon-dressing']);
        self::assertInstanceOf(Household::class, $household);

        return $household;
    }
}
