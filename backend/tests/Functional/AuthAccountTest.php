<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\PendingRegistration;
use App\Entity\User;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuthService business guards that no existing happy-path suite reaches:
 * registration throttling/refresh, the username claim rules, verification
 * token failures, household/profile creation validation and profile switch
 * authorisation (PIN + child restrictions).
 */
final class AuthAccountTest extends ApiTestCase
{
    private const REGISTER_PAYLOAD = [
        'username' => 'camille_test',
        'email' => 'camille@example.com',
        'birthDate' => '1990-05-12',
        'password' => 'secret123',
        'passwordConfirm' => 'secret123',
    ];

    public function testRepeatedRegistrationInsideAMinuteStopsResending(): void
    {
        $client = static::createClient();

        $first = self::api($client, 'POST', '/api/auth/register', self::REGISTER_PAYLOAD);
        self::assertSame(Response::HTTP_ACCEPTED, $first->getStatusCode());
        self::assertCount(1, static::getMailerMessages(), 'The first registration must send a verification e-mail.');

        $second = self::api($client, 'POST', '/api/auth/register', self::REGISTER_PAYLOAD);
        self::assertSame(Response::HTTP_ACCEPTED, $second->getStatusCode());
        self::assertCount(
            0,
            static::getMailerMessages(),
            'A re-registration inside the throttle window must not send a second e-mail.',
        );
    }

    public function testRegistrationRefreshesAPendingTokenAfterTheThrottleWindow(): void
    {
        $client = static::createClient();
        self::api($client, 'POST', '/api/auth/register', self::REGISTER_PAYLOAD);
        $firstToken = self::verificationTokenFromLastEmail();

        $em = $this->entityManager();
        $pending = $em->getRepository(PendingRegistration::class)->findOneBy(['email' => self::REGISTER_PAYLOAD['email']]);
        self::assertInstanceOf(PendingRegistration::class, $pending);
        $age = new \ReflectionProperty(PendingRegistration::class, 'lastSentAt');
        $age->setValue($pending, new \DateTimeImmutable('-2 minutes'));
        $em->flush();

        $again = self::api($client, 'POST', '/api/auth/register', self::REGISTER_PAYLOAD);
        self::assertSame(Response::HTTP_ACCEPTED, $again->getStatusCode());
        $secondToken = self::verificationTokenFromLastEmail();
        self::assertNotSame($firstToken, $secondToken, 'The pending token must rotate after the throttle window.');

        $verified = self::api($client, 'POST', '/api/auth/verify-email', ['token' => $secondToken]);
        self::assertSame(Response::HTTP_OK, $verified->getStatusCode());
    }

    public function testUnverifiedAccountCannotClaimAUsername(): void
    {
        $client = static::freshClient();
        $client->loginUser($this->persistedUser('unverified@example.com', verified: false));

        $response = self::api($client, 'PUT', '/api/auth/username', ['username' => 'sneaky_name']);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertStringContainsString('Verify your e-mail', (string) self::decoded($response)['error']['message']);
    }

    public function testUsernameCannotBeChangedTwice(): void
    {
        $client = static::freshClient();
        $client->loginUser($this->persistedUser('claimed@example.com', verified: true, username: 'already_taken'));

        $response = self::api($client, 'PUT', '/api/auth/username', ['username' => 'second_choice']);

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('already set', (string) self::decoded($response)['error']['message']);
    }

    public function testUsernameFormatIsValidated(): void
    {
        $client = static::freshClient();
        $client->loginUser($this->persistedUser('fresh@example.com', verified: true));

        $response = self::api($client, 'PUT', '/api/auth/username', ['username' => 'ab']);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('3 to 30', (string) self::decoded($response)['error']['message']);
    }

    public function testUsernameHeldByAPendingRegistrationIsRejected(): void
    {
        $holder = static::createClient();
        self::api($holder, 'POST', '/api/auth/register', [
            'username' => 'reserved_name',
            'email' => 'reserved@example.com',
            'birthDate' => '1990-05-12',
            'password' => 'secret123',
            'passwordConfirm' => 'secret123',
        ]);

        $client = static::freshClient();
        $client->loginUser($this->persistedUser('claimer@example.com', verified: true));

        $response = self::api($client, 'PUT', '/api/auth/username', ['username' => 'reserved_name']);

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('already in use', (string) self::decoded($response)['error']['message']);
    }

    public function testVerificationTokenShapeIsChecked(): void
    {
        $client = static::createClient();

        $response = self::api($client, 'POST', '/api/auth/verify-email', ['token' => 'not-a-token']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testVerificationRefusesIncompletePendingRegistrations(): void
    {
        $client = static::createClient();
        $token = str_repeat('a', 64);
        $this->persistPending($token, username: null, birthDate: null);

        $response = self::api($client, 'POST', '/api/auth/verify-email', ['token' => $token]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('incomplete', (string) self::decoded($response)['error']['message']);
    }

    public function testVerificationRefusesAUsernameTakenInMeantime(): void
    {
        $client = static::createClient();
        self::registerAccount($client);

        $token = str_repeat('b', 64);
        $this->persistPending($token, username: 'camille_test', birthDate: '1990-05-12');

        $response = self::api($client, 'POST', '/api/auth/verify-email', ['token' => $token]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('no longer available', (string) self::decoded($response)['error']['message']);
    }

    public function testHouseholdCreationValidationAndUnknownSelection(): void
    {
        $client = static::createClient();
        self::registerAccount($client);

        $invalid = self::api($client, 'POST', '/api/auth/households', [
            'householdName' => 'Coloc',
            'firstName' => '',
        ]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $invalid->getStatusCode());

        $missing = self::api($client, 'POST', '/api/auth/households/999999/select');
        self::assertSame(Response::HTTP_NOT_FOUND, $missing->getStatusCode());

        $created = self::api($client, 'POST', '/api/auth/households', [
            'householdName' => '!!!',
            'firstName' => 'Camille',
        ]);
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode());
        self::assertSame('foyer', self::decoded($created)['household']['slug'], 'A name without slug letters falls back to "foyer".');
    }

    public function testChildProfileCreationValidatesPinAndName(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $noName = self::api($client, 'POST', '/api/auth/profiles', ['role' => 'child', 'name' => '']);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $noName->getStatusCode());

        $badPin = self::api($client, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Léo',
            'pin' => 'abc',
        ]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $badPin->getStatusCode());
        self::assertStringContainsString('4 to 6 digits', (string) self::decoded($badPin)['error']['message']);
    }

    public function testPartnerProfileCreationValidatesItsInputs(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $badEmail = self::api($client, 'POST', '/api/auth/profiles', [
            'role' => 'partner',
            'name' => 'Alex',
            'email' => 'not-an-email',
            'password' => 'secret123',
        ]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $badEmail->getStatusCode());
        self::assertStringContainsString('valid e-mail', (string) self::decoded($badEmail)['error']['message']);

        $usedEmail = self::api($client, 'POST', '/api/auth/profiles', [
            'role' => 'partner',
            'name' => 'Alex',
            'email' => 'camille@example.com',
            'password' => 'secret123',
        ]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $usedEmail->getStatusCode());
        self::assertStringContainsString('already registered', (string) self::decoded($usedEmail)['error']['message']);

        $shortPassword = self::api($client, 'POST', '/api/auth/profiles', [
            'role' => 'partner',
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'password' => 'court',
        ]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $shortPassword->getStatusCode());
        self::assertStringContainsString('at least 8', (string) self::decoded($shortPassword)['error']['message']);

        $badPin = self::api($client, 'POST', '/api/auth/profiles', [
            'role' => 'partner',
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'password' => 'secret123',
            'pin' => 'abc',
        ]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $badPin->getStatusCode());
        self::assertStringContainsString('4 to 6 digits', (string) self::decoded($badPin)['error']['message']);

        $created = self::api($client, 'POST', '/api/auth/profiles', [
            'role' => 'partner',
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'password' => 'secret123',
            'pin' => '1234',
        ]);
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode());
        self::assertTrue(self::decoded($created)['profile']['hasPin']);
    }

    public function testProfileSwitchHonoursPinAndChildRestrictions(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $pinnedChild = self::decoded(self::api($client, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Léo',
            'pin' => '1234',
        ]))['profile']['id'];
        $freeChildA = self::decoded(self::api($client, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Jade',
        ]))['profile']['id'];
        $freeChildB = self::decoded(self::api($client, 'POST', '/api/auth/profiles', [
            'role' => 'child',
            'name' => 'Noé',
        ]))['profile']['id'];

        $needsPin = self::api($client, 'POST', '/api/auth/profiles/'.$pinnedChild.'/switch');
        self::assertSame(Response::HTTP_FORBIDDEN, $needsPin->getStatusCode());
        self::assertStringContainsString('requires its PIN', (string) self::decoded($needsPin)['error']['message']);

        $switched = self::api($client, 'POST', '/api/auth/profiles/'.$freeChildA.'/switch');
        self::assertSame(Response::HTTP_OK, $switched->getStatusCode());

        $whileChild = self::api($client, 'POST', '/api/auth/profiles/'.$freeChildB.'/switch');
        self::assertSame(Response::HTTP_FORBIDDEN, $whileChild->getStatusCode());
        self::assertStringContainsString('cannot switch', (string) self::decoded($whileChild)['error']['message']);
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function persistedUser(string $email, bool $verified, ?string $username = null): User
    {
        $user = new User($email, 'Camille', $username);
        $user->setEmailVerified($verified);
        $user->setPassword('$2y$14$abcdefghijklmnopqrstuv');

        $em = $this->entityManager();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function persistPending(string $token, ?string $username, ?string $birthDate): void
    {
        $pending = new PendingRegistration(
            'pending@example.com',
            '$2y$14$abcdefghijklmnopqrstuv',
            hash('sha256', $token),
            new \DateTimeImmutable('+1 hour'),
            $username,
            null !== $birthDate ? new \DateTimeImmutable($birthDate) : null,
        );

        $em = $this->entityManager();
        $em->persist($pending);
        $em->flush();
    }
}
