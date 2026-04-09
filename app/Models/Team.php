<?php

namespace App\Models;

use App\Enums\Status;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Team extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'teams';

  protected $fillable = [
    'team_head_id',
    'name',
    'code',
    'notes',
    'is_chat_enabled',
    'is_task_enabled',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public $casts =[
    'is_chat_enabled' => 'boolean',
    'status' => Status::class,
  ];

  public function teamHead()
  {
    return $this->belongsTo(User::class, 'team_head_id');
  }

  public function managers()
  {
    return $this->belongsToMany(User::class, 'team_managers', 'team_id', 'user_id')->withTimestamps();
  }

  public function users()
  {
    return $this->belongsToMany(User::class, 'team_id');
  }
}
