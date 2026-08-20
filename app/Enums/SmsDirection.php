<?php

namespace App\Enums;

enum SmsDirection: string
{
  case OUTBOUND = 'outbound';

  case INBOUND = 'inbound';
}
