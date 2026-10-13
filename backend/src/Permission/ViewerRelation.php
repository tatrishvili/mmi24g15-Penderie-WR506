<?php

declare(strict_types=1);

namespace App\Permission;

/**
 * Who looks at the resource, relative to its owner (Matrix v3 columns).
 * LinkHolder = anyone opening a valid share link (friend, follower or stranger).
 */
enum ViewerRelation: string
{
    case Owner = 'owner';
    case HouseholdAdult = 'household_adult';
    case HouseholdChild = 'household_child';
    case Friend = 'friend';
    case Follower = 'follower';
    case Stranger = 'stranger';
    case LinkHolder = 'link_holder';
}
