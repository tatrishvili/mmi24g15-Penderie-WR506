<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inbox_notification')]
#[ORM\Index(name: 'inbox_recipient_id', columns: ['recipient', 'id'])]
class InboxNotification
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $readAt = null;
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 50)]
        public string $recipient,
        #[ORM\Column(length: 30)]
        public string $kind,
        #[ORM\Column]
        public int $referenceId,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }
}
