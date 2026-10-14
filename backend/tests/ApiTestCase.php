<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\HouseholdMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;

/**
 * Functional tests run on a local SQLite file (.env.test). The schema is
 * rebuilt from the Doctrine metadata before every test for full isolation.
 */
abstract class ApiTestCase extends WebTestCase
{
    use MailerAssertionsTrait;

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
     * Registers and verifies an account through the real HTTP endpoints.
     * The verification email is captured by Symfony's test mailer.
     *
     * @return array<string, mixed>
     */
    protected static function registerAccount(
        KernelBrowser $client,
        string $email = 'camille@example.com',
    ): array {
        $username = strtolower((string) preg_replace('/[^a-z0-9_]/', '_', explode('@', $email)[0])).'_test';

        $response = self::api($client, 'POST', '/api/auth/register', [
            'username' => substr($username, 0, 30),
            'email' => $email,
            'birthDate' => '1990-05-12',
            'password' => 'secret123',
            'passwordConfirm' => 'secret123',
        ]);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        $token = self::verificationTokenFromLastEmail();

        $verified = self::api($client, 'POST', '/api/auth/verify-email', [
            'token' => $token,
        ]);
        self::assertSame(Response::HTTP_OK, $verified->getStatusCode());

        return self::decoded($verified);
    }

    /**
     * Registers and verifies an account, returning its automatically-created private space.
     *
     * @return array<string, mixed>
     */
    protected static function registerHousehold(
        KernelBrowser $client,
        string $email = 'camille@example.com',
        string $householdName = 'Mon dressing',
    ): array {
        $payload = self::registerAccount($client, $email);

        if ('Mon dressing' !== $householdName) {
            $created = self::api($client, 'POST', '/api/auth/households', [
                'householdName' => $householdName,
                'firstName' => 'Moi',
            ]);
            self::assertSame(Response::HTTP_CREATED, $created->getStatusCode());

            return self::decoded($created);
        }

        return [
            'household' => $payload['household'],
            'profile' => $payload['activeProfile'],
            'place' => $payload['places'][0],
        ];
    }

    protected static function verificationTokenFromLastEmail(): string
    {
        $message = self::getMailerMessage();
        self::assertNotNull($message, 'Registration should send a verification email.');
        self::assertInstanceOf(Email::class, $message);
        $body = $message->getTextBody();
        self::assertNotNull($body);

        self::assertSame(1, preg_match('/token=([a-f0-9]{64})/', $body, $matches));

        return $matches[1];
    }

    protected static function removeHouseholdMemberships(string $email): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        foreach ($em->getRepository(HouseholdMember::class)->findBy(['user' => $user]) as $membership) {
            $em->remove($membership);
        }

        $em->flush();
    }
}
