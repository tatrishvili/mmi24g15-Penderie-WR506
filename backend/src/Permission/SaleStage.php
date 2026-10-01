<?php

declare(strict_types=1);

namespace App\Permission;

/**
 * Manual sale audience stage (Matrix v3): the owner opens the sale to friends
 * first, then to followers. ACHAT is only possible inside the active stage.
 */
enum SaleStage: string
{
    case Friends = 'friends';
    case Followers = 'followers';
}
