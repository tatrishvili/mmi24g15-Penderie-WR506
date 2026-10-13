<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Granted capabilities on top of VIEW (Matrix v3):
 * COMMENTER (friends + followers if granted), PRÊTER (friends only, validated),
 * ACHAT (staged: friends first, then followers).
 */
enum Capability: string
{
    case Comment = 'comment';
    case Lend = 'lend';
    case Buy = 'buy';
}
