<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AuthControllerTest extends ApiTestCase
{
    public function testRegisterCreatesEverythingAndLogsIn(): void
    {
        $client = static::createClient();

        $payload = self::registerHousehold($client);

        $this->assertSame('camille@example.com', $payload['user']['email']);
        $this->assertSame('Famille Moreau', $payload['household']['name']);
        $this->assertNotSame('', $payload['household']['slug']);
        $this->assertSame('Camille', $payload['profile']['name']);
        $this->assertSame('parent', $payload['profile']['role']);
        $this->assertSame('Maison', $payload['place']['name']);
        $this->assertSame($payload['profile']['id'], $payload['activeProfileId']);

        // Same client = session cookie kept: /me works right away.
        $me = self::api($client, 'GET', '/api/auth/me');

        $this->assertSame(Response::HTTP_OK, $me->getStatusCode());
        $mePayload = self::decoded($me);
        $this->assertSame($payload['user']['email'], $mePayload['user']['email']);
        $this->assertCount(1, $mePayload['profiles']);
        $this->assertCount(1, $mePayload['places']);
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $other = self::freshClient();

        $response = self::api($other, 'POST', '/api/auth/register', [
            'email' => 'camille@example.com',
            'password' => 'secret123',
            'passwordConfirm' => 'secret123',
            'householdName' => 'Autre foyer',
            'firstName' => 'Sophie',
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRegisterValidatesFields(): void
    {
        $client = static::createClient();

        $response = self::api($client, 'POST', '/api/auth/register', [
            'email' => 'not-an-email',
            'password' => 'short',
            'passwordConfirm' => 'different',
            'householdName' => '',
            'firstName' => '',
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $payload = self::decoded($response);
        $this->assertSame('validation_failed', $payload['error']['code']);
        $this->assertArrayHasKey('email', $payload['error']['violations']);
        $this->assertArrayHasKey('password', $payload['error']['violations']);
    }

    public function testLoginLogoutFlow(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $logout = self::api($client, 'POST', '/api/auth/logout');
        $this->assertSame(Response::HTTP_NO_CONTENT, $logout->getStatusCode());

        $me = self::api($client, 'GET', '/api/auth/me');
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $me->getStatusCode());

        $bad = self::api($client, 'POST', '/api/auth/login', [
            'email' => 'camille@example.com',
            'password' => 'wrong-password',
        ]);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $bad->getStatusCode());
        $this->assertSame('invalid_credentials', self::decoded($bad)['error']['code']);

        $good = self::api($client, 'POST', '/api/auth/login', [
            'email' => 'camille@example.com',
            'password' => 'secret123',
        ]);
        $this->assertSame(Response::HTTP_OK, $good->getStatusCode());

        $payload = self::decoded($good);
        $this->assertSame('camille@example.com', $payload['user']['email']);
        $this->assertNull($payload['activeProfileId']);
        $this->assertCount(1, $payload['profiles']);
    }

    public function testMeRequiresAuthentication(): void
    {
        $client = static::createClient();

        $response = self::api($client, 'GET', '/api/auth/me');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('unauthenticated', self::decoded($response)['error']['code']);
    }

    public function testChildProfileCreationAndStandalonePinLogin(): void
    {
        $parent = static::createClient();
        $household = self::registerHousehold($parent);

        $created = self::api($parent, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Léo',
            'pin' => '1234',
        ]);

        $this->assertSame(Response::HTTP_CREATED, $created->getStatusCode());
        $childProfile = self::decoded($created)['profile'];
        $this->assertSame('child', $childProfile['role']);
        $this->assertTrue($childProfile['hasPin']);

        // Standalone login on a fresh device: slug -> profile -> PIN (Mode C).
        $kid = self::freshClient();

        $login = self::api($kid, 'POST', '/api/auth/profile-pin', [
            'householdSlug' => $household['household']['slug'],
            'profileId' => $childProfile['id'],
            'pin' => '1234',
        ]);

        $this->assertSame(Response::HTTP_OK, $login->getStatusCode());
        $this->assertSame($childProfile['id'], self::decoded($login)['activeProfileId']);

        $me = self::api($kid, 'GET', '/api/auth/me');
        $mePayload = self::decoded($me);
        $this->assertSame($childProfile['id'], $mePayload['activeProfileId']);
        $this->assertSame('Léo', $mePayload['activeProfile']['name']);
    }

    public function testPinLoginHidesWhichPartIsWrong(): void
    {
        $parent = static::createClient();
        $household = self::registerHousehold($parent);

        $created = self::api($parent, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Léo',
            'pin' => '1234',
        ]);
        $childProfile = self::decoded($created)['profile'];

        $kid = self::freshClient();

        $wrongPin = self::api($kid, 'POST', '/api/auth/profile-pin', [
            'householdSlug' => $household['household']['slug'],
            'profileId' => $childProfile['id'],
            'pin' => '9999',
        ]);

        $wrongSlug = self::api($kid, 'POST', '/api/auth/profile-pin', [
            'householdSlug' => 'no-such-household',
            'profileId' => $childProfile['id'],
            'pin' => '1234',
        ]);

        foreach ([$wrongPin, $wrongSlug] as $response) {
            $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
            $this->assertSame('invalid_pin', self::decoded($response)['error']['code']);
        }
    }

    public function testParentCanSwitchProfiles(): void
    {
        $parent = static::createClient();
        self::registerHousehold($parent);

        $created = self::api($parent, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Léo',
            'pin' => '1234',
        ]);
        $childId = self::decoded($created)['profile']['id'];

        $switch = self::api($parent, 'POST', '/api/auth/profiles/'.$childId.'/switch');

        $this->assertSame(Response::HTTP_OK, $switch->getStatusCode());
        $this->assertSame($childId, self::decoded($switch)['activeProfileId']);

        $me = self::api($parent, 'GET', '/api/auth/me');
        $this->assertSame($childId, self::decoded($me)['activeProfileId']);
    }

    public function testSwitchToForeignProfileReturns404(): void
    {
        $first = static::createClient();
        self::registerHousehold($first, 'camille@example.com');

        $second = self::freshClient();
        $other = self::registerHousehold($second, 'sophie@example.com');

        $foreignId = $other['profile']['id'];

        $response = self::api($first, 'POST', '/api/auth/profiles/'.$foreignId.'/switch');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('not_found', self::decoded($response)['error']['code']);
    }

    public function testChildCannotCreateProfiles(): void
    {
        $parent = static::createClient();
        $household = self::registerHousehold($parent);

        $created = self::api($parent, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Léo',
            'pin' => '1234',
        ]);
        $childId = self::decoded($created)['profile']['id'];

        $kid = self::freshClient();
        self::api($kid, 'POST', '/api/auth/profile-pin', [
            'householdSlug' => $household['household']['slug'],
            'profileId' => $childId,
            'pin' => '1234',
        ]);

        $response = self::api($kid, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Chloé',
            'pin' => '5678',
        ]);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testSwitchToUnknownProfileReturns404(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $response = self::api($client, 'POST', '/api/auth/profiles/999999/switch');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testHouseholdProfilesListing(): void
    {
        $parent = static::createClient();
        $household = self::registerHousehold($parent);

        self::api($parent, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Léo',
            'pin' => '1234',
        ]);

        // Public listing: no authentication needed (powers the PIN login UI).
        $anon = self::freshClient();

        $response = self::api($anon, 'GET', '/api/auth/households/'.$household['household']['slug'].'/profiles');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = self::decoded($response);
        $this->assertSame('Famille Moreau', $payload['household']['name']);
        $this->assertCount(2, $payload['profiles']);
        $this->assertSame('parent', $payload['profiles'][0]['role']);
        $this->assertFalse($payload['profiles'][0]['hasPin']);
        $this->assertSame('child', $payload['profiles'][1]['role']);
        $this->assertTrue($payload['profiles'][1]['hasPin']);
        $this->assertArrayNotHasKey('email', $payload['profiles'][0]);

        $unknown = self::api($anon, 'GET', '/api/auth/households/no-such-household/profiles');
        $this->assertSame(Response::HTTP_NOT_FOUND, $unknown->getStatusCode());
    }

    public function testPartnerProfileCreation(): void
    {
        $parent = static::createClient();
        self::registerHousehold($parent);

        $created = self::api($parent, 'POST', '/api/auth/profiles', [
            'role' => 'partner',
            'name' => 'Thomas',
            'email' => 'thomas@example.com',
            'password' => 'secret123',
        ]);

        $this->assertSame(Response::HTTP_CREATED, $created->getStatusCode());

        $payload = self::decoded($created);
        $this->assertSame('partner', $payload['profile']['role']);
        $this->assertSame('thomas@example.com', $payload['user']['email']);

        // The partner logs in with their own credentials.
        $partner = self::freshClient();

        $login = self::api($partner, 'POST', '/api/auth/login', [
            'email' => 'thomas@example.com',
            'password' => 'secret123',
        ]);

        $this->assertSame(Response::HTTP_OK, $login->getStatusCode());
        $this->assertSame('thomas@example.com', self::decoded($login)['user']['email']);
    }
}
