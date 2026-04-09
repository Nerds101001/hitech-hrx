<?php

namespace App\Enums;

enum TaskUpdateType: string
{
  case TEXT = 'text';

  case IMAGE = 'image';

  case FILE = 'file';

  case LINK = 'link';

  case START = 'start';

  case COMPLETE = 'complete';

  case HOLD = 'hold';

  case RESUME = 'resume';

}
