<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests run on a local SQLite file (.env.test). The schema is
 * rebuilt from the Doctrine metadata before every test for full isolation.
 */
abstract class ApiTestCase extends WebTestCase
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

        self::ensureKernelShutdown();
    }

    /**
     * A second, third, ... isolated client in the same test (fresh cookie jar).
     * WebTestCase::createClient() refuses to run while the kernel is booted,
     * so the previous kernel is shut down first. Database state is shared
     * through the SQLite file, sessions through cookies.
     */
    protected static function freshClient(): KernelBrowser
    {
        self::ensureKernelShutdown();

        return static::createClient();
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected static function api(KernelBrowser $client, string $method, string $uri, array $payload = []): Response
    {
        $client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload),
        );

        return $client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function decoded(Response $response): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);

        return $data;
    }

    /**
     * Registers a household through the real HTTP endpoint and returns the
     * payload. The client keeps the session cookie (parent logged in).
     *
     * @return array<string, mixed>
     */
    protected static function registerHousehold(
        KernelBrowser $client,
        string $email = 'camille@example.com',
    ): array {
        $response = self::api($client, 'POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'secret123',
            'passwordConfirm' => 'secret123',
            'householdName' => 'Famille Moreau',
            'firstName' => 'Camille',
        ]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        return self::decoded($response);
    }
}
