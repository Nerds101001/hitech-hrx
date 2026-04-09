<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Form extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'forms';

  protected $fillable = [
    'name',
    'description',
    'status',
    'for',
    'is_client_required',
    'created_by_id',
    'updated_by_id',
    'tenant_id'
  ];

  public function fields()
  {
    return $this->hasMany(FormField::class);
  }

  public function entries()
  {
    return $this->hasMany(FormEntry::class);
  }

  protected $casts = [
    'is_client_required' => 'boolean'
  ];
}
