<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\PendingRegistration;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class AuthControllerTest extends ApiTestCase
{
    public function testRegistrationCreatesAccountOnlyAfterEmailVerification(): void
    {
        $client = static::createClient();

        $registered = self::api($client, 'POST', '/api/auth/register', [
            'username' => 'camille_moreau',
            'email' => 'camille@example.com',
            'birthDate' => '1990-05-12',
            'password' => 'secret123',
            'passwordConfirm' => 'secret123',
        ]);

        $this->assertSame(Response::HTTP_ACCEPTED, $registered->getStatusCode());
        $token = self::verificationTokenFromLastEmail();
        $this->assertEmailCount(1);
        $message = self::getMailerMessage();
        $this->assertInstanceOf(\Symfony\Component\Mime\Email::class, $message);
        $this->assertStringNotContainsString('secret123', (string) $message->getTextBody());
        $me = self::api($client, 'GET', '/api/auth/me');
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $me->getStatusCode());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->assertSame(0, $em->getRepository(\App\Entity\User::class)->count([]));
        $pending = $em->getRepository(PendingRegistration::class)->findOneBy(['email' => 'camille@example.com']);
        $this->assertInstanceOf(PendingRegistration::class, $pending);
        $this->assertNotSame('secret123', $pending->getPasswordHash());
        $this->assertSame('camille_moreau', $pending->getUsername());
        $this->assertSame('1990-05-12', $pending->getBirthDate()?->format('Y-m-d'));

        $verified = self::api($client, 'POST', '/api/auth/verify-email', [
            'token' => $token,
        ]);
        $this->assertSame(Response::HTTP_OK, $verified->getStatusCode());

        $payload = self::decoded($verified);
        $this->assertSame('camille@example.com', $payload['user']['email']);
        $this->assertSame('camille_moreau', $payload['user']['username']);
        $this->assertArrayNotHasKey('birthDate', $payload['user']);
        $this->assertSame('Mon dressing', $payload['household']['name']);
        $this->assertSame('Moi', $payload['activeProfile']['name']);
        $this->assertSame('Maison', $payload['places'][0]['name']);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->assertSame(0, $em->getRepository(PendingRegistration::class)->count([]));

        $me = self::api($client, 'GET', '/api/auth/me');
        $this->assertSame(Response::HTTP_OK, $me->getStatusCode());
        $this->assertSame('camille@example.com', self::decoded($me)['user']['email']);
        $this->assertSame('camille_moreau', self::decoded($me)['user']['username']);
        $user = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'camille@example.com']);
        $this->assertSame('1990-05-12', $user?->getBirthDate()?->format('Y-m-d'));

        $reusedToken = self::api($client, 'POST', '/api/auth/verify-email', [
            'token' => $token,
        ]);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $reusedToken->getStatusCode());
    }

    public function testExistingVerifiedAccountCanSetItsMissingUsernameOnce(): void
    {
        $client = static::createClient();
        self::registerAccount($client, 'legacy@example.com');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'legacy@example.com']);
        self::assertInstanceOf(\App\Entity\User::class, $user);
        $user->setUsername(null);
        $em->flush();

        $setUsername = self::api($client, 'PUT', '/api/auth/username', ['username' => ' PENDERIE_TEST ']);
        $this->assertSame(Response::HTTP_OK, $setUsername->getStatusCode());
        $this->assertSame('penderie_test', self::decoded($setUsername)['username']);
        $this->assertSame('penderie_test', self::decoded(self::api($client, 'GET', '/api/auth/me'))['user']['username']);

        $cannotChangeUsername = self::api($client, 'PUT', '/api/auth/username', ['username' => 'another_test']);
        $this->assertSame(Response::HTTP_CONFLICT, $cannotChangeUsername->getStatusCode());
    }

    public function testSettingAnExistingUsernameIsRejected(): void
    {
        $owner = static::createClient();
        self::registerAccount($owner, 'owner@example.com');

        $legacy = self::freshClient();
        self::registerAccount($legacy, 'legacy@example.com');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'legacy@example.com']);
        self::assertInstanceOf(\App\Entity\User::class, $user);
        $user->setUsername(null);
        $em->flush();

        $response = self::api($legacy, 'PUT', '/api/auth/username', ['username' => 'owner_test']);
        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testResendRotatesTheVerificationToken(): void
    {
        $client = static::createClient();
        $registration = self::api($client, 'POST', '/api/auth/register', [
            'username' => 'camille_moreau',
            'email' => 'camille@example.com',
            'birthDate' => '1990-05-12',
            'password' => 'secret123',
            'passwordConfirm' => 'secret123',
        ]);
        $this->assertSame(Response::HTTP_ACCEPTED, $registration->getStatusCode());
        $firstToken = self::verificationTokenFromLastEmail();

        $tooSoon = self::api($client, 'POST', '/api/auth/resend-verification', [
            'email' => 'camille@example.com',
        ]);
        $this->assertSame(Response::HTTP_ACCEPTED, $tooSoon->getStatusCode());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement(
            'UPDATE pending_registration SET last_sent_at = :sentAt WHERE email = :email',
            ['sentAt' => '2000-01-01 00:00:00', 'email' => 'camille@example.com'],
        );

        $resent = self::api($client, 'POST', '/api/auth/resend-verification', [
            'email' => 'camille@example.com',
        ]);
        $this->assertSame(Response::HTTP_ACCEPTED, $resent->getStatusCode());
        $secondToken = self::verificationTokenFromLastEmail();
        $this->assertNotSame($firstToken, $secondToken);

        $oldLink = self::api($client, 'POST', '/api/auth/verify-email', [
            'token' => $firstToken,
        ]);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $oldLink->getStatusCode());

        $validLink = self::api($client, 'POST', '/api/auth/verify-email', [
            'token' => $secondToken,
        ]);
        $this->assertSame(Response::HTTP_OK, $validLink->getStatusCode());
    }

    public function testExpiredVerificationTokenCannotCreateAnAccount(): void
    {
        $client = static::createClient();
        $registration = self::api($client, 'POST', '/api/auth/register', [
            'username' => 'camille_moreau',
            'email' => 'camille@example.com',
            'birthDate' => '1990-05-12',
            'password' => 'secret123',
            'passwordConfirm' => 'secret123',
        ]);
        $this->assertSame(Response::HTTP_ACCEPTED, $registration->getStatusCode());
        $token = self::verificationTokenFromLastEmail();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement(
            'UPDATE pending_registration SET expires_at = :expiresAt WHERE email = :email',
            ['expiresAt' => '2000-01-01 00:00:00', 'email' => 'camille@example.com'],
        );

        $expired = self::api($client, 'POST', '/api/auth/verify-email', [
            'token' => $token,
        ]);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $expired->getStatusCode());
        $this->assertSame(0, $em->getRepository(\App\Entity\User::class)->count([]));
    }

    public function testRegistrationDoesNotRevealAnExistingEmail(): void
    {
        $client = static::createClient();
        self::registerAccount($client);

        $other = self::freshClient();

        $response = self::api($other, 'POST', '/api/auth/register', [
            'username' => 'camille_moreau',
            'email' => 'camille@example.com',
            'birthDate' => '1990-05-12',
            'password' => 'secret123',
            'passwordConfirm' => 'secret123',
        ]);

        $this->assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        $this->assertArrayNotHasKey('user', self::decoded($response));
    }

    public function testRegisterValidatesFields(): void
    {
        $client = static::createClient();

        $response = self::api($client, 'POST', '/api/auth/register', [
            'username' => 'valid_user',
            'email' => 'not-an-email',
            'birthDate' => '1990-05-12',
            'password' => 'secret123',
            'passwordConfirm' => 'secret123',
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $payload = self::decoded($response);
        $this->assertSame('validation_failed', $payload['error']['code']);
        $this->assertArrayHasKey('email', $payload['error']['violations']);
    }

    public function testRegistrationRejectsUsersUnderEighteen(): void
    {
        $client = static::createClient();
        $birthDate = (new \DateTimeImmutable('today'))->modify('-17 years')->format('Y-m-d');

        $response = self::api($client, 'POST', '/api/auth/register', [
            'username' => 'young_user',
            'email' => 'young@example.com',
            'birthDate' => $birthDate,
            'password' => 'secret123',
            'passwordConfirm' => 'secret123',
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertArrayHasKey('birthDate', self::decoded($response)['error']['violations']);
        $this->assertSame(0, static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(PendingRegistration::class)
            ->count([]));
    }

    public function testRegistrationRequiresUniqueUsernames(): void
    {
        $client = static::createClient();
        $registered = self::api($client, 'POST', '/api/auth/register', [
            'username' => 'camille_moreau',
            'email' => 'camille@example.com',
            'birthDate' => '1990-05-12',
            'password' => 'secret123',
            'passwordConfirm' => 'secret123',
        ]);
        $this->assertSame(Response::HTTP_ACCEPTED, $registered->getStatusCode());
        self::api($client, 'POST', '/api/auth/verify-email', [
            'token' => self::verificationTokenFromLastEmail(),
        ]);

        $duplicate = self::api(self::freshClient(), 'POST', '/api/auth/register', [
            'username' => 'CAMILLE_MOREAU',
            'email' => 'sophie@example.com',
            'birthDate' => '1991-06-13',
            'password' => 'secret123',
            'passwordConfirm' => 'secret123',
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $duplicate->getStatusCode());
        $this->assertStringContainsString('username', strtolower(self::decoded($duplicate)['error']['message']));
    }

    public function testRegistrationRejectsMismatchedPasswords(): void
    {
        $client = static::createClient();
        $response = self::api($client, 'POST', '/api/auth/register', [
            'username' => 'valid_user',
            'email' => 'camille@example.com',
            'birthDate' => '1990-05-12',
            'password' => 'secret123',
            'passwordConfirm' => 'different123',
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
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
        // Single foyer: login restores it right away (no picker needed).
        $this->assertSame('Mon dressing', $payload['household']['name']);
        $this->assertNotNull($payload['activeProfileId']);
        $this->assertCount(1, $payload['profiles'] ?? []);
        $this->assertSame('Moi', $payload['profiles'][0]['name']);
    }

    public function testLoginWithoutHouseholdReturnsEmptyState(): void
    {
        $client = static::createClient();
        self::registerAccount($client);
        self::removeHouseholdMemberships('camille@example.com');

        $logout = self::api($client, 'POST', '/api/auth/logout');
        $this->assertSame(Response::HTTP_NO_CONTENT, $logout->getStatusCode());

        $good = self::api($client, 'POST', '/api/auth/login', [
            'email' => 'camille@example.com',
            'password' => 'secret123',
        ]);
        $this->assertSame(Response::HTTP_OK, $good->getStatusCode());

        $payload = self::decoded($good);
        $this->assertSame([], $payload['households']);
        $this->assertNull($payload['household']);
        $this->assertNull($payload['activeProfileId']);
    }

    public function testLoginWithSeveralHouseholdsLetsTheUserPick(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $second = self::api($client, 'POST', '/api/auth/households', [
            'householdName' => 'Appartement Lyon',
            'firstName' => 'Camille',
        ]);
        $this->assertSame(Response::HTTP_CREATED, $second->getStatusCode());
        $secondHouseholdId = self::decoded($second)['household']['id'];

        $logout = self::api($client, 'POST', '/api/auth/logout');
        $this->assertSame(Response::HTTP_NO_CONTENT, $logout->getStatusCode());

        $good = self::api($client, 'POST', '/api/auth/login', [
            'email' => 'camille@example.com',
            'password' => 'secret123',
        ]);
        $this->assertSame(Response::HTTP_OK, $good->getStatusCode());

        $payload = self::decoded($good);
        $this->assertCount(2, $payload['households']);
        $this->assertNull($payload['household']);
        $this->assertNull($payload['activeProfileId']);

        $select = self::api($client, 'POST', '/api/auth/households/'.$secondHouseholdId.'/select');
        $this->assertSame(Response::HTTP_OK, $select->getStatusCode());
        $this->assertSame($secondHouseholdId, self::decoded($select)['activeHouseholdId']);

        $me = self::api($client, 'GET', '/api/auth/me');
        $this->assertSame('Appartement Lyon', self::decoded($me)['household']['name']);
    }

    public function testMeRequiresAuthentication(): void
    {
        $client = static::createClient();

        $response = self::api($client, 'GET', '/api/auth/me');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('unauthenticated', self::decoded($response)['error']['code']);
    }

    public function testPinUnlockRequiresTheAdultAccountAndStaysWithinItsSession(): void
    {
        $parent = static::createClient();
        self::registerHousehold($parent);

        $created = self::api($parent, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Léo',
            'pin' => '1234',
        ]);

        $this->assertSame(Response::HTTP_CREATED, $created->getStatusCode());
        $childProfile = self::decoded($created)['profile'];
        $this->assertSame('child', $childProfile['role']);
        $this->assertTrue($childProfile['hasPin']);

        $bypassPin = self::api($parent, 'POST', '/api/auth/profiles/'.$childProfile['id'].'/switch');
        $this->assertSame(Response::HTTP_FORBIDDEN, $bypassPin->getStatusCode());

        $anonymous = self::freshClient();

        $login = self::api($anonymous, 'POST', '/api/auth/profile-pin', [
            'profileId' => $childProfile['id'],
            'pin' => '1234',
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $login->getStatusCode());

        $unlocked = self::api($parent, 'POST', '/api/auth/profile-pin', [
            'profileId' => $childProfile['id'],
            'pin' => '1234',
        ]);
        $this->assertSame(Response::HTTP_OK, $unlocked->getStatusCode());
        $this->assertSame($childProfile['id'], self::decoded($unlocked)['activeProfileId']);

        $me = self::decoded(self::api($parent, 'GET', '/api/auth/me'));
        $this->assertSame($childProfile['id'], $me['activeProfileId']);
        $this->assertSame('Léo', $me['activeProfile']['name']);
        $this->assertSame('camille@example.com', $me['user']['email']);

        $wrongPin = self::api($parent, 'POST', '/api/auth/profile-pin', [
            'profileId' => $childProfile['id'],
            'pin' => '9999',
        ]);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $wrongPin->getStatusCode());
    }

    public function testParentCanSwitchProfilesWithoutAnOptionalPin(): void
    {
        $parent = static::createClient();
        self::registerHousehold($parent);

        $created = self::api($parent, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Léo',
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
        self::registerHousehold($parent);

        $created = self::api($parent, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Léo',
            'pin' => '1234',
        ]);
        $childId = self::decoded($created)['profile']['id'];

        $anonymous = self::freshClient();
        $login = self::api($anonymous, 'POST', '/api/auth/profile-pin', [
            'profileId' => $childId,
            'pin' => '1234',
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $login->getStatusCode());
    }

    public function testSwitchToUnknownProfileReturns404(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $response = self::api($client, 'POST', '/api/auth/profiles/999999/switch');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testHouseholdProfileEnumerationIsUnavailableWithoutAuthentication(): void
    {
        $parent = static::createClient();
        $household = self::registerHousehold($parent);

        self::api($parent, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Léo',
            'pin' => '1234',
        ]);

        $anon = self::freshClient();
        $response = self::api($anon, 'GET', '/api/auth/households/'.$household['household']['slug'].'/profiles');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
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
