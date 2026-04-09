<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeResignation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'approved_by_id',
        'resignation_date',
        'last_working_day',
        'reason_type',
        'reason',
        'exit_interview_notes',
        'is_rehireable',
        'status',
    ];

    protected $casts = [
        'resignation_date' => 'date',
        'last_working_day' => 'date',
        'approved_at' => 'datetime',
        'is_rehireable' => 'boolean',
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

    public function getReasonTypeLabelAttribute()
    {
        return match($this->reason_type) {
            'voluntary' => 'Voluntary Resignation',
            'involuntary' => 'Involuntary Termination',
            'retirement' => 'Retirement',
            default => ucfirst($this->reason_type),
        };
    }

    public function getIsRehireableLabelAttribute()
    {
        return $this->is_rehireable ? 
            '<span class="badge bg-label-success">Rehireable</span>' : 
            '<span class="badge bg-label-danger">Not Rehireable</span>';
    }
}
