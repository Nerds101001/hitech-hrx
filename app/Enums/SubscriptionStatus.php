<?php

namespace App\Enums;

enum SubscriptionStatus : string
{
    case ACTIVE = 'active';

    case INACTIVE = 'inactive';

    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
}
