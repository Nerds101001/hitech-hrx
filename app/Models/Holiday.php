<?php

namespace App\Models;

use App\Enums\Status;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Holiday extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'holidays';

  protected $fillable = [
    'name',
    'date',
    'code',
    'notes',
    'status',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
    'site_id',
  ];

  public function site()
  {
    return $this->belongsTo(Site::class, 'site_id');
  }

  protected $casts = [
    'date' => 'date',
    'status' => Status::class,
  ];


}
