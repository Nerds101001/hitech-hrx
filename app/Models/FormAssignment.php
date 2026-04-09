<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class FormAssignment extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'form_assignments';

  protected $fillable = [
    'form_id',
    'user_id',
    'team_id',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public function form()
  {
    return $this->belongsTo(Form::class);
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function team()
  {
    return $this->belongsTo(Team::class);
  }

  public function entries()
  {
    return $this->hasMany(FormEntry::class);
  }

  public function fields()
  {
    return $this->hasManyThrough(FormField::class, Form::class);
  }
  
}
