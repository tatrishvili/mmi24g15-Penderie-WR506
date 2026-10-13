<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Access levels, ordered: DENIED < VIEW < EDIT < ADMIN (Matrix v3).
 * Used by PermissionResolver and AccessGrant (later phases).
 */
enum AccessLevel: string
{
    case Denied = 'denied';
    case View = 'view';
    case Edit = 'edit';
    case Admin = 'admin';

    public function rank(): int
    {
        return match ($this) {
            self::Denied => 0,
            self::View => 1,
            self::Edit => 2,
            self::Admin => 3,
        };
    }
}
