<?php

namespace App\Enums;

enum CallEndedBy: string
{
  case AGENT = 'agent';

  case CUSTOMER = 'customer';

  case SYSTEM = 'system';
}
