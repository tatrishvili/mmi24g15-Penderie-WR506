<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Visibility status of a resource (Matrix v3). Defined now, applied to
 * Item/Look/Category/... entities when they are created in later phases.
 */
enum ResourceStatus: string
{
    case Private = 'private';
    case Friends = 'friends';
    case Followers = 'followers';
    case Custom = 'custom';
    case Public = 'public';
    case Personal = 'personal';
}
