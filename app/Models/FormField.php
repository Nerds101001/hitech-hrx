<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class FormField extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'form_fields';

  protected $fillable = [
    'form_id',
    'order',
    'field_type',
    'label',
    'placeholder',
    'is_required',
    'min_length',
    'max_length',
    'default_values',
    'values',
    'is_enabled',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  protected $casts = [
    'is_required' => 'boolean',
    'is_enabled' => 'boolean',
    'values' => 'array',
    'default_values' => 'array',
  ];

  public function form()
  {
    return $this->belongsTo(Form::class);
  }
}
