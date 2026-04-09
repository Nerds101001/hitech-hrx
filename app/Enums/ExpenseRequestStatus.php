<?php

namespace App\Enums;

enum ExpenseRequestStatus: string
{
  case PENDING = 'pending';

  case APPROVED = 'approved';

  case REJECTED = 'rejected';

  case PROCESSED = 'processed';

}
