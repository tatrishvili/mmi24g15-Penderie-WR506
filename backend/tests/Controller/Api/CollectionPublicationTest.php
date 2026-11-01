<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class CollectionPublicationTest extends ApiTestCase
{
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function publish(KernelBrowser $owner, string $id, string $audience): void
    {
        $workspace = self::decoded(self::api($owner, 'GET', '/api/social/workspace'));
        $preview = self::decoded(self::api($owner, 'GET', '/api/social/collections/'.$id.'/preview'));
        $response = self::api($owner, 'PUT', '/api/social/collections/'.$id.'/audience', [
            'version' => $workspace['version'], 'visibility' => $audience, 'approved' => true, 'digest' => $preview['digest'],
        ]);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testAudienceMatrixDiscoveryAndRelationshipRevocation(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner);
        $id = self::decoded(self::api($owner, 'POST', '/api/social/collections', ['name' => 'Disques']))['collections'][0]['id'];
        $friend = self::freshClient();
        self::registerHousehold($friend, 'friend@example.com');
        $follower = self::freshClient();
        self::registerHousehold($follower, 'follower@example.com');
        $stranger = self::freshClient();
        self::registerHousehold($stranger, 'stranger@example.com');
        $path = '/api/social/collection-publications/'.$id;
        self::assertSame(404, self::api($stranger, 'GET', $path)->getStatusCode());
        $request = self::decoded(self::api($friend, 'POST', '/api/friends/requests', ['username' => 'camille_test']))['request'];
        $follow = self::decoded(self::api($follower, 'POST', '/api/friends/following', ['username' => 'camille_test']))['follow'];
        $this->publish($owner, $id, 'friends');
        self::assertSame(404, self::api($friend, 'GET', $path)->getStatusCode(), 'Pending requests are not friends.');
        self::assertSame(200, self::api($owner, 'POST', '/api/friends/requests/'.$request['id'].'/accept')->getStatusCode());
        foreach ([[$friend, 200], [$follower, 404], [$stranger, 404]] as [$viewer, $status]) {
            self::assertSame($status, self::api($viewer, 'GET', $path)->getStatusCode());
            $feed = self::api($viewer, 'GET', '/api/social/collection-publications');
            self::assertSame(200, $feed->getStatusCode(), (string) $feed->getContent());
            self::assertCount(200 === $status ? 1 : 0, self::decoded($feed)['collections']);
        }
        $this->publish($owner, $id, 'followers');
        self::assertSame(200, self::api($friend, 'GET', $path)->getStatusCode());
        $this->publish($owner, $id, 'friends');
        self::assertSame(204, self::api($owner, 'DELETE', '/api/friends/'.$request['id'])->getStatusCode());
        self::assertSame(404, self::api($friend, 'GET', $path)->getStatusCode());
        $this->publish($owner, $id, 'followers');
        self::assertSame(200, self::api($follower, 'GET', $path)->getStatusCode());
        self::assertCount(1, self::decoded(self::api($follower, 'GET', '/api/social/collection-publications'))['collections']);
        self::assertSame(404, self::api($stranger, 'GET', $path)->getStatusCode());
        self::assertSame(204, self::api($follower, 'DELETE', '/api/friends/following/'.$follow['id'])->getStatusCode());
        self::assertSame(404, self::api($follower, 'GET', $path)->getStatusCode());
        $this->publish($owner, $id, 'app');
        self::assertSame(200, self::api($stranger, 'GET', $path)->getStatusCode());
        self::assertCount(1, self::decoded(self::api($stranger, 'GET', '/api/social/collection-publications'))['collections']);
        $this->publish($owner, $id, 'personal');
        self::assertSame(404, self::api($stranger, 'GET', $path)->getStatusCode());
        self::assertSame([], self::decoded(self::api($stranger, 'GET', '/api/social/collection-publications'))['collections']);
        $anonymous = self::freshClient();
        self::assertSame(401, self::api($anonymous, 'GET', $path)->getStatusCode());
    }

    public function testApprovalSafeFrozenProjectionAndPhotoVetoes(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner);
        $created = self::decoded(self::api($owner, 'POST', '/api/social/collections', ['name' => 'Livres']));
        $id = $created['collections'][0]['id'];
        $item = self::decoded(self::api($owner, 'POST', '/api/items', ['name' => 'Nom approuvé', 'notes' => 'secret', 'memory' => 'private', 'photos' => [self::PNG]]))['item'];
        self::api($owner, 'PUT', '/api/social/collections/'.$id.'/items/'.$item['id']);
        $personal = self::decoded(self::api($owner, 'POST', '/api/items', ['name' => 'Personnel']))['item'];
        self::api($owner, 'PUT', '/api/social/items/'.$personal['id'].'/visibility', ['visibility' => 'personal']);
        self::api($owner, 'PUT', '/api/social/collections/'.$id.'/items/'.$personal['id']);
        $child = self::decoded(self::api($owner, 'POST', '/api/auth/profiles', ['role' => 'child', 'name' => 'Enfant']))['profile'];
        $childItem = self::decoded(self::api($owner, 'POST', '/api/items', ['name' => 'Enfant privé', 'ownerProfileId' => $child['id']]))['item'];
        self::api($owner, 'PUT', '/api/social/collections/'.$id.'/items/'.$childItem['id']);
        $preview = self::decoded(self::api($owner, 'GET', '/api/social/collections/'.$id.'/preview'));
        self::assertCount(1, $preview['items']);
        self::assertSame(2, $preview['excludedCount']);
        $workspace = self::decoded(self::api($owner, 'GET', '/api/social/workspace'));
        $url = '/api/social/collections/'.$id.'/audience';
        $body = ['version' => $workspace['version'], 'visibility' => 'app', 'digest' => $preview['digest']];
        self::assertSame(422, self::api($owner, 'PUT', $url, $body)->getStatusCode());
        $body['approved'] = true;
        self::api($owner, 'PATCH', '/api/items/'.$item['id'], ['name' => 'Changé avant approbation']);
        self::assertSame(409, self::api($owner, 'PUT', $url, $body)->getStatusCode());
        self::api($owner, 'PATCH', '/api/items/'.$item['id'], ['name' => 'Nom approuvé']);
        $this->publish($owner, $id, 'app');
        self::api($owner, 'PATCH', '/api/items/'.$item['id'], ['name' => 'Nom privé ultérieur']);
        $viewer = self::freshClient();
        self::registerHousehold($viewer, 'viewer@example.com');
        $path = '/api/social/collection-publications/'.$id;
        $response = self::api($viewer, 'GET', $path);
        self::assertSame(200, $response->getStatusCode());
        $shown = self::decoded($response)['collection'];
        self::assertCount(1, $shown['items']);
        self::assertSame(['name', 'type', 'color', 'photoUrl'], array_keys($shown['items'][0]));
        self::assertSame('Nom approuvé', $shown['items'][0]['name']);
        self::assertStringNotContainsString('secret', (string) $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $photo = $shown['items'][0]['photoUrl'];
        self::assertSame(200, self::api($viewer, 'GET', $photo)->getStatusCode());
        self::assertSame(404, self::api($viewer, 'GET', '/api/items/'.$item['id'])->getStatusCode());
        self::assertSame(404, self::api($viewer, 'PUT', $url, $body)->getStatusCode());
        self::api($owner, 'PUT', '/api/social/items/'.$item['id'].'/visibility', ['visibility' => 'personal']);
        self::assertCount(0, self::decoded(self::api($viewer, 'GET', $path))['collection']['items']);
        self::assertSame(404, self::api($viewer, 'GET', $photo)->getStatusCode());
        self::api($owner, 'PUT', '/api/social/items/'.$item['id'].'/visibility', ['visibility' => 'private']);
        self::assertCount(0, self::decoded(self::api($viewer, 'GET', $path))['collection']['items'], 'Restoring private visibility must not republish the item.');
        $this->publish($owner, $id, 'personal');
        self::assertSame(404, self::api($viewer, 'GET', $photo)->getStatusCode());
        self::api($owner, 'POST', '/api/auth/profiles/'.$child['id'].'/switch');
        self::assertSame(403, self::api($owner, 'PUT', $url, $body)->getStatusCode());
        self::assertSame(403, self::api($owner, 'GET', '/api/social/collection-publications')->getStatusCode());
    }

    public function testCreateAudienceRequiresApprovalAndMembershipChangesUnpublish(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner);
        self::assertSame(422, self::api($owner, 'POST', '/api/social/collections', ['name' => 'Public', 'visibility' => 'app'])->getStatusCode());
        self::assertSame(422, self::api($owner, 'POST', '/api/social/collections', ['name' => 'Invalid', 'visibility' => 'public', 'approved' => true])->getStatusCode());
        $response = self::api($owner, 'POST', '/api/social/collections', ['name' => 'Partagée', 'visibility' => 'app', 'approved' => true]);
        self::assertSame(200, $response->getStatusCode());
        $id = self::decoded($response)['collections'][0]['id'];
        self::assertSame('app', self::decoded($response)['collections'][0]['visibility']);
        $viewer = self::freshClient();
        self::registerHousehold($viewer, 'viewer@example.com');
        $path = '/api/social/collection-publications/'.$id;
        self::assertSame(200, self::api($viewer, 'GET', $path)->getStatusCode());
        $item = self::decoded(self::api($owner, 'POST', '/api/items', ['name' => 'Ajout']))['item'];
        self::assertSame('personal', self::decoded(self::api($owner, 'PUT', '/api/social/collections/'.$id.'/items/'.$item['id']))['collections'][0]['visibility']);
        self::assertSame(404, self::api($viewer, 'GET', $path)->getStatusCode());
    }

    public function testUnverifiedViewersAreDeniedAndArchivedItemsNeedApprovalAgain(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner);
        $collection = self::decoded(self::api($owner, 'POST', '/api/social/collections', ['name' => 'Archive']))['collections'][0]['id'];
        $item = self::decoded(self::api($owner, 'POST', '/api/items', ['name' => 'Objet']))['item']['id'];
        self::api($owner, 'PUT', '/api/social/collections/'.$collection.'/items/'.$item);
        $this->publish($owner, $collection, 'app');
        $viewer = self::freshClient();
        self::registerHousehold($viewer, 'viewer@example.com');
        $path = '/api/social/collection-publications/'.$collection;
        self::assertCount(1, self::decoded(self::api($viewer, 'GET', $path))['collection']['items']);
        self::api($owner, 'PATCH', '/api/items/'.$item, ['status' => 'archive']);
        self::api($owner, 'PATCH', '/api/items/'.$item, ['status' => 'disponible']);
        self::assertCount(0, self::decoded(self::api($viewer, 'GET', $path))['collection']['items']);
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'viewer@example.com']);
        self::assertNotNull($user);
        $user->setEmailVerified(false);
        $em->flush();
        self::assertSame(403, self::api($viewer, 'GET', $path)->getStatusCode());
        self::assertSame(403, self::api($viewer, 'GET', '/api/social/collection-publications')->getStatusCode());
    }
}
