<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

final class FriendControllerTest extends ApiTestCase
{
    public function testAdultAccountsCanSendAndAcceptFriendRequests(): void
    {
        $camille = static::createClient();
        self::registerAccount($camille, 'camille@example.com');

        $sophie = self::freshClient();
        self::registerAccount($sophie, 'sophie@example.com');

        $sent = self::api($camille, 'POST', '/api/friends/requests', ['username' => 'SOPHIE_test']);
        $this->assertSame(Response::HTTP_CREATED, $sent->getStatusCode());
        $requestId = self::decoded($sent)['request']['id'];

        $incoming = self::decoded(self::api($sophie, 'GET', '/api/friends'));
        $this->assertCount(1, $incoming['incoming']);
        $this->assertSame('camille_test', $incoming['incoming'][0]['user']['username']);
        $this->assertArrayNotHasKey('email', $incoming['incoming'][0]['user']);
        $this->assertSame('camille_test', $incoming['followers'][0]['user']['username']);

        $camilleOutgoing = self::decoded(self::api($camille, 'GET', '/api/friends'));
        $this->assertSame('sophie_test', $camilleOutgoing['following'][0]['user']['username']);

        $accepted = self::api($sophie, 'POST', '/api/friends/requests/'.$requestId.'/accept');
        $this->assertSame(Response::HTTP_OK, $accepted->getStatusCode());

        $camilleFriends = self::decoded(self::api($camille, 'GET', '/api/friends'));
        $this->assertCount(1, $camilleFriends['friends']);
        $this->assertSame('sophie_test', $camilleFriends['friends'][0]['user']['username']);
        $this->assertSame('sophie_test', $camilleFriends['followers'][0]['user']['username']);
        $this->assertSame([], $camilleFriends['incoming']);
        $this->assertSame([], $camilleFriends['outgoing']);
    }

    public function testFriendRequestsRequireAuthenticationAndAValidUsername(): void
    {
        $anonymous = static::createClient();

        $list = self::api($anonymous, 'GET', '/api/friends');
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $list->getStatusCode());

        $sender = self::freshClient();
        self::registerAccount($sender, 'camille@example.com');

        $invalidUsername = self::api($sender, 'POST', '/api/friends/requests', ['username' => 'not valid']);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $invalidUsername->getStatusCode());

        $missingUsername = self::api($sender, 'POST', '/api/friends/requests', ['username' => 'nobody_test']);
        $this->assertSame(Response::HTTP_NOT_FOUND, $missingUsername->getStatusCode());

        $selfRequest = self::api($sender, 'POST', '/api/friends/requests', ['username' => 'camille_test']);
        $this->assertSame(Response::HTTP_CONFLICT, $selfRequest->getStatusCode());
    }

    public function testARequestCanOnlyBeAcceptedByItsRecipientAndCanBeDeclined(): void
    {
        $sender = static::createClient();
        self::registerAccount($sender, 'camille@example.com');

        $recipient = self::freshClient();
        self::registerAccount($recipient, 'sophie@example.com');

        $outsider = self::freshClient();
        self::registerAccount($outsider, 'lea@example.com');

        $sent = self::api($sender, 'POST', '/api/friends/requests', ['username' => 'sophie_test']);
        $requestId = self::decoded($sent)['request']['id'];

        $foreignAccept = self::api($outsider, 'POST', '/api/friends/requests/'.$requestId.'/accept');
        $this->assertSame(Response::HTTP_NOT_FOUND, $foreignAccept->getStatusCode());

        $declined = self::api($recipient, 'DELETE', '/api/friends/requests/'.$requestId);
        $this->assertSame(Response::HTTP_NO_CONTENT, $declined->getStatusCode());

        $lists = self::decoded(self::api($sender, 'GET', '/api/friends'));
        $this->assertSame([], $lists['outgoing']);
        $this->assertCount(1, $lists['following'], 'Declining a friend request must not remove the sender’s follow.');
    }

    public function testDirectFollowIsIdempotentAndCanBeRemovedByItsOwner(): void
    {
        $follower = static::createClient();
        self::registerAccount($follower, 'camille@example.com');

        $target = self::freshClient();
        self::registerAccount($target, 'sophie@example.com');

        $follow = self::api($follower, 'POST', '/api/friends/following', ['username' => 'SOPHIE_test']);
        $this->assertSame(Response::HTTP_CREATED, $follow->getStatusCode());
        $followId = self::decoded($follow)['follow']['id'];

        $repeatedFollow = self::api($follower, 'POST', '/api/friends/following', ['username' => 'sophie_test']);
        $this->assertSame(Response::HTTP_OK, $repeatedFollow->getStatusCode());
        $this->assertSame($followId, self::decoded($repeatedFollow)['follow']['id']);

        $following = self::decoded(self::api($follower, 'GET', '/api/friends'));
        $this->assertCount(1, $following['following']);
        $followers = self::decoded(self::api($target, 'GET', '/api/friends'));
        $this->assertSame('camille_test', $followers['followers'][0]['user']['username']);

        $unfollow = self::api($follower, 'DELETE', '/api/friends/following/'.$followId);
        $this->assertSame(Response::HTTP_NO_CONTENT, $unfollow->getStatusCode());
        $this->assertSame([], self::decoded(self::api($follower, 'GET', '/api/friends'))['following']);
        $this->assertSame([], self::decoded(self::api($target, 'GET', '/api/friends'))['followers']);
    }

    public function testUnverifiedPartnerAccountsCannotSendFriendRequests(): void
    {
        $owner = static::createClient();
        self::registerHousehold($owner);

        self::api($owner, 'POST', '/api/auth/profiles', [
            'role' => 'partner',
            'name' => 'Thomas',
            'email' => 'thomas@example.com',
            'password' => 'secret123',
        ]);

        $partner = self::freshClient();
        self::api($partner, 'POST', '/api/auth/login', [
            'email' => 'thomas@example.com',
            'password' => 'secret123',
        ]);

        $response = self::api($partner, 'POST', '/api/friends/requests', ['username' => 'camille_test']);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
}
