<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TaskUpdate extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'task_updates';

  protected $fillable = [
    'task_id',
    'comment',
    'latitude',
    'longitude',
    'address',
    'file_url',
    'is_admin',
    'form_entry_id',
    'update_type',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  protected $casts = [
    'is_admin' => 'boolean',
    'latitude' => 'float',
    'longitude' => 'float',
  ];

  public function task()
  {
    return $this->belongsTo(Task::class, 'task_id');
  }

  public function formEntry()
  {
    return $this->belongsTo(FormEntry::class, 'form_entry_id');
  }
}
