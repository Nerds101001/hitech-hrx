<?php

namespace App\Models;

use App\Enums\ShiftType;
use App\Enums\Status;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Shift extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'shifts';

  protected $fillable = [
    'name',
    'code',
    'notes',
    'start_date',
    'end_date',
    'start_time',
    'end_time',
    'sunday',
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'is_infinite',
    'over_time_threshold',
    'is_default',
    'is_over_time_enabled',
    'is_break_enabled',
    'break_time',
    'shift_type',
    'is_flexible',
    'flex_start_time',
    'flex_end_time',
    'min_working_hours',
    'status',
    'timezone',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  protected $casts = [
    'start_date' => 'date',
    'end_date' => 'date',
    'start_time' => 'datetime:H:i:s',
    'end_time' => 'datetime:H:i:s',
    'sunday' => 'boolean',
    'monday' => 'boolean',
    'tuesday' => 'boolean',
    'wednesday' => 'boolean',
    'thursday' => 'boolean',
    'friday' => 'boolean',
    'saturday' => 'boolean',
    'is_infinite' => 'boolean',
    'is_default' => 'boolean',
    'is_over_time_enabled' => 'boolean',
    'is_break_enabled' => 'boolean',
    'shift_type' => ShiftType::class,
    'status' => Status::class
  ];

  public function userShifts()
  {
    return $this->hasMany(UserShift::class);
  }
}
