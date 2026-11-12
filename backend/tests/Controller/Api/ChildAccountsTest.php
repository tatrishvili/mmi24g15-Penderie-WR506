<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class ChildAccountsTest extends ApiTestCase
{
    private const INITIAL = 'Parent gives a safe password 2026';
    private const PASSWORD = 'My own private long password 2026';

    /** @return array<string, mixed> */
    private function setupChild(KernelBrowser $parent, string $name = 'Emma'): array
    {
        $username = 'kid_'.substr(hash('sha256', $this->name().$name), 0, 12);
        $response = self::api($parent, 'POST', '/api/children', ['name' => $name, 'username' => $username, 'password' => self::INITIAL, 'age' => 13, 'relationship' => 'mother']);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        return self::decoded($response);
    }

    private function childLogin(string $username): KernelBrowser
    {
        $client = self::freshClient();
        $login = self::api($client, 'POST', '/api/auth/child-login', ['username' => $username, 'password' => self::INITIAL]);
        self::assertSame(200, $login->getStatusCode(), (string) $login->getContent());
        self::assertSame(403, self::api($client, 'GET', '/api/items')->getStatusCode());
        $changed = self::api($client, 'POST', '/api/auth/child-password', ['currentPassword' => self::INITIAL, 'password' => self::PASSWORD]);
        self::assertSame(200, $changed->getStatusCode(), (string) $changed->getContent());
        self::assertSame(200, self::api($client, 'POST', '/api/auth/child-login', ['username' => $username, 'password' => self::PASSWORD])->getStatusCode());

        return $client;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function item(KernelBrowser $client, string $name, array $extra = []): array
    {
        $response = self::api($client, 'POST', '/api/items', ['name' => $name, ...$extra]);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        return self::decoded($response)['item'];
    }

    public function testIndependentChildSessionIsRestrictedAndParentCanRevokeIt(): void
    {
        $parent = static::createClient();
        $home = self::registerHousehold($parent);
        $kid = $this->setupChild($parent);
        $child = $this->childLogin($kid['username']);
        $me = self::decoded(self::api($child, 'GET', '/api/auth/me'));
        self::assertArrayNotHasKey('user', $me);
        self::assertCount(1, $me['profiles']);
        self::assertSame($kid['profileId'], $me['activeProfileId']);
        $own = $this->item($child, 'My jumper');
        self::assertSame($kid['profileId'], $own['ownerProfile']['id']);
        foreach (['/api/friends', '/api/social/workspace', '/api/children'] as $uri) {
            self::assertSame(403, self::api($child, 'GET', $uri)->getStatusCode(), $uri);
        }
        self::assertSame(403, self::api($child, 'POST', '/api/auth/households/'.$home['household']['id'].'/select')->getStatusCode());
        self::assertSame(403, self::api($child, 'POST', '/api/auth/profiles/'.$home['profile']['id'].'/switch')->getStatusCode());
        self::assertSame(403, self::api($child, 'DELETE', '/api/items/'.$own['id'])->getStatusCode());
        self::assertSame(403, self::api($child, 'PATCH', '/api/items/'.$own['id'], ['status' => 'loue'])->getStatusCode());
        self::assertSame(200, self::api($parent, 'PATCH', '/api/children/'.$kid['profileId'].'/access', ['enabled' => false])->getStatusCode());
        self::assertSame(401, self::api($child, 'GET', '/api/items')->getStatusCode());
        self::assertSame(401, self::api(self::freshClient(), 'POST', '/api/auth/child-login', ['username' => $kid['username'], 'password' => self::PASSWORD])->getStatusCode());
    }

    public function testRepeatedRoomsAndSharedBedroomsDoNotGrantInventoryAccess(): void
    {
        $parent = static::createClient();
        $home = self::registerHousehold($parent);
        $emma = $this->setupChild($parent);
        $lucas = $this->setupChild($parent, 'Lucas');
        $roomIds = [];
        foreach ([['Parents bedroom', [], false], ['Emma bedroom', [$emma['profileId']], false], ['Lucas bedroom', [$lucas['profileId']], false], ['Shared bedroom', [$emma['profileId'], $lucas['profileId']], false], ['Upstairs bathroom', [], true], ['Parents bathroom', [], false], ['Garage 1', [], true]] as [$name, $ids, $common]) {
            $response = self::api($parent, 'POST', '/api/places/'.$home['place']['id'].'/rooms', ['name' => $name, 'kind' => str_contains($name, 'bathroom') ? 'bathroom' : 'bedroom', 'common' => $common, 'assignedProfileIds' => $ids]);
            self::assertSame(201, $response->getStatusCode());
            $roomIds[$name] = self::decoded($response)['room']['id'];
        }
        $parentItem = $this->item($parent, 'Parents private jacket', ['roomId' => $roomIds['Shared bedroom']]);
        $child = $this->childLogin($emma['username']);
        $names = array_column(self::decoded(self::api($child, 'GET', '/api/places'))['places'][0]['rooms'], 'name');
        self::assertSame(['Emma bedroom', 'Shared bedroom', 'Upstairs bathroom', 'Garage 1'], $names);
        self::assertSame(404, self::api($child, 'GET', '/api/items/'.$parentItem['id'])->getStatusCode());
        self::assertSame(404, self::api($child, 'POST', '/api/items', ['name' => 'Wrong room', 'roomId' => $roomIds['Parents bedroom']])->getStatusCode());
        $own = $this->item($child, 'My book', ['roomId' => $roomIds['Shared bedroom']]);
        self::assertSame(200, self::api($parent, 'PATCH', '/api/rooms/'.$roomIds['Shared bedroom'], ['assignedProfileIds' => [$lucas['profileId']]])->getStatusCode());
        $hiddenLocation = self::decoded(self::api($child, 'GET', '/api/items/'.$own['id']))['item'];
        self::assertNull($hiddenLocation['room']);
        self::assertNull($hiddenLocation['storageLocation']);
        $edit = self::decoded(self::api($child, 'PATCH', '/api/items/'.$own['id'], ['name' => 'Book renamed']))['item'];
        self::assertNull($edit['room']);
    }

    public function testParentCanSelectChildViewersWithoutGrantingEditOrPrivateFields(): void
    {
        $parent = static::createClient();
        self::registerHousehold($parent);
        $emma = $this->setupChild($parent);
        $lucas = $this->setupChild($parent, 'Lucas');
        $item = $this->item($parent, 'Visible guitar', ['notes' => 'Private insurance details', 'memory' => 'Private memory']);
        $child = $this->childLogin($emma['username']);
        $sibling = $this->childLogin($lucas['username']);
        self::assertSame(404, self::api($child, 'GET', '/api/items/'.$item['id'])->getStatusCode());
        $current = self::decoded(self::api($parent, 'GET', '/api/items/'.$item['id']))['item'];
        self::assertSame(200, self::api($parent, 'PUT', '/api/items/'.$item['id'].'/family-audience', ['audience' => 'selected', 'profileIds' => [$emma['profileId']], 'version' => $current['sharingVersion'], 'approved' => true])->getStatusCode());
        $shared = self::decoded(self::api($child, 'GET', '/api/items/'.$item['id']))['item'];
        self::assertFalse($shared['canEdit']);
        self::assertNull($shared['notes']);
        self::assertNull($shared['memory']);
        self::assertNull($shared['qrToken']);
        self::assertNull($shared['room']);
        self::assertSame(404, self::api($sibling, 'GET', '/api/items/'.$item['id'])->getStatusCode());
        self::assertSame(404, self::api($child, 'PATCH', '/api/items/'.$item['id'], ['name' => 'Stolen edit'])->getStatusCode());
        self::assertSame(404, self::api($child, 'POST', '/api/items/'.$item['id'].'/photos', ['photos' => []])->getStatusCode());
        $visible = self::decoded(self::api($child, 'GET', '/api/items?perPage=1'));
        self::assertSame(1, $visible['total']);
        self::assertCount(1, $visible['items']);
        self::assertSame(200, self::api($parent, 'PATCH', '/api/items/'.$item['id'], ['name' => 'Changed after review'])->getStatusCode());
        self::assertSame(404, self::api($child, 'GET', '/api/items/'.$item['id'])->getStatusCode());
    }

    public function testChildSharingNeedsParentReviewAndStaleApprovalsCannotPublish(): void
    {
        $parent = static::createClient();
        self::registerHousehold($parent);
        $emma = $this->setupChild($parent);
        $lucas = $this->setupChild($parent, 'Lucas');
        $child = $this->childLogin($emma['username']);
        $sibling = $this->childLogin($lucas['username']);
        $item = $this->item($child, 'My painting');
        $request = self::api($child, 'PUT', '/api/items/'.$item['id'].'/family-audience', ['audience' => 'selected', 'profileIds' => [$lucas['profileId']], 'version' => 1]);
        self::assertSame(202, $request->getStatusCode(), (string) $request->getContent());
        $approval = self::decoded($request)['approval'];
        self::assertSame(404, self::api($sibling, 'GET', '/api/items/'.$item['id'])->getStatusCode());
        self::assertSame(403, self::api($child, 'POST', '/api/child-approvals/'.$approval['id'].'/decision', ['approve' => true, 'version' => $approval['version']])->getStatusCode());
        $inbox = self::decoded(self::api($parent, 'GET', '/api/notifications'));
        self::assertSame(1, $inbox['unread']);
        self::assertSame('My painting', $inbox['notifications'][0]['detail']['proposal']['preview']['name']);
        self::assertSame(200, self::api($parent, 'POST', '/api/child-approvals/'.$approval['id'].'/decision', ['approve' => true, 'version' => $approval['version']])->getStatusCode());
        self::assertSame(200, self::api($sibling, 'GET', '/api/items/'.$item['id'])->getStatusCode());
        self::assertSame(409, self::api($parent, 'POST', '/api/child-approvals/'.$approval['id'].'/decision', ['approve' => true, 'version' => $approval['version']])->getStatusCode());
        self::assertSame(200, self::api($child, 'DELETE', '/api/child-approvals/'.$approval['id'])->getStatusCode());
        self::assertSame(404, self::api($sibling, 'GET', '/api/items/'.$item['id'])->getStatusCode());
        $current = self::decoded(self::api($child, 'GET', '/api/items/'.$item['id']))['item'];
        $second = self::decoded(self::api($child, 'PUT', '/api/items/'.$item['id'].'/family-audience', ['audience' => 'household', 'version' => $current['sharingVersion']]))['approval'];
        self::assertSame(200, self::api($child, 'PATCH', '/api/items/'.$item['id'], ['name' => 'Changed painting'])->getStatusCode());
        self::assertSame(409, self::api($parent, 'POST', '/api/child-approvals/'.$second['id'].'/decision', ['approve' => true, 'version' => $second['version']])->getStatusCode());
    }

    public function testAnotherParentNeedsAnExplicitInvitationAndAcceptance(): void
    {
        $mother = static::createClient();
        $home = self::registerHousehold($mother, 'mother@example.com');
        $kid = $this->setupChild($mother);
        $father = self::freshClient();
        self::registerHousehold($father, 'father@example.com');
        self::assertSame(404, self::api($father, 'POST', '/api/children/'.$kid['profileId'].'/parents', ['username' => 'father_test', 'relationship' => 'father'])->getStatusCode());
        $invitation = self::api($mother, 'POST', '/api/children/'.$kid['profileId'].'/parents', ['username' => 'father_test', 'relationship' => 'father']);
        self::assertSame(201, $invitation->getStatusCode(), (string) $invitation->getContent());
        $linkId = self::decoded($invitation)['id'];
        self::assertSame(403, self::api($mother, 'POST', '/api/parent-invitations/'.$linkId.'/accept')->getStatusCode());
        self::assertSame(200, self::api($father, 'POST', '/api/parent-invitations/'.$linkId.'/accept')->getStatusCode());
        self::assertSame(200, self::api($father, 'POST', '/api/auth/households/'.$home['household']['id'].'/select')->getStatusCode());
        self::assertCount(1, self::decoded(self::api($father, 'GET', '/api/children'))['children']);
        self::assertSame(200, self::api($mother, 'DELETE', '/api/parent-links/'.$linkId)->getStatusCode());
        self::assertCount(0, self::decoded(self::api($father, 'GET', '/api/children'))['children']);
        self::assertSame(403, self::api($father, 'POST', '/api/children/'.$kid['profileId'].'/credentials', ['password' => self::INITIAL])->getStatusCode());
    }

    public function testPrivateCollectionsAndContactSharesRequireBothFamiliesAndRevokePhotos(): void
    {
        $mother = static::createClient();
        self::registerHousehold($mother, 'first_parent@example.com');
        $emma = $this->setupChild($mother);
        $father = self::freshClient();
        self::registerHousehold($father, 'second_parent@example.com');
        $lucas = $this->setupChild($father, 'Lucas');
        $child = $this->childLogin($emma['username']);
        $other = $this->childLogin($lucas['username']);
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $item = $this->item($child, 'My drawing', ['notes' => 'Never share this note', 'photos' => [$png]]);
        $collection = self::decoded(self::api($child, 'POST', '/api/child-collections', ['name' => 'My art', 'itemIds' => [$item['id']]]))['collection'];
        self::assertSame([], self::decoded(self::api($other, 'GET', '/api/child-collections'))['collections']);
        self::assertSame(404, self::api($other, 'GET', '/api/child-collections?childId='.$emma['profileId'])->getStatusCode());
        self::assertCount(1, self::decoded(self::api($mother, 'GET', '/api/child-collections?childId='.$emma['profileId']))['collections']);
        $request = self::api($child, 'POST', '/api/child-connections', ['username' => $lucas['username']]);
        self::assertSame(201, $request->getStatusCode());
        $connection = self::decoded($request)['connection'];
        self::assertSame(200, self::api($other, 'POST', '/api/child-connections/'.$connection['id'], ['action' => 'accept'])->getStatusCode());
        self::assertSame(200, self::api($mother, 'POST', '/api/child-connections/'.$connection['id'], ['action' => 'approve'])->getStatusCode());
        self::assertSame(422, self::api($child, 'POST', '/api/child-approvals', ['kind' => 'collection', 'sourceId' => $collection['id'], 'version' => $collection['version'], 'recipients' => ['child:'.$lucas['profileId']]])->getStatusCode());
        self::assertSame(200, self::api($father, 'POST', '/api/child-connections/'.$connection['id'], ['action' => 'approve'])->getStatusCode());
        $requested = self::api($child, 'POST', '/api/child-approvals', ['kind' => 'collection', 'sourceId' => $collection['id'], 'version' => $collection['version'], 'recipients' => ['child:'.$lucas['profileId']]]);
        self::assertSame(202, $requested->getStatusCode(), (string) $requested->getContent());
        $approval = self::decoded($requested)['approval'];
        self::assertSame([], self::decoded(self::api($other, 'GET', '/api/child-publications'))['publications']);
        self::assertSame(403, self::api($father, 'POST', '/api/child-approvals/'.$approval['id'].'/decision', ['approve' => true, 'version' => $approval['version']])->getStatusCode());
        self::assertSame(200, self::api($mother, 'POST', '/api/child-approvals/'.$approval['id'].'/decision', ['approve' => true, 'version' => $approval['version']])->getStatusCode());
        $publications = self::decoded(self::api($other, 'GET', '/api/child-publications'))['publications'];
        self::assertCount(1, $publications);
        self::assertFalse($publications[0]['canRevoke']);
        self::assertArrayNotHasKey('id', $publications[0]['items'][0]);
        self::assertArrayNotHasKey('notes', $publications[0]['items'][0]);
        self::assertArrayNotHasKey('version', $publications[0]['items'][0]);
        self::assertArrayNotHasKey('room', $publications[0]['items'][0]);
        $url = $publications[0]['items'][0]['photos'][0]['url'];
        $photo = self::api($other, 'GET', $url);
        self::assertSame(200, $photo->getStatusCode());
        self::assertStringContainsString('no-store', (string) $photo->headers->get('Cache-Control'));
        self::assertSame(404, self::api($other, 'GET', '/api/items/'.$item['id'])->getStatusCode());
        self::assertSame(200, self::api($other, 'POST', '/api/child-connections/'.$connection['id'], ['action' => 'revoke'])->getStatusCode());
        self::assertSame([], self::decoded(self::api($other, 'GET', '/api/child-publications'))['publications']);
        self::assertSame(404, self::api($other, 'GET', $url)->getStatusCode());
        $renewed = self::decoded(self::api($child, 'POST', '/api/child-connections', ['username' => $lucas['username']]))['connection'];
        self::assertSame(200, self::api($other, 'POST', '/api/child-connections/'.$renewed['id'], ['action' => 'accept'])->getStatusCode());
        self::assertSame(200, self::api($mother, 'POST', '/api/child-connections/'.$renewed['id'], ['action' => 'approve'])->getStatusCode());
        self::assertSame(200, self::api($father, 'POST', '/api/child-connections/'.$renewed['id'], ['action' => 'approve'])->getStatusCode());
        self::assertSame([], self::decoded(self::api($other, 'GET', '/api/child-publications'))['publications']);
        self::assertSame(200, self::api($child, 'PATCH', '/api/child-collections/'.$collection['id'], ['name' => 'Changed art', 'version' => $collection['version']])->getStatusCode());
        self::assertSame(409, self::api($child, 'POST', '/api/child-approvals', ['kind' => 'collection', 'sourceId' => $collection['id'], 'version' => $collection['version'], 'recipients' => ['child:'.$lucas['profileId']]])->getStatusCode());
    }

    public function testParentResetRevokesAllChildDevicesAndNotificationReadsAreScoped(): void
    {
        $parent = static::createClient();
        self::registerHousehold($parent);
        $kid = $this->setupChild($parent);
        $first = $this->childLogin($kid['username']);
        $second = self::freshClient();
        self::assertSame(200, self::api($second, 'POST', '/api/auth/child-login', ['username' => $kid['username'], 'password' => self::PASSWORD])->getStatusCode());
        self::assertSame(200, self::api($parent, 'POST', '/api/children/'.$kid['profileId'].'/credentials', ['password' => 'A fresh initial password 2026'])->getStatusCode());
        self::assertSame(401, self::api($first, 'GET', '/api/auth/me')->getStatusCode());
        self::assertSame(401, self::api($second, 'GET', '/api/items')->getStatusCode());
        $stranger = self::freshClient();
        self::registerHousehold($stranger, 'stranger@example.com');
        self::assertSame(404, self::api($stranger, 'POST', '/api/notifications/99999/read')->getStatusCode());
        self::assertSame(404, self::api($stranger, 'POST', '/api/children/'.$kid['profileId'].'/credentials', ['password' => self::INITIAL])->getStatusCode());
    }

    public function testHouseholdApprovalDoesNotSilentlyIncludeFutureChildren(): void
    {
        $parent = static::createClient();
        self::registerHousehold($parent);
        $emma = $this->setupChild($parent);
        $child = $this->childLogin($emma['username']);
        $item = $this->item($child, 'Family drawing');
        $approval = self::decoded(self::api($child, 'PUT', '/api/items/'.$item['id'].'/family-audience', ['audience' => 'household', 'version' => 1]))['approval'];
        $lucas = $this->setupChild($parent, 'Lucas');
        self::assertSame(200, self::api($parent, 'POST', '/api/child-approvals/'.$approval['id'].'/decision', ['approve' => true, 'version' => $approval['version']])->getStatusCode());
        $other = $this->childLogin($lucas['username']);
        self::assertSame(404, self::api($other, 'GET', '/api/items/'.$item['id'])->getStatusCode());
    }

    public function testAdultRelativeRequiresParentApprovalAndNeverBecomesAParent(): void
    {
        $parent = static::createClient();
        self::registerHousehold($parent);
        $kid = $this->setupChild($parent);
        $relative = self::freshClient();
        self::registerHousehold($relative, 'aunt@example.com');
        $child = $this->childLogin($kid['username']);
        $connection = self::decoded(self::api($child, 'POST', '/api/child-connections', ['username' => 'aunt_test', 'relative' => true]))['connection'];
        self::assertSame(200, self::api($relative, 'POST', '/api/child-connections/'.$connection['id'], ['action' => 'accept'])->getStatusCode());
        self::assertSame(403, self::api($relative, 'POST', '/api/child-connections/'.$connection['id'], ['action' => 'approve'])->getStatusCode());
        self::assertSame(200, self::api($parent, 'POST', '/api/child-connections/'.$connection['id'], ['action' => 'approve'])->getStatusCode());
        $item = $this->item($child, 'A present for my aunt');
        $relativeId = self::decoded(self::api($relative, 'GET', '/api/auth/me'))['user']['id'];
        $request = self::api($child, 'POST', '/api/child-approvals', ['kind' => 'item', 'sourceId' => $item['id'], 'version' => 1, 'recipients' => ['adult:'.$relativeId]]);
        self::assertSame(202, $request->getStatusCode());
        $approval = self::decoded($request)['approval'];
        self::assertSame('aunt_test', $approval['proposal']['recipientNames']['adult:'.$relativeId]);
        self::assertSame(200, self::api($parent, 'POST', '/api/child-approvals/'.$approval['id'].'/decision', ['approve' => true, 'version' => $approval['version']])->getStatusCode());
        self::assertCount(1, self::decoded(self::api($relative, 'GET', '/api/child-publications'))['publications']);
        self::assertSame(404, self::api($relative, 'POST', '/api/children/'.$kid['profileId'].'/credentials', ['password' => self::INITIAL])->getStatusCode());
    }

    public function testChildAccountThrottleCannotBeBypassedByChangingIpAddresses(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        // The normal test cache is reset between requests. Keep only this
        // account limiter's real fixed-window state for the distributed-IP check.
        static::getContainer()->set('limiter.child_login_account', new \Symfony\Component\RateLimiter\RateLimiterFactory(['id' => 'child_login_account', 'policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'], new \Symfony\Component\RateLimiter\Storage\InMemoryStorage()));
        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $client->request('POST', '/api/auth/child-login', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '198.51.100.'.$attempt], content: json_encode(['username' => 'unlisted_child_throttle', 'password' => 'An incorrect long password']));
            self::assertSame($attempt <= 5 ? 401 : 429, $client->getResponse()->getStatusCode());
            if ($attempt <= 5) {
                self::assertSame('invalid_credentials', self::decoded($client->getResponse())['error']['code']);
            }
        }
    }

    public function testCredentialResetAlsoRevokesTrustedDeviceChildMode(): void
    {
        $device = static::createClient();
        self::registerHousehold($device);
        $kid = $this->setupChild($device);
        self::assertSame(200, self::api($device, 'POST', '/api/auth/profiles/'.$kid['profileId'].'/switch')->getStatusCode());
        self::assertSame(200, self::api($device, 'GET', '/api/items')->getStatusCode());
        $parent = self::freshClient();
        self::assertSame(200, self::api($parent, 'POST', '/api/auth/login', ['email' => 'camille@example.com', 'password' => 'secret123'])->getStatusCode());
        self::assertSame(200, self::api($parent, 'POST', '/api/children/'.$kid['profileId'].'/credentials', ['password' => self::INITIAL])->getStatusCode());
        self::assertSame(401, self::api($device, 'GET', '/api/items')->getStatusCode());
        $me = self::decoded(self::api($device, 'GET', '/api/auth/me'));
        self::assertTrue($me['restrictedSessionRevoked']);
        self::assertArrayNotHasKey('user', $me);
    }
}
