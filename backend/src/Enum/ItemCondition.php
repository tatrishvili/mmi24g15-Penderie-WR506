<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Physical condition of an item (CONCEPT.md §13).
 */
enum ItemCondition: string
{
    case Neuf = 'neuf';
    case CommeNeuf = 'comme_neuf';
    case TresBon = 'tres_bon';
    case Bon = 'bon';
    case AReparer = 'a_reparer';
    case Abime = 'abime';
}
