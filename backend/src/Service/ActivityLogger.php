<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\ChildAccount;
use App\Entity\Household;
use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Best-effort audit writer. Audit failures must never break the user request.
 */
final class ActivityLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CurrentActor $actor,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = [],
        ?Household $household = null,
        ?User $actorUser = null,
        ?Profile $actorProfile = null,
    ): void {
        try {
            $tokenActor = $this->actor->tokenUser();
            $actorUser ??= $tokenActor instanceof User ? $tokenActor : null;

            if (null === $actorProfile && $tokenActor instanceof ChildAccount) {
                $actorProfile = $tokenActor->profile;
            }

            if (null === $actorProfile && null !== $actorUser) {
                $actorProfile = $this->actor->profile();
            }

            $household ??= $this->safeHousehold() ?? $actorProfile?->getHousehold();
            $profileHousehold = $actorProfile?->getHousehold();
            if (null !== $profileHousehold) {
                $household = $profileHousehold;
            }

            $this->em->persist(new ActivityLog(
                $action,
                $household,
                $actorUser,
                $actorProfile,
                $subjectType,
                $subjectId,
                $metadata,
            ));
            $this->em->flush();
        } catch (\Throwable) {
            // Logging is observability, not a reason to fail the request.
        }
    }

    private function safeHousehold(): ?Household
    {
        try {
            return $this->actor->household();
        } catch (\Throwable) {
            return null;
        }
    }
}
