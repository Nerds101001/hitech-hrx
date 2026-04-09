<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
  use UserActionsTrait, TenantTrait;

  protected $table = 'notifications';

  protected $fillable = [
    'type',
    'notifiable_id',
    'notifiable_type',
    'data',
    'read_at',
  ];

  public function getTypeString(): string
  {
    return match ($this->type) {
      'App\Notifications\Leave\NewLeaveRequest', 'App\Notifications\Leave\CancelLeaveRequest', 'App\Notifications\Expense\CancelExpenseRequest', 'App\Notifications\Expense\NewExpenseRequest' => 'Approvals',
      'App\Notifications\Alerts\BreakAlert', 'App\Notifications\NewVisit' => 'Alerts',
      'App\Notifications\Chat\NewChatMessage' => 'Chat',
      'App\Notifications\Attendance\CheckInOut' => 'Attendance',
      default => 'System Notification',
    };
  }
}
