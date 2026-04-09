<?php

namespace App\Enums;

enum IncentiveType: string
{
  case FIXED = 'fixed';
  case PERCENTAGE = 'percentage';
  case NONE = 'none';
}
