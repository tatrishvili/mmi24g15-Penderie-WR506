<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FriendRequestStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'friend_request')]
#[ORM\UniqueConstraint(name: 'uniq_friend_request_pair', columns: ['pair_key'])]
class FriendRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $sender;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(name: 'pair_key', length: 43)]
    private string $pairKey;

    #[ORM\Column(length: 10, enumType: FriendRequestStatus::class)]
    private FriendRequestStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $sender, User $recipient)
    {
        $this->sender = $sender;
        $this->recipient = $recipient;
        $this->pairKey = self::pairKey($sender, $recipient);
        $this->status = FriendRequestStatus::Pending;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function pairKey(User $first, User $second): string
    {
        $firstId = $first->getId();
        $secondId = $second->getId();

        if (null === $firstId || null === $secondId) {
            throw new \LogicException('Friend requests require persisted accounts.');
        }

        return min($firstId, $secondId).':'.max($firstId, $secondId);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSender(): User
    {
        return $this->sender;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function getPairKey(): string
    {
        return $this->pairKey;
    }

    public function getStatus(): FriendRequestStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function accept(): void
    {
        $this->status = FriendRequestStatus::Accepted;
    }
}
