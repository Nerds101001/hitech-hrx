<?php

namespace App\Models;

use App\Enums\Status;
use App\Traits\TenantTrait;
use OwenIt\Auditing\Auditable;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Department extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'departments';

  protected $fillable = [
    'name',
    'code',
    'notes',
    'parent_id',
    'status',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];

  protected $casts = [
    'status' => Status::class
  ];

  public function parent()
  {
    return $this->belongsTo(Department::class, 'parent_id');
  }

  public function children()
  {
    return $this->hasMany(Department::class, 'parent_id');
  }

  public function users()
  {
    return $this->hasManyThrough(User::class, Designation::class, 'department_id', 'designation_id', 'id', 'id');
  }

  public function designations()
  {
    return $this->hasMany(Designation::class, 'department_id');
  }

  public function managers()
  {
    return $this->belongsToMany(User::class, 'department_managers', 'department_id', 'user_id')->withTimestamps();
  }

  public function parentDepartment(){
    return $this->belongsTo(Department::class, 'parent_id');
  }
}
