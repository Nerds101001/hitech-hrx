<?php

namespace App\Enums;

enum CrmContactRole: string
{
  case PURCHASE = 'purchase';

  case FINANCE = 'finance';

  case ACCOUNTS = 'accounts';

  case ADMIN = 'admin';

  case OTHER = 'other';

  public function label(): string
  {
    return match ($this) {
      self::PURCHASE => 'Purchase',
      self::FINANCE  => 'Finance',
      self::ACCOUNTS => 'Accounts',
      self::ADMIN    => 'Admin',
      self::OTHER    => 'Other',
    };
  }
}
