<?php

namespace App\Enums;

enum UserStatus: string
{
  case ONLINE = 'online';

  case OFFLINE = 'offline';

  case BUSY = 'busy';

  case AWAY = 'away';

  case ON_CALL = 'on_call';

  case DO_NOT_DISTURB = 'do_not_disturb';

  case ON_LEAVE = 'on_leave';

  case ON_MEETING = 'ON_meeting';

  case UNKNOWN = 'unknown';

}
