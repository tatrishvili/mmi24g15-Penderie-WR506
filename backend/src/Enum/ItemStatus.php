<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Lifecycle status of an item: available, lent out, sold, given away,
 * archived. Loans and sales (Phase 4/7) transition through these states.
 */
enum ItemStatus: string
{
    case Disponible = 'disponible';
    case Loue = 'loue';
    case Vendu = 'vendu';
    case Donne = 'donne';
    case Archive = 'archive';
}
