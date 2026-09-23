<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class FormEntryField extends Model implements AuditableContract
{
  use  UserActionsTrait, TenantTrait, SoftDeletes, Auditable;

  protected $table = 'form_entry_fields';

  protected $fillable = [
    'form_entry_id',
    'form_field_id',
    'value',
    'created_by_id',
    'updated_by_id',
    'tenant_id'
  ];

  public function formEntry()
  {
    return $this->belongsTo(FormEntry::class);
  }

  public function formField()
  {
    return $this->belongsTo(FormField::class);
  }

}
