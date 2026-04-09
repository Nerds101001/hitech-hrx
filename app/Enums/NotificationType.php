<?php

namespace App\Enums;

enum NotificationType: string
{
  case ALL = 'all';
  case USER = 'user';
  case ROLE = 'role';
  case ROLES = 'roles';
  case USERS = 'users';
}
