<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class TaskCc extends Model
{
  use TenantTrait;

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
