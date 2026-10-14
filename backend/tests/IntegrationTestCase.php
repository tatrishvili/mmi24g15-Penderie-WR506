<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests boot Symfony against the test SQLite database (.env.test),
 * rebuild the schema from the entity metadata and load the fixture dataset of
 * src/DataFixtures — the same data as `make fixtures`, but isolated per test.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $loader = static::getContainer()->get('doctrine.fixtures.loader');

        $executor = new ORMExecutor($em, new ORMPurger($em));
        $executor->execute($loader->getFixtures());
    }
}
