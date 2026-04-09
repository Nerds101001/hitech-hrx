<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeTermination extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'approved_by_id',
        'termination_date',
        'last_working_day',
        'termination_type',
        'reason',
        'termination_notes',
        'is_eligible_for_rehire',
        'status',
    ];

    protected $casts = [
        'termination_date' => 'date',
        'last_working_day' => 'date',
        'approved_at' => 'datetime',
        'is_eligible_for_rehire' => 'boolean',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'pending' => '<span class="badge bg-label-warning">Pending</span>',
            'approved' => '<span class="badge bg-label-success">Approved</span>',
            'rejected' => '<span class="badge bg-label-danger">Rejected</span>',
            default => '<span class="badge bg-label-secondary">' . $this->status . '</span>',
        };
    }

    public function getTerminationTypeLabelAttribute()
    {
        return match($this->termination_type) {
            'misconduct' => 'Misconduct',
            'performance' => 'Performance Issues',
            'redundancy' => 'Redundancy',
            'contract_end' => 'Contract End',
            default => ucfirst($this->termination_type),
        };
    }

    public function getIsEligibleForRehireLabelAttribute()
    {
        return $this->is_eligible_for_rehire ? 
            '<span class="badge bg-label-success">Eligible</span>' : 
            '<span class="badge bg-label-danger">Not Eligible</span>';
    }
}
