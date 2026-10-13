<?php

declare(strict_types=1);

namespace App\Permission;

use App\Enum\AccessLevel;
use App\Enum\Capability;
use App\Enum\ResourceStatus;

/**
 * Pure Matrix v3 evaluator (no database).
 *
 * Layers, in precedence order:
 * 1. PERSONAL status = absolute veto (only the owner sees, nothing overrides).
 * 2. AccessGrant rows (custom audiences, DENIED overrides, share links) are
 *    evaluated by a LATER layer and always win over these defaults — except
 *    against PERSONAL, which they can never cross.
 * 3. This resolver = the status defaults for every (type, status, viewer).
 *
 * CUSTOM status with no grant evaluated here answers DENIED (closed by default).
 */
final class PermissionResolver
{
    public function resolve(
        ResourceType $type,
        ResourceStatus $status,
        ViewerRelation $viewer,
        ResolutionContext $context = new ResolutionContext(),
    ): Decision {
        // 1. PERSONAL veto: owner only, zero capabilities, no exception.
        if (ResourceStatus::Personal === $status) {
            return ViewerRelation::Owner === $viewer
                ? new Decision(AccessLevel::Admin)
                : new Decision(AccessLevel::Denied);
        }

        // 2. The owner is ADMIN of everything that is not PERSONAL-gated above.
        if (ViewerRelation::Owner === $viewer) {
            return new Decision(AccessLevel::Admin);
        }

        // 3. Share link holder: VIEW of the link perimeter + filtered capabilities.
        if (ViewerRelation::LinkHolder === $viewer) {
            return new Decision(AccessLevel::View, $this->filterCapabilities(ViewerRelation::Stranger, $context->grantedCaps, $context->saleStage));
        }

        // 4. Household adults: EDIT what they see (VIEW-only rows stay VIEW).
        if (ViewerRelation::HouseholdAdult === $viewer) {
            return match ($type) {
                ResourceType::Home, ResourceType::Item => new Decision(AccessLevel::Edit),
                ResourceType::Loan, ResourceType::Sale => new Decision(AccessLevel::View),
                default => $type->isStructure() || ResourceType::Look === $type
                    ? new Decision(AccessLevel::Edit)
                    : new Decision(AccessLevel::Denied),
            };
        }

        // 5. Household children: structure VIEW, own rows VIEW, nothing else.
        if (ViewerRelation::HouseholdChild === $viewer) {
            return $this->childOn($type, $status, $context);
        }

        // 6. Outside the household: pure audience logic.
        return new Decision($this->audienceLevel($type, $status, $viewer));
    }

    private function childOn(ResourceType $type, ResourceStatus $status, ResolutionContext $context): Decision
    {
        if (ResourceType::Home === $type) {
            return new Decision(AccessLevel::View);
        }

        if ($type->isStructure()) {
            // Structure is VIEW unless closed by CUSTOM with no grant.
            return ResourceStatus::Custom === $status
                ? new Decision(AccessLevel::Denied)
                : new Decision(AccessLevel::View);
        }

        if (ResourceType::Loan === $type || ResourceType::Sale === $type) {
            return $context->isOwn ? new Decision(AccessLevel::View) : new Decision(AccessLevel::Denied);
        }

        if (ResourceType::Look === $type) {
            return ResourceStatus::Private === $status || ResourceStatus::Custom === $status
                ? new Decision(AccessLevel::Denied)
                : new Decision(AccessLevel::View);
        }

        // Objects: a child never sees the parent's PRIVATE objects (Matrix v3 ¹).
        return match ($status) {
            ResourceStatus::Private, ResourceStatus::Custom => new Decision(AccessLevel::Denied),
            default => new Decision(AccessLevel::View),
        };
    }

    private function audienceLevel(ResourceType $type, ResourceStatus $status, ViewerRelation $viewer): AccessLevel
    {
        if (ResourceType::Home === $type || ResourceType::Loan === $type) {
            return AccessLevel::Denied;
        }

        if (ResourceType::Sale === $type) {
            return $this->saleAudience($status, $viewer);
        }

        if (ResourceType::Look === $type) {
            // A SHARED look is open to friends AND followers (single SHARED row).
            return match ($status) {
                ResourceStatus::Friends, ResourceStatus::Followers => $this->isFriendOrFollower($viewer) ? AccessLevel::View : AccessLevel::Denied,
                ResourceStatus::Public => AccessLevel::View,
                default => AccessLevel::Denied,
            };
        }

        // Items (+ structure when shared outside, same audience nesting).
        return match ($status) {
            ResourceStatus::Friends => ViewerRelation::Friend === $viewer ? AccessLevel::View : AccessLevel::Denied,
            ResourceStatus::Followers => $this->isFriendOrFollower($viewer) ? AccessLevel::View : AccessLevel::Denied,
            // C5 rule: structure (room/storage/category) is NEVER public.
            ResourceStatus::Public => $type->isStructure() ? AccessLevel::Denied : AccessLevel::View,
            default => AccessLevel::Denied,
        };
    }

    private function saleAudience(ResourceStatus $status, ViewerRelation $viewer): AccessLevel
    {
        return match ($status) {
            ResourceStatus::Friends => ViewerRelation::Friend === $viewer ? AccessLevel::View : AccessLevel::Denied,
            ResourceStatus::Followers => $this->isFriendOrFollower($viewer) ? AccessLevel::View : AccessLevel::Denied,
            ResourceStatus::Public => AccessLevel::View,
            default => AccessLevel::Denied,
        };
    }

    private function isFriendOrFollower(ViewerRelation $viewer): bool
    {
        return ViewerRelation::Friend === $viewer || ViewerRelation::Follower === $viewer;
    }

    /**
     * Filters requested capabilities by viewer relation (Matrix v3 capability rows):
     * - COMMENTER: friends + followers + link holders, when granted.
     * - PRÊTER: friends only (a validated borrow request), when granted.
     * - ACHAT: staged sale — friends inside the friends stage, followers once the
     *   followers stage is open, strangers never.
     *
     * @param list<Capability> $granted
     *
     * @return list<Capability>
     */
    public function filterCapabilities(ViewerRelation $viewer, array $granted, ?SaleStage $saleStage = null): array
    {
        $kept = [];

        foreach ($granted as $capability) {
            $keep = match ($capability) {
                Capability::Comment => ViewerRelation::Friend === $viewer
                    || ViewerRelation::Follower === $viewer
                    || ViewerRelation::Stranger === $viewer,
                Capability::Lend => ViewerRelation::Friend === $viewer,
                Capability::Buy => $this->canBuy($viewer, $saleStage),
            };

            if ($keep) {
                $kept[] = $capability;
            }
        }

        return $kept;
    }

    private function canBuy(ViewerRelation $viewer, ?SaleStage $saleStage): bool
    {
        if (null === $saleStage) {
            return false;
        }

        if (ViewerRelation::Friend === $viewer) {
            return true;
        }

        return ViewerRelation::Follower === $viewer && SaleStage::Followers === $saleStage;
    }
}
