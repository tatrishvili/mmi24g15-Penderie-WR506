<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Item;
use App\Entity\Loan;
use App\Entity\SharedItemReport;
use App\Entity\User;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class SocialControllerTest extends ApiTestCase
{
    public function testPrivateCollectionsAndProfilePersistWithoutPublishing(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);
        $workspace = self::decoded(self::api($client, 'GET', '/api/social/workspace'));
        self::assertSame([], $workspace['collections']);
        self::assertSame(200, self::api($client, 'PATCH', '/api/social/workspace', [
            'version' => $workspace['version'], 'profile' => ['displayName' => 'Camille', 'bio' => 'Mes objets', 'photo' => ''],
        ])->getStatusCode());
        $response = self::api($client, 'POST', '/api/social/collections', ['name' => 'Vinyles', 'cover' => '']);
        self::assertSame(200, $response->getStatusCode());
        $data = self::decoded($response);
        self::assertSame('personal', $data['collections'][0]['visibility']);
        self::assertSame('Camille', $data['profile']['displayName']);
        $item = self::decoded(self::api($client, 'POST', '/api/items', ['name' => 'Disque']))['item'];
        $collection = $data['collections'][0]['id'];
        $added = self::decoded(self::api($client, 'PUT', '/api/social/collections/'.$collection.'/items/'.$item['id']));
        self::assertSame([$item['id']], $added['collections'][0]['itemIds']);
        self::assertSame(409, self::api($client, 'PATCH', '/api/social/workspace', ['version' => 0, 'pins' => []])->getStatusCode());
        self::assertSame(422, self::api($client, 'PATCH', '/api/social/workspace', ['version' => $added['version'], 'pins' => ['unknown']])->getStatusCode());
        self::assertSame(422, self::api($client, 'POST', '/api/social/collections', ['name' => '', 'cover' => ''])->getStatusCode());
    }

    public function testShareProjectionRevocationAndPersonalVeto(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner);
        $item = self::decoded(self::api($owner, 'POST', '/api/items', ['name' => 'Pull noir', 'color' => 'Noir', 'notes' => 'secret', 'memory' => 'private']))['item'];
        $url = '/api/social/items/'.$item['id'];
        self::assertSame(422, self::api($owner, 'POST', $url.'/shares', ['approved' => false])->getStatusCode());
        $share = self::decoded(self::api($owner, 'POST', $url.'/shares', ['approved' => true]));
        $token = basename($share['path']);
        $viewer = self::freshClient();
        $response = self::api($viewer, 'GET', '/api/social/shared/'.$token);
        self::assertSame(200, $response->getStatusCode());
        $public = self::decoded($response)['item'];
        self::assertSame(['name', 'type', 'color', 'photoUrl', 'owner', 'available'], array_keys($public));
        self::assertSame('Pull noir', $public['name']);
        self::assertStringNotContainsString('secret', (string) $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame(200, self::api($owner, 'PUT', $url.'/visibility', ['visibility' => 'personal'])->getStatusCode());
        self::assertSame(404, self::api($viewer, 'GET', '/api/social/shared/'.$token)->getStatusCode());
        self::assertSame(403, self::api($owner, 'POST', $url.'/shares', ['approved' => true])->getStatusCode());
        self::api($owner, 'PUT', $url.'/visibility', ['visibility' => 'private']);
        self::assertSame(404, self::api($viewer, 'GET', '/api/social/shared/'.$token)->getStatusCode());
    }

    public function testVerifiedAdultCanReportOneSharedLinkAndModerationIsReversibleAndScoped(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner, 'owner@example.com');
        $item = self::decoded(self::api($owner, 'POST', '/api/items', [
            'name' => 'Pull noir',
            'notes' => 'secret source note',
        ]))['item'];
        $share = self::decoded(self::api($owner, 'POST', '/api/social/items/'.$item['id'].'/shares', ['approved' => true]));
        $token = basename($share['path']);

        self::assertSame(404, self::api($owner, 'POST', '/api/social/shared/'.$token.'/reports', [
            'reason' => 'inappropriate',
            'details' => '',
        ])->getStatusCode());

        $viewer = static::freshClient();
        self::registerHousehold($viewer, 'reporter@example.com');
        $reportResponse = self::api($viewer, 'POST', '/api/social/shared/'.$token.'/reports', [
            'reason' => 'misleading',
            'details' => 'The presentation is misleading.',
        ]);
        self::assertSame(201, $reportResponse->getStatusCode());
        $reportId = self::decoded($reportResponse)['id'];
        self::assertSame(409, self::api($viewer, 'POST', '/api/social/shared/'.$token.'/reports', [
            'reason' => 'other',
            'details' => '',
        ])->getStatusCode());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $unverified = new User('unverified@example.com', 'Unverified');
        $unverified->setEmailVerified(false);
        $em->persist($unverified);
        $em->flush();
        $unverifiedClient = static::freshClient();
        $unverifiedClient->loginUser($unverified);
        self::assertSame(403, self::api($unverifiedClient, 'POST', '/api/social/shared/'.$token.'/reports', [
            'reason' => 'other',
            'details' => '',
        ])->getStatusCode());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $ownerUser = $em->getRepository(User::class)->findOneBy(['email' => 'owner@example.com']);
        self::assertInstanceOf(User::class, $ownerUser);
        $ownerUser->promote('ROLE_SUPER_ADMIN');
        $em->flush();
        self::assertContains('ROLE_SUPER_ADMIN', $ownerUser->getRoles());
        $admin = static::freshClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $moderator = $em->getRepository(User::class)->findOneBy(['email' => 'owner@example.com']);
        self::assertInstanceOf(User::class, $moderator);
        $admin->loginUser($moderator);

        $admin->request('GET', '/admin/signalements', ['status' => 'all']);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Pull noir', (string) $admin->getResponse()->getContent());
        self::assertStringNotContainsString('secret source note', (string) $admin->getResponse()->getContent());
        $csrfToken = $admin->getCrawler()
            ->filterXPath('//form[@action="/admin/signalements/'.$reportId.'/take-down"]//input[@name="_token"]')
            ->attr('value');

        $admin->request('POST', '/admin/signalements/'.$reportId.'/take-down', [
            '_token' => $csrfToken,
            'status' => 'pending',
        ]);
        self::assertResponseRedirects('/admin/signalements?status=pending');
        self::assertSame(404, self::api($viewer, 'GET', '/api/social/shared/'.$token)->getStatusCode());
        self::assertSame(200, self::api($owner, 'POST', '/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'secret123',
        ])->getStatusCode());
        self::assertSame(200, self::api($owner, 'GET', '/api/items/'.$item['id'])->getStatusCode());
        $shares = self::decoded(self::api($owner, 'GET', '/api/social/shares'));
        self::assertTrue($shares['shares'][0]['moderationDisabled']);

        $admin->request('GET', '/admin/signalements', ['status' => 'taken_down']);
        $csrfToken = $admin->getCrawler()
            ->filterXPath('//form[@action="/admin/signalements/'.$reportId.'/restore"]//input[@name="_token"]')
            ->attr('value');
        $admin->request('POST', '/admin/signalements/'.$reportId.'/restore', [
            '_token' => $csrfToken,
            'status' => 'taken_down',
        ]);
        self::assertResponseRedirects('/admin/signalements?status=taken_down');
        self::assertSame(200, self::api($viewer, 'GET', '/api/social/shared/'.$token)->getStatusCode());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $report = $em->getRepository(SharedItemReport::class)->find($reportId);
        self::assertInstanceOf(SharedItemReport::class, $report);
        self::assertSame(SharedItemReport::STATUS_RESTORED, $report->status);
    }

    public function testVerifiedLinksAndCrossAccountIsolation(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner);
        $item = self::decoded(self::api($owner, 'POST', '/api/items', ['name' => 'Appareil']))['item'];
        $share = self::decoded(self::api($owner, 'POST', '/api/social/items/'.$item['id'].'/shares', ['approved' => true, 'verifiedOnly' => true]));
        $token = basename($share['path']);
        $viewer = self::freshClient();
        self::assertSame(403, self::api($viewer, 'GET', '/api/social/shared/'.$token)->getStatusCode());
        self::registerHousehold($viewer, 'noa@example.com');
        self::assertSame(200, self::api($viewer, 'GET', '/api/social/shared/'.$token)->getStatusCode());
        self::assertSame(404, self::api($viewer, 'GET', '/api/items/'.$item['id'])->getStatusCode());
        self::assertSame(404, self::api($viewer, 'DELETE', '/api/social/shares/'.$share['id'])->getStatusCode());
        self::assertSame([], self::decoded(self::api($viewer, 'GET', '/api/social/shares'))['shares']);
        self::assertSame(204, self::api($owner, 'DELETE', '/api/social/shares/'.$share['id'])->getStatusCode());
        self::assertSame(404, self::api($viewer, 'GET', '/api/social/shared/'.$token)->getStatusCode());
    }

    public function testFriendTargetedShareIsRestrictedAndRevokedWhenFriendshipEnds(): void
    {
        $owner = static::createClient();
        self::registerAccount($owner, 'camille@example.com');
        $friend = self::freshClient();
        self::registerAccount($friend, 'sophie@example.com');
        $outsider = self::freshClient();
        self::registerAccount($outsider, 'lea@example.com');

        $sent = self::api($owner, 'POST', '/api/friends/requests', ['username' => 'sophie_test']);
        $friendshipId = self::decoded($sent)['request']['id'];
        self::assertSame(200, self::api($friend, 'POST', '/api/friends/requests/'.$friendshipId.'/accept')->getStatusCode());
        $friends = self::decoded(self::api($owner, 'GET', '/api/friends'))['friends'];
        $friendId = $friends[0]['user']['id'];
        $item = self::decoded(self::api($owner, 'POST', '/api/items', ['name' => 'Appareil photo']))['item'];
        $shareUrl = '/api/social/items/'.$item['id'].'/shares';

        self::assertSame(404, self::api($owner, 'POST', $shareUrl, [
            'approved' => true,
            'recipientId' => self::decoded(self::api($outsider, 'GET', '/api/auth/me'))['user']['id'],
        ])->getStatusCode());
        $share = self::decoded(self::api($owner, 'POST', $shareUrl, [
            'approved' => true,
            'recipientId' => $friendId,
        ]));
        $token = basename($share['path']);

        self::assertSame(200, self::api($friend, 'GET', '/api/social/shared/'.$token)->getStatusCode());
        self::assertSame(404, self::api($outsider, 'GET', '/api/social/shared/'.$token)->getStatusCode());
        self::assertSame(201, self::api($friend, 'POST', '/api/social/shared/'.$token.'/loans', [])->getStatusCode());

        self::assertSame(204, self::api($owner, 'DELETE', '/api/friends/'.$friendshipId)->getStatusCode());
        self::assertSame(404, self::api($friend, 'GET', '/api/social/shared/'.$token)->getStatusCode());
    }

    public function testLoanConsentHandoverReturnHistoryAndDeletionGuard(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner);
        $item = self::decoded(self::api($owner, 'POST', '/api/items', ['name' => 'Appareil photo']))['item'];
        $share = self::decoded(self::api($owner, 'POST', '/api/social/items/'.$item['id'].'/shares', ['approved' => true]));
        $token = basename($share['path']);
        $borrower = self::freshClient();
        self::registerHousehold($borrower, 'noa@example.com');
        self::assertSame(422, self::api($borrower, 'POST', '/api/social/shared/'.$token.'/loans', ['dueAt' => '2020-01-01'])->getStatusCode());
        $request = self::api($borrower, 'POST', '/api/social/shared/'.$token.'/loans', [
            'dueAt' => '2099-12-01',
            'message' => 'Pour le week-end',
        ]);
        self::assertSame(201, $request->getStatusCode());
        $loan = self::decoded($request)['loan'];
        self::assertNull($loan['itemId']);
        self::assertSame('2099-12-01', $loan['dueAt']);
        $ownerInbox = self::decoded(self::api($owner, 'GET', '/api/notifications'));
        self::assertSame('loan_request', $ownerInbox['notifications'][0]['kind']);
        self::assertSame('Appareil photo', $ownerInbox['notifications'][0]['detail']['itemName']);
        self::assertSame('noa_test', $ownerInbox['notifications'][0]['detail']['borrower']);
        $ownerNotificationId = $ownerInbox['notifications'][0]['id'];
        self::assertSame([], self::decoded(self::api($borrower, 'GET', '/api/notifications'))['notifications']);
        self::assertSame(404, self::api($borrower, 'POST', '/api/notifications/'.$ownerNotificationId.'/read')->getStatusCode());
        self::assertSame(409, self::api($borrower, 'POST', '/api/social/shared/'.$token.'/loans')->getStatusCode());
        $base = '/api/social/loans/'.$loan['id'];
        self::assertSame(404, self::api($borrower, 'POST', $base.'/accept')->getStatusCode());
        $accepted = self::decoded(self::api($owner, 'POST', $base.'/accept'))['loan'];
        self::assertSame('accepted', $accepted['status']);
        $borrowerInbox = self::decoded(self::api($borrower, 'GET', '/api/notifications'));
        self::assertSame('loan_accepted', $borrowerInbox['notifications'][0]['kind']);
        self::assertSame('Appareil photo', $borrowerInbox['notifications'][0]['detail']['itemName']);
        self::assertNull($accepted['handedAt']);
        self::assertSame(409, self::api($owner, 'DELETE', '/api/items/'.$item['id'])->getStatusCode());
        self::assertSame(409, self::api($owner, 'PATCH', '/api/items/'.$item['id'], ['status' => 'disponible'])->getStatusCode());
        self::assertSame('active', self::decoded(self::api($owner, 'POST', $base.'/handover'))['loan']['status']);
        self::assertSame('loan_handover', self::decoded(self::api($borrower, 'GET', '/api/notifications'))['notifications'][0]['kind']);
        self::assertSame('returned', self::decoded(self::api($owner, 'POST', $base.'/return'))['loan']['status']);
        $borrowerInbox = self::decoded(self::api($borrower, 'GET', '/api/notifications'));
        self::assertSame('loan_returned', $borrowerInbox['notifications'][0]['kind']);
        self::assertCount(3, $borrowerInbox['notifications']);
        self::assertSame(409, self::api($owner, 'POST', $base.'/return')->getStatusCode());
        self::assertSame('disponible', self::decoded(self::api($owner, 'GET', '/api/items/'.$item['id']))['item']['status']);
        self::assertSame(204, self::api($owner, 'DELETE', '/api/items/'.$item['id'])->getStatusCode());
        $history = self::decoded(self::api($borrower, 'GET', '/api/social/loans'))['loans'];
        self::assertCount(1, $history);
        self::assertSame('Appareil photo', $history[0]['itemName']);
        self::assertSame('returned', $history[0]['status']);
    }

    public function testDecliningLoanSendsBorrowerAnInboxNotification(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner);
        $item = self::decoded(self::api($owner, 'POST', '/api/items', ['name' => 'Veste noire']))['item'];
        $share = self::decoded(self::api($owner, 'POST', '/api/social/items/'.$item['id'].'/shares', ['approved' => true]));
        $token = basename($share['path']);
        $borrower = self::freshClient();
        self::registerHousehold($borrower, 'noa@example.com');
        $request = self::decoded(self::api($borrower, 'POST', '/api/social/shared/'.$token.'/loans', []));

        self::assertSame(200, self::api($owner, 'POST', '/api/social/loans/'.$request['loan']['id'].'/decline')->getStatusCode());

        $inbox = self::decoded(self::api($borrower, 'GET', '/api/notifications'));
        self::assertSame('loan_declined', $inbox['notifications'][0]['kind']);
        self::assertSame('declined', $inbox['notifications'][0]['detail']['status']);
        self::assertFalse($inbox['notifications'][0]['detail']['isOwner']);
        self::assertNull($inbox['notifications'][0]['detail']['itemId']);
    }

    public function testLoanRequestCanOmitItsDueDate(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner);
        $item = self::decoded(self::api($owner, 'POST', '/api/items', ['name' => 'Projecteur']))['item'];
        $share = self::decoded(self::api($owner, 'POST', '/api/social/items/'.$item['id'].'/shares', ['approved' => true]));
        $borrower = self::freshClient();
        self::registerHousehold($borrower, 'noa@example.com');

        $request = self::api($borrower, 'POST', '/api/social/shared/'.basename($share['path']).'/loans', ['message' => 'Pour la soirée']);

        self::assertSame(201, $request->getStatusCode());
        self::assertNull(self::decoded($request)['loan']['dueAt']);
    }

    public function testChildCannotShareOrReadOtherProfilesInventory(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);
        $item = self::decoded(self::api($client, 'POST', '/api/items', ['name' => 'Adulte']))['item'];
        $share = self::decoded(self::api($client, 'POST', '/api/social/items/'.$item['id'].'/shares', ['approved' => true]));
        $child = self::decoded(self::api($client, 'POST', '/api/auth/profiles', ['role' => 'child', 'name' => 'Léa']))['profile'];
        $owned = self::api($client, 'POST', '/api/items', ['name' => 'Léa', 'ownerProfileId' => $child['id']]);
        self::assertSame($child['id'], self::decoded($owned)['item']['ownerProfile']['id']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'camille@example.com']);
        self::assertInstanceOf(User::class, $user);
        $adultItem = $em->getRepository(Item::class)->find($item['id']);
        $childItem = $em->getRepository(Item::class)->find(self::decoded($owned)['item']['id']);
        self::assertInstanceOf(Item::class, $adultItem);
        self::assertInstanceOf(Item::class, $childItem);
        $adultLoan = new Loan($adultItem, $user, $user, new \DateTimeImmutable('+7 days'), '');
        $adultLoan->status = 'active';
        $adultLoan->handedAt = new \DateTimeImmutable();
        $childLoan = new Loan($childItem, $user, $user, new \DateTimeImmutable('+7 days'), '');
        $childLoan->status = 'active';
        $childLoan->handedAt = new \DateTimeImmutable();
        $em->persist($adultLoan);
        $em->persist($childLoan);
        $em->flush();

        self::api($client, 'POST', '/api/auth/profiles/'.$child['id'].'/switch');
        self::assertSame(403, self::api($client, 'GET', '/api/social/workspace')->getStatusCode());
        self::assertSame(403, self::api($client, 'POST', '/api/social/items/'.$item['id'].'/shares', ['approved' => true])->getStatusCode());
        self::assertSame(403, self::api($client, 'POST', '/api/social/shared/'.basename($share['path']).'/reports', [
            'reason' => 'inappropriate',
            'details' => '',
        ])->getStatusCode());
        self::assertSame(404, self::api($client, 'GET', '/api/items/'.$item['id'])->getStatusCode());
        $list = self::decoded(self::api($client, 'GET', '/api/items'));
        self::assertCount(1, $list['items']);
        self::assertSame('Léa', $list['items'][0]['name']);
        self::assertSame(403, self::api($client, 'DELETE', '/api/items/'.$list['items'][0]['id'])->getStatusCode());
        self::assertSame(403, self::api($client, 'PATCH', '/api/items/'.$list['items'][0]['id'], ['status' => 'loue'])->getStatusCode());
        self::assertSame(403, self::api($client, 'POST', '/api/places', ['name' => 'Interdit'])->getStatusCode());
        self::assertSame([], self::decoded(self::api($client, 'GET', '/api/places'))['places']);

        $space = self::decoded(self::api($client, 'GET', '/api/me/space'));
        self::assertTrue($space['isChild']);
        self::assertFalse($space['canManage']);
        self::assertSame(['Léa'], array_column($space['items'], 'name'));
        self::assertSame(['Léa'], array_column($space['loans'], 'itemName'));
        self::assertSame([], $space['places']);
        self::assertSame([], $space['profiles']);
        self::assertSame([], $space['members']);
        self::assertSame([], $space['shares']);
        self::assertSame(403, self::api($client, 'GET', '/api/family/members')->getStatusCode());
        self::assertSame(403, self::api($client, 'DELETE', '/api/social/shares/'.$share['id'])->getStatusCode());
        self::assertSame(403, self::api($client, 'POST', '/api/social/loans/'.$childLoan->id.'/return')->getStatusCode());
    }

    public function testExpiredShareAndPhotoCannotBeUsedAfterRevocation(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner);
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $png = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
        imagedestroy($image);
        $created = self::api($owner, 'POST', '/api/items', ['name' => 'Nom approuvé', 'photos' => [$png]]);
        self::assertSame(201, $created->getStatusCode());
        $id = self::decoded($created)['item']['id'];
        $share = self::decoded(self::api($owner, 'POST', '/api/social/items/'.$id.'/shares', ['approved' => true]));
        $token = basename($share['path']);
        self::api($owner, 'PATCH', '/api/items/'.$id, ['name' => 'Nom privé ultérieur', 'color' => 'Note privée']);
        $viewer = self::freshClient();
        $public = self::decoded(self::api($viewer, 'GET', '/api/social/shared/'.$token))['item'];
        self::assertSame('Nom approuvé', $public['name']);
        self::assertNull($public['color']);
        $photo = self::api($viewer, 'GET', $public['photoUrl']);
        self::assertSame(200, $photo->getStatusCode());
        self::assertSame('image/png', $photo->headers->get('Content-Type'));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $record = $em->getRepository(\App\Entity\ItemShare::class)->find($share['id']);
        self::assertNotNull($record);
        $record->expiresAt = new \DateTimeImmutable('-1 day');
        $em->flush();
        self::assertSame(404, self::api($viewer, 'GET', '/api/social/shared/'.$token)->getStatusCode());
        self::assertSame(404, self::api($viewer, 'GET', $public['photoUrl'])->getStatusCode());
    }
}
