<?php

namespace App\Enums;

enum DomainRequestStatus : string
{
 case PENDING = 'pending';

  case APPROVED = 'approved';

  case REJECTED = 'rejected';

  case ACTIVE = 'active';

  case INACTIVE = 'inactive';

  case CANCELLED = 'cancelled';
}
