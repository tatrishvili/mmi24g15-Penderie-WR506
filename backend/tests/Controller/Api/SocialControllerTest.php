<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\ApiTestCase;

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
        $request = self::api($borrower, 'POST', '/api/social/shared/'.$token.'/loans', ['dueAt' => '2099-12-01', 'message' => 'Pour le week-end']);
        self::assertSame(201, $request->getStatusCode());
        $loan = self::decoded($request)['loan'];
        self::assertNull($loan['itemId']);
        self::assertSame(409, self::api($borrower, 'POST', '/api/social/shared/'.$token.'/loans', ['dueAt' => '2099-12-01'])->getStatusCode());
        $base = '/api/social/loans/'.$loan['id'];
        self::assertSame(404, self::api($borrower, 'POST', $base.'/accept')->getStatusCode());
        $accepted = self::decoded(self::api($owner, 'POST', $base.'/accept'))['loan'];
        self::assertSame('accepted', $accepted['status']);
        self::assertNull($accepted['handedAt']);
        self::assertSame(409, self::api($owner, 'DELETE', '/api/items/'.$item['id'])->getStatusCode());
        self::assertSame(409, self::api($owner, 'PATCH', '/api/items/'.$item['id'], ['status' => 'disponible'])->getStatusCode());
        self::assertSame('active', self::decoded(self::api($owner, 'POST', $base.'/handover'))['loan']['status']);
        self::assertSame('returned', self::decoded(self::api($owner, 'POST', $base.'/return'))['loan']['status']);
        self::assertSame(409, self::api($owner, 'POST', $base.'/return')->getStatusCode());
        self::assertSame('disponible', self::decoded(self::api($owner, 'GET', '/api/items/'.$item['id']))['item']['status']);
        self::assertSame(204, self::api($owner, 'DELETE', '/api/items/'.$item['id'])->getStatusCode());
        $history = self::decoded(self::api($borrower, 'GET', '/api/social/loans'))['loans'];
        self::assertCount(1, $history);
        self::assertSame('Appareil photo', $history[0]['itemName']);
        self::assertSame('returned', $history[0]['status']);
    }

    public function testChildCannotShareOrReadOtherProfilesInventory(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);
        $item = self::decoded(self::api($client, 'POST', '/api/items', ['name' => 'Adulte']))['item'];
        $child = self::decoded(self::api($client, 'POST', '/api/auth/profiles', ['role' => 'child', 'name' => 'Léa']))['profile'];
        $owned = self::api($client, 'POST', '/api/items', ['name' => 'Léa', 'ownerProfileId' => $child['id']]);
        self::assertSame($child['id'], self::decoded($owned)['item']['ownerProfile']['id']);
        self::api($client, 'POST', '/api/auth/profiles/'.$child['id'].'/switch');
        self::assertSame(403, self::api($client, 'GET', '/api/social/workspace')->getStatusCode());
        self::assertSame(403, self::api($client, 'POST', '/api/social/items/'.$item['id'].'/shares', ['approved' => true])->getStatusCode());
        self::assertSame(404, self::api($client, 'GET', '/api/items/'.$item['id'])->getStatusCode());
        $list = self::decoded(self::api($client, 'GET', '/api/items'));
        self::assertCount(1, $list['items']);
        self::assertSame('Léa', $list['items'][0]['name']);
        self::assertSame(403, self::api($client, 'DELETE', '/api/items/'.$list['items'][0]['id'])->getStatusCode());
        self::assertSame(403, self::api($client, 'PATCH', '/api/items/'.$list['items'][0]['id'], ['status' => 'loue'])->getStatusCode());
        self::assertSame(403, self::api($client, 'POST', '/api/places', ['name' => 'Interdit'])->getStatusCode());
        self::assertSame([], self::decoded(self::api($client, 'GET', '/api/places'))['places']);
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
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $record = $em->getRepository(\App\Entity\ItemShare::class)->find($share['id']);
        self::assertNotNull($record);
        $record->expiresAt = new \DateTimeImmutable('-1 day');
        $em->flush();
        self::assertSame(404, self::api($viewer, 'GET', '/api/social/shared/'.$token)->getStatusCode());
        self::assertSame(404, self::api($viewer, 'GET', $public['photoUrl'])->getStatusCode());
    }
}
