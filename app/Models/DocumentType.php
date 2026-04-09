<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DocumentType extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'document_types';

  protected $fillable = [
    'name',
    'code',
    'notes',
    'is_required',
    'status',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];

  protected $casts = [
    'is_required' => 'boolean'
  ];
}
