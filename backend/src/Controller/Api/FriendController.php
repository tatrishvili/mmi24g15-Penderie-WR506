<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Follow;
use App\Entity\FriendRequest;
use App\Entity\User;
use App\Enum\FriendRequestStatus;
use App\Service\CommunityIdentityService;
use App\Service\CurrentActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/friends')]
final class FriendController extends AbstractController
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly EntityManagerInterface $em,
        private readonly CommunityIdentityService $identities,
    ) {
    }

    #[Route('', name: 'api_friends_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->actor->requireUser();
        if (!$user->isEmailVerified()) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Verify your e-mail before viewing social connections.');
        }

        $requests = $this->em->getRepository(FriendRequest::class)->createQueryBuilder('request')
            ->where('request.sender = :user OR request.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('request.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $friends = [];
        $incoming = [];
        $outgoing = [];
        $followRepository = $this->em->getRepository(Follow::class);
        $following = $followRepository->findBy(['follower' => $user], ['createdAt' => 'DESC']);
        $followers = $followRepository->findBy(['followed' => $user], ['createdAt' => 'DESC']);

        foreach ($requests as $friendRequest) {
            if (!$friendRequest instanceof FriendRequest) {
                continue;
            }

            $otherUser = $friendRequest->getSender()->getId() === $user->getId()
                ? $friendRequest->getRecipient()
                : $friendRequest->getSender();
            $entry = [
                'id' => $friendRequest->getId(),
                'user' => $this->userPayload($otherUser),
            ];

            if (FriendRequestStatus::Accepted === $friendRequest->getStatus()) {
                $friends[] = $entry;
            } elseif ($friendRequest->getRecipient()->getId() === $user->getId()) {
                $incoming[] = $entry;
            } else {
                $outgoing[] = $entry;
            }
        }

        return new JsonResponse([
            'friends' => $friends,
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'following' => array_map(
                fn (Follow $follow): array => $this->followPayload($follow, $follow->getFollowed()),
                $following,
            ),
            'followers' => array_map(
                fn (Follow $follow): array => $this->followPayload($follow, $follow->getFollower()),
                $followers,
            ),
        ]);
    }

    #[Route('/following', name: 'api_friends_follow_create', methods: ['POST'])]
    public function follow(Request $request): JsonResponse
    {
        $follower = $this->actor->requireUser();
        if (!$follower->isEmailVerified()) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Verify your e-mail before following accounts.');
        }

        $followed = $this->findVerifiedUser($request);
        if ($followed->getId() === $follower->getId()) {
            throw new ConflictHttpException('You cannot follow your own account.');
        }

        $follow = $this->findFollow($follower, $followed);
        if (!$follow instanceof Follow) {
            $follow = new Follow($follower, $followed);
            $this->em->persist($follow);
            $this->em->flush();

            return new JsonResponse(['follow' => $this->followPayload($follow, $followed)], Response::HTTP_CREATED);
        }

        return new JsonResponse(['follow' => $this->followPayload($follow, $followed)]);
    }

    #[Route('/following/{id}', name: 'api_friends_follow_remove', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function unfollow(int $id): Response
    {
        $user = $this->actor->requireUser();
        $follow = $this->em->getRepository(Follow::class)->find($id);

        if (!$follow instanceof Follow || $follow->getFollower()->getId() !== $user->getId()) {
            throw new NotFoundHttpException('Follow not found.');
        }

        $this->em->remove($follow);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/requests', name: 'api_friends_request_create', methods: ['POST'])]
    public function createRequest(Request $request): JsonResponse
    {
        $sender = $this->actor->requireUser();
        if (!$sender->isEmailVerified()) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Verify your e-mail before adding friends.');
        }

        $recipient = $this->findVerifiedUser($request);
        if ($recipient->getId() === $sender->getId()) {
            throw new ConflictHttpException('You cannot send a friend request to yourself.');
        }

        $pairKey = FriendRequest::pairKey($sender, $recipient);
        $existing = $this->em->getRepository(FriendRequest::class)->findOneBy(['pairKey' => $pairKey]);
        if ($existing instanceof FriendRequest) {
            if (!hash_equals($existing->getPairKey(), $pairKey)) {
                throw new \LogicException('The friend-request pair lookup returned an unrelated record.');
            }

            if (FriendRequestStatus::Pending === $existing->getStatus()) {
                $this->ensureFollow($sender, $recipient);
                $this->em->flush();
            }

            throw new ConflictHttpException(FriendRequestStatus::Accepted === $existing->getStatus() ? 'You are already friends.' : 'A friend request for this account is already pending.');
        }

        $friendRequest = new FriendRequest($sender, $recipient);
        $this->em->persist($friendRequest);
        $this->ensureFollow($sender, $recipient);
        $this->em->flush();

        return new JsonResponse(['request' => $this->requestPayload($friendRequest, $recipient)], Response::HTTP_CREATED);
    }

    #[Route('/requests/{id}/accept', name: 'api_friends_request_accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function acceptRequest(int $id): JsonResponse
    {
        $user = $this->actor->requireUser();
        if (!$user->isEmailVerified()) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Verify your e-mail before accepting friend requests.');
        }

        $friendRequest = $this->em->getRepository(FriendRequest::class)->find($id);

        if (!$friendRequest instanceof FriendRequest || $friendRequest->getRecipient()->getId() !== $user->getId()) {
            throw new NotFoundHttpException('Friend request not found.');
        }
        if (FriendRequestStatus::Pending !== $friendRequest->getStatus()) {
            throw new ConflictHttpException('This friend request is no longer pending.');
        }

        $friendRequest->accept();
        $this->ensureFollow($user, $friendRequest->getSender());
        $this->em->flush();

        return new JsonResponse(['friend' => $this->requestPayload($friendRequest, $friendRequest->getSender())]);
    }

    #[Route('/requests/{id}', name: 'api_friends_request_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteRequest(int $id): Response
    {
        $user = $this->actor->requireUser();
        $friendRequest = $this->em->getRepository(FriendRequest::class)->find($id);

        if (!$friendRequest instanceof FriendRequest
            || FriendRequestStatus::Pending !== $friendRequest->getStatus()
            || ($friendRequest->getSender()->getId() !== $user->getId() && $friendRequest->getRecipient()->getId() !== $user->getId())) {
            throw new NotFoundHttpException('Friend request not found.');
        }

        $this->em->remove($friendRequest);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'api_friends_remove', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function removeFriend(int $id): Response
    {
        $user = $this->actor->requireUser();
        $friendRequest = $this->em->getRepository(FriendRequest::class)->find($id);

        if (!$friendRequest instanceof FriendRequest
            || FriendRequestStatus::Accepted !== $friendRequest->getStatus()
            || ($friendRequest->getSender()->getId() !== $user->getId() && $friendRequest->getRecipient()->getId() !== $user->getId())) {
            throw new NotFoundHttpException('Friend not found.');
        }

        $this->em->remove($friendRequest);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    private function requestPayload(FriendRequest $friendRequest, User $other): array
    {
        return [
            'id' => $friendRequest->getId(),
            'user' => $this->userPayload($other),
        ];
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        $identity = $this->identities->presentation($user);

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'firstName' => $identity['displayName'],
            ...$identity,
        ];
    }

    /**
     * @return array{ id: int, user: array<string, mixed> }
     */
    private function followPayload(Follow $follow, User $other): array
    {
        return [
            'id' => $follow->getId() ?? throw new \LogicException('A follow must be persisted before it is returned.'),
            'user' => $this->userPayload($other),
        ];
    }

    private function findVerifiedUser(Request $request): User
    {
        try {
            $data = $request->toArray();
        } catch (JsonException) {
            throw new UnprocessableEntityHttpException('Request body must be valid JSON.');
        }

        $username = $data['username'] ?? null;
        if (!\is_string($username) || 1 !== preg_match('/^[a-zA-Z0-9_]{3,30}$/', trim($username))) {
            throw new UnprocessableEntityHttpException('Enter a username with 3 to 30 letters, numbers, or underscores.');
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['username' => strtolower(trim($username))]);
        if (!$user instanceof User || !$user->isEmailVerified()) {
            throw new NotFoundHttpException('No account was found for that username.');
        }

        return $user;
    }

    private function findFollow(User $follower, User $followed): ?Follow
    {
        $follow = $this->em->getRepository(Follow::class)->findOneBy([
            'follower' => $follower,
            'followed' => $followed,
        ]);

        return $follow instanceof Follow ? $follow : null;
    }

    private function ensureFollow(User $follower, User $followed): void
    {
        if (!$this->findFollow($follower, $followed) instanceof Follow) {
            $this->em->persist(new Follow($follower, $followed));
        }
    }
}
