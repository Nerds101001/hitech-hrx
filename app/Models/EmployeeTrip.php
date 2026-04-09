<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeTrip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'approved_by_id',
        'trip_title',
        'trip_type',
        'start_date',
        'end_date',
        'destination',
        'purpose',
        'estimated_cost',
        'actual_cost',
        'notes',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'estimated_cost' => 'decimal',
        'actual_cost' => 'decimal',
        'approved_at' => 'datetime',
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

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'pending' => '<span class="badge bg-label-warning">Pending</span>',
            'approved' => '<span class="badge bg-label-success">Approved</span>',
            'rejected' => '<span class="badge bg-label-danger">Rejected</span>',
            'completed' => '<span class="badge bg-label-info">Completed</span>',
            default => '<span class="badge bg-label-secondary">' . $this->status . '</span>',
        };
    }

    public function getTripTypeLabelAttribute()
    {
        return match($this->trip_type) {
            'business' => 'Business Trip',
            'training' => 'Training',
            'conference' => 'Conference',
            default => ucfirst($this->trip_type),
        };
    }

    public function getDurationAttribute()
    {
        if ($this->start_date && $this->end_date) {
            return $this->start_date->diffInDays($this->end_date) . ' days';
        }
        return 'N/A';
    }
}
