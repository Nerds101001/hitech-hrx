<?php

namespace App\Enums;

enum OrderType: string
{
  case PLAN = 'plan';

  case ADDITIONAL_USER = 'additional_user';

  case RENEWAL = 'renewal';

  case UPGRADE = 'upgrade';

  case DOWNGRADE = 'downgrade';

}
