<?php

namespace App\Enums;

enum TargetType: string
{
  case DAILY = 'daily';

  case WEEKLY = 'weekly';

  case MONTHLY = 'monthly';

  case QUARTERLY = 'quarterly';

  case HALF_YEARLY = 'half_yearly';

  case YEARLY = 'yearly';
}
