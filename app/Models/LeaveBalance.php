<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class LeaveBalance extends Model implements AuditableContract
{
    use TenantTrait, Auditable;

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'balance',
        'used',
        'carry_forward_last_year',
        'accrued_this_year',
        'tenant_id',
    ];

    protected $casts = [
        'balance' => 'float',
        'used'    => 'float',
        'carry_forward_last_year' => 'float',
        'accrued_this_year' => 'float',
    ];

    public function transformAudit(array $data): array
    {
        if (isset($this->auditCustomMessage)) {
            $data['user_agent'] = $this->auditCustomMessage;
        }
        return $data;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
