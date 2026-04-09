<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Task extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'tasks';

  protected $fillable = [
    'title',
    'description',
    'type',
    'assigned_by_id',
    'user_id',
    'client_id',
    'site_id',
    'latitude',
    'longitude',
    'max_radius',
    'start_date_time',
    'end_date_time',
    'for_date',
    'due_date',
    'start_form_id',
    'end_form_id',
    'is_geo_fence_enabled',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public function assignedBy()
  {
    return $this->belongsTo(User::class, 'assigned_by_id');
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function client()
  {
    return $this->belongsTo(Client::class);
  }

  public function site()
  {
    return $this->belongsTo(Site::class);
  }

  public function startForm()
  {
    return $this->belongsTo(Form::class, 'start_form_id');
  }

  public function endForm()
  {
    return $this->belongsTo(Form::class, 'end_form_id');
  }

  public function taskUpdates()
  {
    return $this->hasMany(TaskUpdate::class);
  }

  protected $casts = [
    'is_geo_fence_enabled' => 'boolean',
    'latitude' => 'float',
    'longitude' => 'float',
    'max_radius' => 'integer',
    'start_date_time' => 'datetime',
    'end_date_time' => 'datetime',
    'due_date' => 'datetime',
  ];


}
