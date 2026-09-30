<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Notification extends Model implements AuditableContract
{
  use UserActionsTrait, TenantTrait, Auditable;

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
      'App\Notifications\Leave\NewLeaveRequest', 'App\Notifications\Leave\NewOutdoorDuty', 'App\Notifications\Leave\CancelLeaveRequest', 'App\Notifications\Expense\CancelExpenseRequest', 'App\Notifications\Expense\NewExpenseRequest' => 'Approvals',
      'App\Notifications\Alerts\BreakAlert', 'App\Notifications\NewVisit' => 'Alerts',
      'App\Notifications\Chat\NewChatMessage' => 'Chat',
      'App\Notifications\Attendance\CheckInOut' => 'Attendance',
      default => 'System Notification',
    };
  }
}
