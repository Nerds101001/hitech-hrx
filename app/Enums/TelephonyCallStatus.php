<?php

namespace App\Enums;

enum TelephonyCallStatus: string
{
  case QUEUED = 'queued';

  case RINGING = 'ringing';

  case CONNECTED = 'connected';

  case BUSY = 'busy';

  case NO_ANSWER = 'no_answer';

  case INVALID_NUMBER = 'invalid_number';

  case FAILED = 'failed';

  case ENDED = 'ended';
}
