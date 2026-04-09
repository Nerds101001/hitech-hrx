<?php

namespace App\Enums;

enum PayslipStatus: string
{
  case GENERATED = 'generated';

  case DRAFT = 'draft';

  case APPROVED = 'approved';

  case AUTO_APPROVED = 'auto_approved';

  case REJECTED = 'rejected';

  case PENDING = 'pending';

  case FIRST_APPROVAL = 'first_approval';

  case SECOND_APPROVAL = 'second_approval';

  case PAID = 'paid';

  case HOLD = 'hold';

  case PARTIAL_PAID = 'partial_paid';

}
