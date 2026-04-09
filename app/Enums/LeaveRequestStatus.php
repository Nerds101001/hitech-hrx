<?php

namespace App\Enums;

enum LeaveRequestStatus: string
{
  case PENDING = 'pending';

  case APPROVED = 'approved';

  case REJECTED = 'rejected';

  case CANCELLED = 'cancelled';

  case CANCELLED_BY_ADMIN = 'cancelled_by_admin';
  
}
