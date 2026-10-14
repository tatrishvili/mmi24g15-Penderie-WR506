<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PlaceControllerTest extends ApiTestCase
{
    public function testHouseRoomAndNestedStorageCanBeCreatedAndListed(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $placeResponse = self::api($client, 'POST', '/api/places', ['name' => 'Maison de campagne']);
        $this->assertSame(Response::HTTP_CREATED, $placeResponse->getStatusCode());
        $placeId = self::decoded($placeResponse)['place']['id'];

        $roomResponse = self::api($client, 'POST', '/api/places/'.$placeId.'/rooms', ['name' => 'Chambre']);
        $this->assertSame(Response::HTTP_CREATED, $roomResponse->getStatusCode());
        $roomId = self::decoded($roomResponse)['room']['id'];

        $wardrobeResponse = self::api($client, 'POST', '/api/rooms/'.$roomId.'/storage-locations', [
            'name' => 'Armoire',
        ]);
        $this->assertSame(Response::HTTP_CREATED, $wardrobeResponse->getStatusCode());
        $wardrobeId = self::decoded($wardrobeResponse)['storageLocation']['id'];

        $boxResponse = self::api($client, 'POST', '/api/rooms/'.$roomId.'/storage-locations', [
            'name' => 'Boîte 2',
            'parentId' => $wardrobeId,
        ]);
        $this->assertSame(Response::HTTP_CREATED, $boxResponse->getStatusCode());

        $listed = self::api($client, 'GET', '/api/places');
        $this->assertSame(Response::HTTP_OK, $listed->getStatusCode());
        $places = self::decoded($listed)['places'];
        $place = array_values(array_filter($places, static fn (array $entry): bool => $entry['id'] === $placeId))[0];

        $this->assertSame('Chambre', $place['rooms'][0]['name']);
        $this->assertSame('Armoire', $place['rooms'][0]['storageLocations'][0]['name']);
        $this->assertSame('Boîte 2', $place['rooms'][0]['storageLocations'][0]['children'][0]['name']);
    }

    public function testCannotCreateRoomUnderAnotherHouseholdsPlace(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner, 'camille@example.com');
        $placeId = self::decoded(self::api($owner, 'GET', '/api/places'))['places'][0]['id'];

        $other = self::freshClient();
        self::registerHousehold($other, 'sophie@example.com', 'Sophie');
        $response = self::api($other, 'POST', '/api/places/'.$placeId.'/rooms', ['name' => 'Intrusion']);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testLocationNamesAreValidated(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $response = self::api($client, 'POST', '/api/places', ['name' => '  ']);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }
}
