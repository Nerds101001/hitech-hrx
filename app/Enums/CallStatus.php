<?php

namespace App\Enums;

enum CallStatus: string
{
  case MISSED = 'missed';

  case COMPLETED = 'completed';

  case DECLINED = 'declined';
}
