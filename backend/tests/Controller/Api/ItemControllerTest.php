<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ItemControllerTest extends ApiTestCase
{
    public function testItemCrudFlow(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $created = self::api($client, 'POST', '/api/items', [
            'name' => 'Pull noir Zara',
            'brand' => 'Zara',
            'color' => 'noir',
            'size' => 'M',
            'condition' => 'tres_bon',
        ]);

        $this->assertSame(Response::HTTP_CREATED, $created->getStatusCode());

        $item = self::decoded($created)['item'];
        $this->assertSame('Pull noir Zara', $item['name']);
        $this->assertSame('tres_bon', $item['condition']);
        $this->assertSame('disponible', $item['status']);
        $this->assertNotSame('', $item['qrToken']);
        $this->assertSame('Moi', $item['ownerProfile']['name']);
        $this->assertSame([], $item['photos']);

        $listed = self::api($client, 'GET', '/api/items');
        $this->assertSame(Response::HTTP_OK, $listed->getStatusCode());
        $this->assertSame(1, self::decoded($listed)['total']);

        $shown = self::api($client, 'GET', '/api/items/'.$item['id']);
        $this->assertSame(Response::HTTP_OK, $shown->getStatusCode());
        $this->assertSame('Pull noir Zara', self::decoded($shown)['item']['name']);

        $patched = self::api($client, 'PATCH', '/api/items/'.$item['id'], [
            'status' => 'donne',
            'notes' => 'À donner à Sophie',
        ]);
        $this->assertSame(Response::HTTP_OK, $patched->getStatusCode());
        $this->assertSame('donne', self::decoded($patched)['item']['status']);
        $this->assertSame('Pull noir Zara', self::decoded($patched)['item']['name']);

        $deleted = self::api($client, 'DELETE', '/api/items/'.$item['id']);
        $this->assertSame(Response::HTTP_NO_CONTENT, $deleted->getStatusCode());

        $gone = self::api($client, 'GET', '/api/items/'.$item['id']);
        $this->assertSame(Response::HTTP_NOT_FOUND, $gone->getStatusCode());
    }

    public function testItemValidation(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $response = self::api($client, 'POST', '/api/items', [
            'name' => '',
            'condition' => 'rotten',
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $payload = self::decoded($response);
        $this->assertSame('validation_failed', $payload['error']['code']);
        $this->assertArrayHasKey('name', $payload['error']['violations']);
        $this->assertArrayHasKey('condition', $payload['error']['violations']);
    }

    public function testItemStoresItsTypeCategoryAndExactLocation(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $place = self::decoded(self::api($client, 'GET', '/api/places'))['places'][0];
        $room = self::decoded(self::api($client, 'POST', '/api/places/'.$place['id'].'/rooms', [
            'name' => 'Chambre',
        ]))['room'];
        $wardrobe = self::decoded(self::api($client, 'POST', '/api/rooms/'.$room['id'].'/storage-locations', [
            'name' => 'Penderie',
        ]))['storageLocation'];
        $box = self::decoded(self::api($client, 'POST', '/api/rooms/'.$room['id'].'/storage-locations', [
            'name' => 'Boîte hiver',
            'parentId' => $wardrobe['id'],
        ]))['storageLocation'];

        $response = self::api($client, 'POST', '/api/items', [
            'name' => 'Pull bleu',
            'type' => 'pull',
            'category' => 'Hauts',
            'roomId' => $room['id'],
            'storageLocationId' => $box['id'],
        ]);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $item = self::decoded($response)['item'];
        $this->assertSame('pull', $item['type']);
        $this->assertSame('Hauts', $item['category']);
        $this->assertSame('Chambre', $item['room']['name']);
        $this->assertSame(['Penderie', 'Boîte hiver'], $item['storageLocation']['path']);

        $withoutStorage = self::api($client, 'PATCH', '/api/items/'.$item['id'], [
            'storageLocationId' => null,
        ]);
        $this->assertSame(Response::HTTP_OK, $withoutStorage->getStatusCode());
        $this->assertSame('Chambre', self::decoded($withoutStorage)['item']['room']['name']);
        $this->assertNull(self::decoded($withoutStorage)['item']['storageLocation']);

        $room = self::decoded(self::api($client, 'POST', '/api/places/'.$place['id'].'/rooms', [
            'name' => 'Entrée',
        ]))['room'];
        $moved = self::api($client, 'PATCH', '/api/items/'.$item['id'], [
            'roomId' => $room['id'],
        ]);
        $this->assertSame(Response::HTTP_OK, $moved->getStatusCode());
        $this->assertSame('Entrée', self::decoded($moved)['item']['room']['name']);
        $this->assertNull(self::decoded($moved)['item']['storageLocation']);

        $cleared = self::api($client, 'PATCH', '/api/items/'.$item['id'], [
            'roomId' => null,
        ]);
        $this->assertSame(Response::HTTP_OK, $cleared->getStatusCode());
        $this->assertNull(self::decoded($cleared)['item']['room']);
        $this->assertNull(self::decoded($cleared)['item']['storageLocation']);
    }

    public function testCannotAssignLocationFromAnotherHousehold(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner, 'camille@example.com');
        $place = self::decoded(self::api($owner, 'GET', '/api/places'))['places'][0];
        $room = self::decoded(self::api($owner, 'POST', '/api/places/'.$place['id'].'/rooms', [
            'name' => 'Chambre privée',
        ]))['room'];

        $other = self::freshClient();
        self::registerHousehold($other, 'sophie@example.com', 'Sophie');
        $response = self::api($other, 'POST', '/api/items', [
            'name' => 'Article étranger',
            'roomId' => $room['id'],
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testItemRequiresHousehold(): void
    {
        $client = static::createClient();
        self::registerAccount($client);
        self::removeHouseholdMemberships('camille@example.com');

        $response = self::api($client, 'POST', '/api/items', ['name' => 'Pull']);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testCannotStealNeighboursItem(): void
    {
        $first = static::createClient();
        self::registerHousehold($first, 'camille@example.com');

        $created = self::api($first, 'POST', '/api/items', ['name' => 'Montre']);
        $itemId = self::decoded($created)['item']['id'];

        $second = self::freshClient();
        self::registerHousehold($second, 'sophie@example.com', 'Famille Diallo');

        foreach ([
            ['GET', '/api/items/'.$itemId, []],
            ['PATCH', '/api/items/'.$itemId, ['name' => 'Volée']],
            ['DELETE', '/api/items/'.$itemId, []],
        ] as [$method, $uri, $body]) {
            $response = self::api($second, $method, $uri, $body);
            $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), $method.' '.$uri);
        }

        $listed = self::api($second, 'GET', '/api/items');
        $this->assertSame(0, self::decoded($listed)['total']);
    }

    public function testItemPhotoUploadAndDelete(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $created = self::api($client, 'POST', '/api/items', ['name' => 'Pull']);
        $itemId = self::decoded($created)['item']['id'];

        // 1x1 transparent PNG.
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $uploaded = self::api($client, 'POST', '/api/items/'.$itemId.'/photos', ['photos' => [$png]]);
        $this->assertSame(Response::HTTP_CREATED, $uploaded->getStatusCode());

        $photos = self::decoded($uploaded)['photos'];
        $this->assertCount(1, $photos);
        $this->assertTrue($photos[0]['isPrimary']);

        $shown = self::api($client, 'GET', '/api/items/'.$itemId);
        $this->assertCount(1, self::decoded($shown)['item']['photos']);

        $file = self::api($client, 'GET', $photos[0]['url']);
        $this->assertSame(Response::HTTP_OK, $file->getStatusCode());

        $bad = self::api($client, 'POST', '/api/items/'.$itemId.'/photos', ['photos' => ['not-an-image']]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $bad->getStatusCode());

        $deleted = self::api($client, 'DELETE', '/api/items/'.$itemId.'/photos/'.$photos[0]['id']);
        $this->assertSame(Response::HTTP_NO_CONTENT, $deleted->getStatusCode());

        $shown = self::api($client, 'GET', '/api/items/'.$itemId);
        $this->assertCount(0, self::decoded($shown)['item']['photos']);
    }

    public function testItemSearchAndQrLookup(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        self::api($client, 'POST', '/api/items', ['name' => 'Pull noir', 'color' => 'noir']);
        $second = self::decoded(self::api($client, 'POST', '/api/items', ['name' => 'Robe rouge']));
        $token = $second['item']['qrToken'];

        $search = self::api($client, 'GET', '/api/items?q=noir');
        $payload = self::decoded($search);
        $this->assertSame(1, $payload['total']);
        $this->assertSame('Pull noir', $payload['items'][0]['name']);

        $qr = self::api($client, 'GET', '/api/items/qr/'.$token);
        $this->assertSame(Response::HTTP_OK, $qr->getStatusCode());
        $this->assertSame('Robe rouge', self::decoded($qr)['item']['name']);

        $unknown = self::api($client, 'GET', '/api/items/qr/nope');
        $this->assertSame(Response::HTTP_NOT_FOUND, $unknown->getStatusCode());
    }
}
