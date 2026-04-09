<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormEntry extends Model
{
  use  UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'form_entries';

  protected $fillable = [
    'attendance_log_id',
    'form_id',
    'user_id',
    'client_id',
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

  public function client()
  {
    return $this->belongsTo(Client::class);
  }

  public function formEntryFields()
  {
    return $this->hasMany(FormEntryField::class);
  }

  public function attendanceLog()
  {
    return $this->belongsTo(AttendanceLog::class);
  }
}
