<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Business paths of the item lifecycle that the happy-path CRUD test does not
 * touch: photos at creation, every optional field on PATCH, and the guards of
 * the photo pipeline (count, formats, storage) plus location validation.
 */
final class ItemLifecycleTest extends ApiTestCase
{
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testCreateWithPhotosAndStatusThenPatchEveryOptionalField(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $created = self::api($client, 'POST', '/api/items', [
            'name' => 'Pull noir Zara',
            'status' => 'loue',
            'condition' => 'tres_bon',
            'photos' => [self::PNG],
        ]);

        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode());
        $item = self::decoded($created)['item'];
        self::assertSame('loue', $item['status']);

        $shown = self::decoded(self::api($client, 'GET', '/api/items/'.$item['id']))['item'];
        self::assertCount(1, $shown['photos'], 'The photo must be stored and visible on the item.');
        $item['id'] = $shown['id'];

        $patched = self::api($client, 'PATCH', '/api/items/'.$item['id'], [
            'name' => 'Pull bleu',
            'type' => 'pull',
            'category' => 'haut',
            'brand' => 'Uniqlo',
            'color' => 'bleu',
            'size' => 'L',
            'material' => 'laine',
            'condition' => 'bon',
            'status' => 'disponible',
            'memory' => 'taille L',
            'notes' => 'manque un bouton',
        ]);

        self::assertSame(Response::HTTP_OK, $patched->getStatusCode());
        $updated = self::decoded($patched)['item'];
        self::assertSame('Pull bleu', $updated['name']);
        self::assertSame('pull', $updated['type']);
        self::assertSame('haut', $updated['category']);
        self::assertSame('Uniqlo', $updated['brand']);
        self::assertSame('bleu', $updated['color']);
        self::assertSame('L', $updated['size']);
        self::assertSame('laine', $updated['material']);
        self::assertSame('bon', $updated['condition']);
        self::assertSame('disponible', $updated['status']);
        self::assertSame('taille L', $updated['memory']);
        self::assertSame('manque un bouton', $updated['notes']);
    }

    public function testBookAndMusicMetadataRoundTripsAndCanBeCleared(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);
        $created = self::api($client, 'POST', '/api/items', [
            'name' => 'Un livre', 'type' => 'livre', 'author' => 'Camille', 'publisher' => 'Éditions Test',
        ]);
        self::assertSame(201, $created->getStatusCode());
        $item = self::decoded($created)['item'];
        $shown = self::decoded(self::api($client, 'GET', '/api/items/'.$item['id']))['item'];
        self::assertSame('Camille', $shown['author']);
        self::assertSame('Éditions Test', $shown['publisher']);
        self::assertNull($shown['brand']);
        $patched = self::api($client, 'PATCH', '/api/items/'.$item['id'], [
            'type' => 'vinyle', 'author' => null, 'artist' => 'Un groupe', 'publisher' => 'Un label',
        ]);
        self::assertSame(200, $patched->getStatusCode());
        $shown = self::decoded(self::api($client, 'GET', '/api/items/'.$item['id']))['item'];
        self::assertNull($shown['author']);
        self::assertSame('Un groupe', $shown['artist']);
        self::assertSame('Un label', $shown['publisher']);
        $invalid = self::api($client, 'PATCH', '/api/items/'.$item['id'], ['author' => str_repeat('a', 121)]);
        self::assertSame(422, $invalid->getStatusCode());
    }

    public function testPatchRejectsAnEmptyName(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);
        $item = self::decoded(self::api($client, 'POST', '/api/items', ['name' => 'Pull noir']))['item'];

        $patched = self::api($client, 'PATCH', '/api/items/'.$item['id'], ['name' => '']);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $patched->getStatusCode());
        self::assertSame('validation_failed', self::decoded($patched)['error']['code']);
    }

    public function testPhotoGuardRails(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);
        $item = self::decoded(self::api($client, 'POST', '/api/items', ['name' => 'Sac cabas']))['item'];
        $photos = '/api/items/'.$item['id'].'/photos';

        $empty = self::api($client, 'POST', $photos, ['photos' => []]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $empty->getStatusCode());
        self::assertSame('No photos provided.', self::decoded($empty)['error']['message']);

        $notAString = self::api($client, 'POST', $photos, ['photos' => [123]]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $notAString->getStatusCode());
        self::assertStringContainsString('base64 data URL', (string) self::decoded($notAString)['error']['message']);

        $seven = self::api($client, 'POST', $photos, ['photos' => array_fill(0, 7, self::PNG)]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $seven->getStatusCode());
        self::assertStringContainsString('Too many photos', (string) self::decoded($seven)['error']['message']);

        $badEncoding = self::api($client, 'POST', $photos, ['photos' => ['data:image/png;base64,@@@@']]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $badEncoding->getStatusCode());
        self::assertStringContainsString('5 MB max', (string) self::decoded($badEncoding)['error']['message']);

        $notAnImage = self::api($client, 'POST', $photos, [
            'photos' => ['data:image/png;base64,'.base64_encode('plain text')],
        ]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $notAnImage->getStatusCode());
        self::assertStringContainsString('not a readable image', (string) self::decoded($notAnImage)['error']['message']);

        $missing = self::api($client, 'DELETE', '/api/items/'.$item['id'].'/photos/999999');
        self::assertSame(Response::HTTP_NOT_FOUND, $missing->getStatusCode());
    }

    public function testPrimaryPhotoIsPromotedWhenRemovedThenTheItemDiesWithItsFiles(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);
        $item = self::decoded(self::api($client, 'POST', '/api/items', ['name' => 'Robe d\'été']))['item'];

        $first = self::decoded(self::api($client, 'POST', '/api/items/'.$item['id'].'/photos', ['photos' => [self::PNG]]))['photos'];
        self::assertTrue($first[0]['isPrimary']);

        $second = self::decoded(self::api(
            $client,
            'POST',
            '/api/items/'.$item['id'].'/photos',
            ['photos' => [self::PNG]],
        ))['photos'];
        self::assertFalse($second[0]['isPrimary']);

        $removed = self::api($client, 'DELETE', '/api/items/'.$item['id'].'/photos/'.$first[0]['id']);
        self::assertSame(Response::HTTP_NO_CONTENT, $removed->getStatusCode());

        $shown = self::decoded(self::api($client, 'GET', '/api/items/'.$item['id']))['item'];
        self::assertCount(1, $shown['photos']);
        self::assertTrue($shown['photos'][0]['isPrimary'], 'The next photo must be promoted to primary.');

        $deleted = self::api($client, 'DELETE', '/api/items/'.$item['id']);
        self::assertSame(Response::HTTP_NO_CONTENT, $deleted->getStatusCode());
    }

    public function testLocationValidationGuards(): void
    {
        $client = static::createClient();
        self::registerHousehold($client);

        $place = self::decoded(self::api($client, 'GET', '/api/places'))['places'][0];
        $roomA = self::decoded(self::api($client, 'POST', '/api/places/'.$place['id'].'/rooms', ['name' => 'Chambre']))['room'];
        $roomB = self::decoded(self::api($client, 'POST', '/api/places/'.$place['id'].'/rooms', ['name' => 'Salon']))['room'];
        $storage = self::decoded(self::api($client, 'POST', '/api/rooms/'.$roomA['id'].'/storage-locations', [
            'name' => 'Armoire',
        ]))['storageLocation'];

        $foreignStorage = self::api($client, 'POST', '/api/items', [
            'name' => 'Hors saison',
            'storageLocationId' => 999999,
        ]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $foreignStorage->getStatusCode());
        self::assertStringContainsString('Storage location', (string) self::decoded($foreignStorage)['error']['message']);

        $mixedRoom = self::api($client, 'POST', '/api/items', [
            'name' => 'Mauvaise combinaison',
            'storageLocationId' => $storage['id'],
            'roomId' => $roomB['id'],
        ]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $mixedRoom->getStatusCode());
        self::assertStringContainsString('does not belong to the selected room', (string) self::decoded($mixedRoom)['error']['message']);

        $created = self::api($client, 'POST', '/api/items', [
            'name' => 'Dans l\'armoire',
            'storageLocationId' => $storage['id'],
        ]);
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode());
        $itemId = self::decoded($created)['item']['id'];

        // Patching only the storage location keeps the room implicit.
        $moved = self::api($client, 'PATCH', '/api/items/'.$itemId, ['storageLocationId' => $storage['id']]);
        self::assertSame(Response::HTTP_OK, $moved->getStatusCode());
        self::assertSame('Armoire', self::decoded($moved)['item']['storageLocation']['name']);
        self::assertSame('Chambre', self::decoded($moved)['item']['room']['name']);
    }
}
