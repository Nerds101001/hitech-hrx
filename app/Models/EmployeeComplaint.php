<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeComplaint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'complainant_id',
        'respondent_id',
        'approved_by_id',
        'complaint_type',
        'severity',
        'complaint_date',
        'description',
        'resolution',
        'notes',
        'status',
    ];

    protected $casts = [
        'complaint_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    // Relationships
    public function complainant()
    {
        return $this->belongsTo(User::class, 'complainant_id');
    }

    public function respondent()
    {
        return $this->belongsTo(User::class, 'respondent_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInvestigating($query)
    {
        return $query->where('status', 'investigating');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeDismissed($query)
    {
        return $query->where('status', 'dismissed');
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'open' => '<span class="badge bg-label-warning">Open</span>',
            'investigating' => '<span class="badge bg-label-info">Investigating</span>',
            'resolved' => '<span class="badge bg-label-success">Resolved</span>',
            'dismissed' => '<span class="badge bg-label-secondary">Dismissed</span>',
            default => '<span class="badge bg-label-secondary">' . $this->status . '</span>',
        };
    }

    public function getSeverityBadgeAttribute()
    {
        return match($this->severity) {
            'low' => '<span class="badge bg-label-info">Low</span>',
            'medium' => '<span class="badge bg-label-warning">Medium</span>',
            'high' => '<span class="badge bg-label-danger">High</span>',
            'critical' => '<span class="badge bg-label-danger">Critical</span>',
            default => '<span class="badge bg-label-secondary">' . ucfirst($this->severity) . '</span>',
        };
    }

    public function getComplaintTypeLabelAttribute()
    {
        return match($this->complaint_type) {
            'harassment' => 'Harassment',
            'discrimination' => 'Discrimination',
            'safety' => 'Safety Issue',
            'policy' => 'Policy Violation',
            default => ucfirst($this->complaint_type),
        };
    }
}
