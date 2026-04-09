<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'from_department_id',
        'to_department_id',
        'from_team_id',
        'to_team_id',
        'approved_by_id',
        'transfer_date',
        'effective_date',
        'reason',
        'notes',
        'status',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'effective_date' => 'date',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromDepartment()
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment()
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function fromTeam()
    {
        return $this->belongsTo(Team::class, 'from_team_id');
    }

    public function toTeam()
    {
        return $this->belongsTo(Team::class, 'to_team_id');
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
}
