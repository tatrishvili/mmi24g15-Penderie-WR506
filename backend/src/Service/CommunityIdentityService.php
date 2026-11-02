<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommunityIdentity;
use App\Entity\Follow;
use App\Entity\FriendRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class CommunityIdentityService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(User $owner): ?CommunityIdentity
    {
        return $this->em->getRepository(CommunityIdentity::class)->findOneBy(['owner' => $owner]);
    }

    /** @return array{displayName: string, photoUrl: ?string} */
    public function presentation(User $owner): array
    {
        $identity = $this->find($owner);

        return ['displayName' => $identity->displayName ?? $owner->getUsername() ?? 'Penderie',
            'photoUrl' => null !== $identity && '' !== $identity->photo && null !== $owner->getUsername()
                ? '/api/social/profiles/'.rawurlencode($owner->getUsername()).'/photo' : null];
    }

    public function save(User $owner, string $name, string $photo, bool $publish, string $bio = '', ?bool $shareBio = null): void
    {
        $identity = $this->find($owner);
        if (!$publish) {
            if (null !== $identity) {
                $this->em->remove($identity);
            }

            return;
        }
        // Re-encode and resize the approved image; never publish original metadata.
        $safePhoto = '';
        if ('' !== $photo) {
            $bytes = base64_decode(explode(',', $photo, 2)[1] ?? '', true);
            $image = false !== $bytes ? @imagecreatefromstring($bytes) : false;
            if (false !== $image) {
                $resized = imagescale($image, min(256, imagesx($image)));
                if (false !== $resized) {
                    ob_start();
                    imagepng($resized);
                    $safePhoto = base64_encode((string) ob_get_clean());
                    imagedestroy($resized);
                }
                imagedestroy($image);
            }
        }
        if (null === $identity) {
            $identity = new CommunityIdentity($owner, $name, $safePhoto);
            $this->em->persist($identity);
        } else {
            $identity->displayName = $name;
            $identity->photo = $safePhoto;
        }
        if (null !== $shareBio) {
            $identity->bioShared = $shareBio;
            $identity->bio = $shareBio ? $bio : '';
        }
    }

    /** Aggregate counts; connection lists are restricted to their owner.
     * @return array{friends: int, followers: int, following: int}
     */
    public function counts(User $owner): array
    {
        $friends = $this->em->getRepository(FriendRequest::class)->createQueryBuilder('r')
            ->select('COUNT(r.id)')->where('(r.sender = :owner OR r.recipient = :owner) AND r.status = :status')
            ->setParameter('owner', $owner)->setParameter('status', 'accepted')->getQuery()->getSingleScalarResult();

        return ['friends' => (int) $friends,
            'followers' => $this->em->getRepository(Follow::class)->count(['followed' => $owner]),
            'following' => $this->em->getRepository(Follow::class)->count(['follower' => $owner])];
    }
}
