<?php

declare(strict_types=1);

namespace App\Permission;

use App\Enum\AccessLevel;

/**
 * What kind of resource is evaluated (Matrix v3 rows).
 */
enum ResourceType: string
{
    case Item = 'item';
    case Category = 'category';
    case Storage = 'storage';
    case Room = 'room';
    case Home = 'home';
    case Look = 'look';
    case Loan = 'loan';
    case Sale = 'sale';

    public function isStructure(): bool
    {
        return self::Category === $this || self::Storage === $this || self::Room === $this;
    }

    public function maxLevel(): AccessLevel
    {
        // Household adults EDIT everything they can see (partner = EDIT everywhere).
        return match ($this) {
            self::Loan, self::Sale => AccessLevel::View,
            default => AccessLevel::Edit,
        };
    }
}
