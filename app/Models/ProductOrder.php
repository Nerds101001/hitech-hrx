<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class ProductOrder extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'orders';

  protected $fillable = [
    'attendance_log_id',
    'user_id',
    'client_id',
    'order_no',
    'total',
    'discount',
    'tax',
    'grand_total',
    'quantity',
    'notes',
    'user_remarks',
    'admin_remarks',
    'cancel_remarks',
    'processed_by_id',
    'processed_at',
    'completed_by_id',
    'completed_at',
    'cancelled_by_id',
    'cancelled_at',
    'status',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];
  protected $casts = [
    'total' => 'float',
    'tax' => 'float',
    'discount' => 'float',
    'grand_total' => 'float',
    'quantity' => 'integer',
  ];

  public function client()
  {
    return $this->belongsTo(Client::class);
  }

  public function orderLines()
  {
    return $this->hasMany(OrderLine::class, 'order_id');
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function attendanceLog()
  {
    return $this->belongsTo(AttendanceLog::class);
  }

}
