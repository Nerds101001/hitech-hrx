<?php

namespace App\Enums;

enum OfflineRequestStatus: string
{
  case PENDING = 'pending';

  case APPROVED = 'approved';

  case REJECTED = 'rejected';

  case  CANCELLED = 'cancelled';
}
