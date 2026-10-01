<?php

declare(strict_types=1);

namespace App\Permission;

use App\Enum\Capability;

/**
 * Extra facts the pure status matrix cannot decide alone:
 * - $isOwn: the viewer is the borrower of this loan / the buyer of this sale
 *   ("les siens" rows for household children).
 * - $grantedCaps: capabilities carried by a share link (COMMENTER / PRÊTER / ACHAT).
 * - $saleStage: active audience stage of a sale (friends first, then followers).
 */
final class ResolutionContext
{
    /**
     * @param list<Capability> $grantedCaps
     */
    public function __construct(
        public readonly bool $isOwn = false,
        public readonly array $grantedCaps = [],
        public readonly ?SaleStage $saleStage = null,
    ) {
    }
}
