<?php

namespace App\Enums;

enum TaskStatus: string
{
  case NEW = 'new';

  case IN_PROGRESS = 'in_progress';

  case COMPLETED = 'completed';

  case HOLD = 'hold';

  case CANCELLED = 'cancelled';
  

  case DELETED = 'deleted';

}
