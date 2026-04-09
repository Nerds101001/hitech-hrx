<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeePromotion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'previous_designation_id',
        'new_designation_id',
        'approved_by_id',
        'promotion_type',
        'promotion_date',
        'salary_increase',
        'reason',
        'notes',
        'status',
    ];

    protected $casts = [
        'promotion_date' => 'date',
        'salary_increase' => 'decimal',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function previousDesignation()
    {
        return $this->belongsTo(Designation::class, 'previous_designation_id');
    }

    public function newDesignation()
    {
        return $this->belongsTo(Designation::class, 'new_designation_id');
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

    public function getPromotionTypeLabelAttribute()
    {
        return match($this->promotion_type) {
            'merit' => 'Merit Based',
            'seniority' => 'Seniority',
            'performance' => 'Performance Based',
            default => ucfirst($this->promotion_type),
        };
    }
}
