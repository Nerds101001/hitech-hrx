<?php

namespace App\Enums;

enum CallOrigin: string
{
  /** Placed through HRX's own initiate endpoint. */
  case HRX = 'hrx';

  /** First seen via a vendor webhook for a call HRX never placed — manual
   *  dialing, or any path outside the click-to-call button. */
  case EXTERNAL = 'external';
}
