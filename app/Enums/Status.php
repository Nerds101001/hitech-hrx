<?php

namespace App\Enums;

enum Status : string
{
   case ACTIVE = 'active';

   case INACTIVE = 'inactive';

   case DELETED = 'deleted';
}
