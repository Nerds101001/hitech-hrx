<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeWarning extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'given_by_id',
        'warning_type',
        'severity',
        'warning_date',
        'description',
        'action_taken',
        'notes',
        'status',
    ];

    protected $casts = [
        'warning_date' => 'date',
        'given_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function givenBy()
    {
        return $this->belongsTo(User::class, 'given_by_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
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
            'active' => '<span class="badge bg-label-warning">Active</span>',
            'resolved' => '<span class="badge bg-label-success">Resolved</span>',
            'dismissed' => '<span class="badge bg-label-info">Dismissed</span>',
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

    public function getWarningTypeLabelAttribute()
    {
        return match($this->warning_type) {
            'verbal' => 'Verbal Warning',
            'written' => 'Written Warning',
            'performance' => 'Performance Issue',
            'attendance' => 'Attendance Issue',
            default => ucfirst($this->warning_type),
        };
    }
}
