<?php

namespace App\Models;

use App\Enums\Status;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class BankAccount extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'bank_accounts';

  protected $fillable = [
    'user_id',
    'bank_name',
    'bank_code',
    'account_name',
    'account_number',
    'branch_name',
    'branch_code',
    'passbook_path',
    'tax_no',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];
  protected $casts = [
    'status' => Status::class
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

}
