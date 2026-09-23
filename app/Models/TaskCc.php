<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TaskCc extends Model implements AuditableContract
{
  use TenantTrait, Auditable;

  protected $table = 'task_ccs';

  protected $fillable = ['task_id', 'user_id', 'created_by_id', 'tenant_id'];

  public function task()
  {
    return $this->belongsTo(Task::class);
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
