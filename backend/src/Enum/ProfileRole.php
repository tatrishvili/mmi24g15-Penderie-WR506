<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Role of a Profile inside a Household (MLD v2, Matrix v3).
 */
enum ProfileRole: string
{
    case Parent = 'parent';
    case Partner = 'partner';
    case Child = 'child';

    public function isAdult(): bool
    {
        return $this !== self::Child;
    }

    public function securityRole(): string
    {
        return match ($this) {
            self::Parent => 'ROLE_PARENT',
            self::Partner => 'ROLE_PARTNER',
            self::Child => 'ROLE_CHILD',
        };
    }
}
