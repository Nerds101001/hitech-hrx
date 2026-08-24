<?php

namespace App\Enums;

enum CrmActivityType: string
{
  /** Manually typed by a user. */
  case COMMENT = 'comment';

  /** Auto-logged when a linked telephony call is dispositioned. */
  case CALL = 'call';

  /** Reserved for future auto-logged events (mapping created, team changed, ...). */
  case SYSTEM = 'system';
}
