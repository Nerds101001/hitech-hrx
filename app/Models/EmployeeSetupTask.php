<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * The attendance setup accounts owes a new joiner: employee ID and, unless
 * their department is exempt, biometric enrolment.
 */
class EmployeeSetupTask extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'user_id', 'needs_emp_id', 'needs_biometric', 'status',
        'emp_id_value', 'biometric_id_value', 'notes',
        'completed_by_id', 'completed_at', 'tenant_id',
    ];

    protected $casts = [
        'needs_emp_id'    => 'boolean',
        'needs_biometric' => 'boolean',
        'completed_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    /** Human summary of what is still outstanding, for the popup and card. */
    public function getRequirementLabelAttribute(): string
    {
        $bits = [];
        if ($this->needs_emp_id)    { $bits[] = 'Employee ID'; }
        if ($this->needs_biometric) { $bits[] = 'Biometric ID'; }

        return $bits ? implode(' and ', $bits) : 'Setup';
    }
}
