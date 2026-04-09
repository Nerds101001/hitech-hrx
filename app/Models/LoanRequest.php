<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class LoanRequest extends Model implements AuditableContract
{
    use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

    protected $table = 'loan_requests';

    protected $fillable = [
      'user_id',
      'amount',
      'approved_amount',
      'action_taken_by_id',
      'action_taken_at',
      'admin_remarks',
      'remarks',
      'status',
      'created_by_id',
      'updated_by_id',
      'tenant_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actionTakenBy()
    {
        return $this->belongsTo(User::class, 'action_taken_by_id');
    }

    protected $casts = [
        'action_taken_at' => 'datetime',
    ];
}
