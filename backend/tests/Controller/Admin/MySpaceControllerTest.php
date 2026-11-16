<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Item;
use App\Entity\Loan;
use App\Entity\PageVisit;
use App\Entity\Profile;
use App\Entity\User;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class MySpaceControllerTest extends ApiTestCase
{
    public function testStatsOnlyCountsLoansInTheActiveHousehold(): void
    {
        $client = static::createClient();
        $firstHome = self::registerHousehold($client, 'parent@example.com');
        $firstItemId = self::decoded(self::api($client, 'POST', '/api/items', ['name' => 'First home item']))['item']['id'];

        $secondHomeResponse = self::api($client, 'POST', '/api/auth/households', [
            'householdName' => 'Second home',
            'firstName' => 'Parent',
        ]);
        self::assertSame(201, $secondHomeResponse->getStatusCode());
        $secondHome = self::decoded($secondHomeResponse);
        $secondItemId = self::decoded(self::api($client, 'POST', '/api/items', ['name' => 'Second home item']))['item']['id'];

        self::assertSame(200, self::api($client, 'POST', '/api/auth/households/'.$firstHome['household']['id'].'/select')->getStatusCode());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'parent@example.com']);
        $firstItem = $em->getRepository(Item::class)->find($firstItemId);
        $secondItem = $em->getRepository(Item::class)->find($secondItemId);
        self::assertInstanceOf(User::class, $user);
        self::assertInstanceOf(Item::class, $firstItem);
        self::assertInstanceOf(Item::class, $secondItem);
        self::assertNotSame($firstHome['household']['id'], $secondHome['household']['id']);

        $firstLoan = new Loan($firstItem, $user, $user, new \DateTimeImmutable('+7 days'), '');
        $firstLoan->status = 'active';
        $secondLoan = new Loan($secondItem, $user, $user, new \DateTimeImmutable('+7 days'), '');
        $secondLoan->status = 'active';
        $em->persist($firstLoan);
        $em->persist($secondLoan);
        $em->flush();

        $stats = self::decoded(self::api($client, 'GET', '/api/me/stats'));

        self::assertSame(1, $stats['activeLoans']);
    }

    public function testDashboardSeparatesProfilesAndLoginAccountsAndFiltersActivity(): void
    {
        $client = static::createClient();
        $home = self::registerHousehold($client, 'parent@example.com', 'Test Foyer');
        $item = self::decoded(self::api($client, 'POST', '/api/items', ['name' => 'Veste noire']))['item'];
        self::api($client, 'POST', '/api/auth/profiles', ['role' => 'child', 'name' => 'Léo']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'parent@example.com']);
        $profile = $em->getRepository(Profile::class)->find($home['profile']['id']);
        self::assertInstanceOf(User::class, $user);
        self::assertInstanceOf(Profile::class, $profile);
        $household = $profile->getHousehold();

        $em->persist(new ActivityLog(
            'item.updated',
            $household,
            $user,
            $profile,
            'item',
            $item['id'],
            ['path' => '/api/items/'.$item['id'], 'changes' => ['color', 'room', 'untrusted']],
        ));
        $em->persist(new ActivityLog(
            'auth.login',
            $household,
            $user,
            $profile,
            'user',
            null,
            ['path' => '/api/auth/login'],
        ));
        $em->flush();

        $space = self::decoded(self::api($client, 'GET', '/api/me/space'));
        self::assertSame(2, $space['stats']['profiles']);
        self::assertCount(2, $space['profiles']);
        self::assertCount(1, $space['members']);

        $updated = array_values(array_filter(
            $space['activity'],
            static fn (array $activity): bool => 'item.updated' === $activity['action'],
        ));
        self::assertCount(1, $updated);
        self::assertSame('Moi', $updated[0]['actor']);
        self::assertSame('Veste noire', $updated[0]['objectName']);
        self::assertSame(['color', 'room'], $updated[0]['changes']);
        self::assertSame($item['id'], $updated[0]['itemId']);
        self::assertTrue($updated[0]['canEdit']);
        self::assertSame(['id', 'action', 'actor', 'objectName', 'createdAt', 'changes', 'itemId', 'canEdit'], array_keys($updated[0]));
        self::assertNotContains('auth.login', array_column($space['activity'], 'action'));

        $activity = self::decoded(self::api($client, 'GET', '/api/me/activity'));
        self::assertNotContains('auth.login', array_column($activity, 'action'));
        foreach ($activity as $entry) {
            self::assertArrayNotHasKey('path', $entry);
        }
    }

    public function testActivityDismissalIsPrivateAndPreservesTheAuditEntry(): void
    {
        $client = static::createClient();
        $home = self::registerHousehold($client, 'parent@example.com');
        $item = self::decoded(self::api($client, 'POST', '/api/items', ['name' => 'Veste noire']))['item'];
        self::api($client, 'PATCH', '/api/items/'.$item['id'], ['color' => 'rouge']);

        $space = self::decoded(self::api($client, 'GET', '/api/me/space'));
        $updated = array_values(array_filter(
            $space['activity'],
            static fn (array $activity): bool => 'item.updated' === $activity['action'],
        ));
        self::assertCount(1, $updated);
        self::assertSame(['color'], $updated[0]['changes']);
        $activityId = $updated[0]['id'];

        self::assertSame(204, self::api($client, 'DELETE', '/api/me/space/activity/'.$activityId)->getStatusCode());
        $spaceAfterDismissal = self::decoded(self::api($client, 'GET', '/api/me/space'));
        self::assertNotContains($activityId, array_column($spaceAfterDismissal['activity'], 'id'));
        self::assertSame(204, self::api($client, 'DELETE', '/api/me/space/activity/'.$activityId)->getStatusCode());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(ActivityLog::class, $em->getRepository(ActivityLog::class)->find($activityId));

        static::ensureKernelShutdown();
        $otherClient = static::createClient();
        self::registerHousehold($otherClient, 'other-parent@example.com');
        self::assertSame(404, self::api($otherClient, 'DELETE', '/api/me/space/activity/'.$activityId)->getStatusCode());
    }

    public function testAdminItemDetailsRequireSuperAdminAndStayAggregateOnly(): void
    {
        $client = static::createClient();
        self::registerHousehold($client, 'owner@example.com');
        self::api($client, 'POST', '/api/items', ['name' => 'Private winter coat', 'notes' => 'Private note']);

        $client->request('GET', '/admin/metrics/items');
        self::assertSame(403, $client->getResponse()->getStatusCode());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'owner@example.com']);
        self::assertInstanceOf(User::class, $user);
        $user->promote('ROLE_SUPER_ADMIN');
        $em->flush();
        self::signInAsPromotedAccount($client, 'owner@example.com');

        $client->request('GET', '/admin/metrics/items?period=all', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Private winter coat', $content);
        self::assertStringNotContainsString('Private note', $content);
        self::assertStringContainsString('Items added by hour', $content);
    }

    public function testAdminVisitorDetailsExposeCountsButNotVisitorIdentifiers(): void
    {
        $client = static::createClient();
        $home = self::registerHousehold($client, 'owner@example.com');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'owner@example.com']);
        $profile = $em->getRepository(Profile::class)->find($home['profile']['id']);
        self::assertInstanceOf(User::class, $user);
        self::assertInstanceOf(Profile::class, $profile);
        $visitorId = hash('sha256', 'browser-id');
        $em->persist(new PageVisit('items', $visitorId, $profile->getHousehold(), 'fr', true));
        $em->persist(new PageVisit('items', $visitorId, $profile->getHousehold(), 'fr', true));
        $user->promote('ROLE_SUPER_ADMIN');
        $em->flush();
        self::signInAsPromotedAccount($client, 'owner@example.com');

        $client->request('GET', '/admin/metrics/visits30d?period=all', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString($visitorId, $content);
        $detail = self::decoded($client->getResponse());
        self::assertSame(2, $detail['rows'][0]['views']);
        self::assertSame(1, $detail['rows'][0]['visitors']);
    }

    public function testAdminDashboardRendersClickableMetricCardsAndSelectedPeriod(): void
    {
        $client = static::createClient();
        self::registerHousehold($client, 'owner@example.com');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'owner@example.com']);
        self::assertInstanceOf(User::class, $user);
        $user->promote('ROLE_SUPER_ADMIN');
        $em->flush();
        self::signInAsPromotedAccount($client, 'owner@example.com');

        $client->request('GET', '/admin?period=90d');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('data-detail-url="/admin/metrics/items?period=90d"', $content);
        self::assertStringContainsString('<dialog', $content);
        self::assertStringContainsString('value="90d" selected', $content);
    }

    public function testAdminJournalHasFilteredPaginationForOlderActivity(): void
    {
        $client = static::createClient();
        $home = self::registerHousehold($client, 'owner@example.com');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'owner@example.com']);
        $profile = $em->getRepository(Profile::class)->find($home['profile']['id']);
        self::assertInstanceOf(User::class, $user);
        self::assertInstanceOf(Profile::class, $profile);

        for ($index = 0; $index < 51; ++$index) {
            $em->persist(new ActivityLog('item.updated', $profile->getHousehold(), $user, $profile, 'item', $index + 1));
        }
        $user->promote('ROLE_SUPER_ADMIN');
        $em->flush();
        self::signInAsPromotedAccount($client, 'owner@example.com');

        $client->request('GET', '/admin/journal?action=item.updated&page=2');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertMatchesRegularExpression('/2\\s*(of|sur|\\/)\\s*2/', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('/api/', (string) $client->getResponse()->getContent());
    }

    private static function signInAsPromotedAccount(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email): void
    {
        $client->request(
            'POST',
            '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => 'secret123'], JSON_THROW_ON_ERROR),
        );

        self::assertSame(200, $client->getResponse()->getStatusCode());
    }
}
