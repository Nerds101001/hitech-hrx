<?php

namespace App\Enums;

enum DocumentApprovalStatus: string
{
  case PENDING = 'pending';

  case APPROVED = 'approved';

  case REJECTED = 'rejected';

  case DELETED = 'deleted';
}
