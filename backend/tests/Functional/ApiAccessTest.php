<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security contract of the API: every route outside the public list
 * (health, register, verify-email, resend-verification, login) answers a JSON
 * 401 to anonymous callers, and an authenticated member still needs an active
 * household (via POST /api/auth/households/{id}/select) to reach the foyer.
 */
final class ApiAccessTest extends ApiTestCase
{
    public function testProtectedEndpointsAnswerJson401ToAnonymousRequests(): void
    {
        $client = self::freshClient();

        $protected = [
            ['GET', '/api/auth/me', []],
            ['PUT', '/api/auth/username', ['username' => 'sneaky']],
            ['POST', '/api/auth/profile-pin', ['profileId' => 1, 'pin' => '1234']],
            ['POST', '/api/auth/households', ['name' => 'Sneak house']],
            ['POST', '/api/auth/households/1/select', []],
            ['POST', '/api/auth/profiles', ['name' => 'Kid']],
            ['GET', '/api/items', []],
            ['POST', '/api/items', ['name' => 'Sneak item']],
            ['GET', '/api/items/1', []],
            ['PATCH', '/api/items/1', ['name' => 'Renamed']],
            ['DELETE', '/api/items/1', []],
            ['GET', '/api/places', []],
            ['POST', '/api/places', ['name' => 'Attic']],
            ['GET', '/api/friends', []],
            ['POST', '/api/friends/requests', ['username' => 'nobody']],
            ['POST', '/api/scan', ['token' => 'deadbeef']],
        ];

        foreach ($protected as [$method, $uri, $body]) {
            $response = self::api($client, $method, $uri, $body);
            $payload = self::decoded($response);

            self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), $method.' '.$uri);
            self::assertSame('unauthenticated', $payload['error']['code'] ?? null, $method.' '.$uri);
            self::assertNotEmpty($payload['error']['message'] ?? '', $method.' '.$uri);
        }
    }

    public function testPublicRoutesStayReachableWithoutAuth(): void
    {
        $client = self::freshClient();

        $health = self::api($client, 'GET', '/api/health');

        self::assertSame(Response::HTTP_OK, $health->getStatusCode());
    }

    public function testLoginUserReachesTheFoyerOnlyAfterSelectingAHousehold(): void
    {
        $client = self::freshClient();
        $registered = self::registerHousehold($client);
        $householdId = (int) ($registered['household']['id'] ?? 0);
        self::assertGreaterThan(0, $householdId);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'camille@example.com']);
        self::assertInstanceOf(User::class, $user);

        $authed = self::freshClient();
        $authed->loginUser($user);

        $me = self::api($authed, 'GET', '/api/auth/me');
        self::assertSame(Response::HTTP_OK, $me->getStatusCode());
        self::assertSame('camille@example.com', self::decoded($me)['user']['email'] ?? null);

        $before = self::api($authed, 'GET', '/api/items');
        self::assertSame(Response::HTTP_UNAUTHORIZED, $before->getStatusCode());
        self::assertStringContainsString(
            'household',
            strtolower((string) (self::decoded($before)['error']['message'] ?? '')),
        );

        $selected = self::api($authed, 'POST', '/api/auth/households/'.$householdId.'/select');
        self::assertSame(Response::HTTP_OK, $selected->getStatusCode());

        $after = self::api($authed, 'GET', '/api/items');
        self::assertSame(Response::HTTP_OK, $after->getStatusCode());
        self::assertIsArray(self::decoded($after)['items'] ?? null);
        self::assertIsInt(self::decoded($after)['total'] ?? null);
    }
}
