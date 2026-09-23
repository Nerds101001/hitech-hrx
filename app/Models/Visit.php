<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Visit extends Model implements AuditableContract
{
  use UserActionsTrait, TenantTrait, SoftDeletes, Auditable;

  protected $table = 'visits';

  protected $fillable = [
    'client_id',
    'attendance_log_id',
    'remarks',
    'img_url',
    'latitude',
    'longitude',
    'address',
    'created_by_id',
    'updated_by_id',
  ];

  protected $casts = [
    'latitude' => 'float',
    'longitude' => 'float',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function client()
  {
    return $this->belongsTo(Client::class, 'client_id');
  }

  public function attendanceLog()
  {
    return $this->belongsTo(AttendanceLog::class);
  }

  public function createdBy()
  {
    return $this->belongsTo(User::class, 'created_by_id');
  }
}
