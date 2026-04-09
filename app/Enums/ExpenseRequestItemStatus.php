<?php

namespace App\Enums;

enum ExpenseRequestItemStatus: string
{
  
  case PENDING = 'pending';

  case APPROVED = 'approved';

  case REJECTED = 'rejected';

  case PROCESSED = 'processed';

  case CANCELLED = 'cancelled';

  case HOLD = 'hold';
}
