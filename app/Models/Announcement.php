<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by_id',
        'title',
        'content',
        'type',
        'priority',
        'start_date',
        'end_date',
        'is_active',
        'requires_acknowledgment',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'requires_acknowledgment' => 'boolean',
    ];

    // Relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_active', true)
                   ->where(function($q) {
                       $q->whereNull('end_date')
                         ->orWhere('end_date', '>=', now());
                   })
                   ->where('start_date', '<=', now());
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Accessors
    public function getTypeBadgeAttribute()
    {
        return match($this->type) {
            'general' => '<span class="badge bg-label-primary">General</span>',
            'urgent' => '<span class="badge bg-label-danger">Urgent</span>',
            'policy' => '<span class="badge bg-label-info">Policy</span>',
            'holiday' => '<span class="badge bg-label-success">Holiday</span>',
            'event' => '<span class="badge bg-label-warning">Event</span>',
            default => '<span class="badge bg-label-secondary">' . ucfirst($this->type) . '</span>',
        };
    }

    public function getPriorityBadgeAttribute()
    {
        return match($this->priority) {
            'low' => '<span class="badge bg-label-info">Low</span>',
            'medium' => '<span class="badge bg-label-warning">Medium</span>',
            'high' => '<span class="badge bg-label-danger">High</span>',
            'critical' => '<span class="badge bg-label-danger">Critical</span>',
            default => '<span class="badge bg-label-secondary">' . ucfirst($this->priority) . '</span>',
        };
    }

    public function getIsActiveLabelAttribute()
    {
        return $this->is_active ? 
            '<span class="badge bg-label-success">Active</span>' : 
            '<span class="badge bg-label-secondary">Inactive</span>';
    }

    public function getRequiresAcknowledgmentLabelAttribute()
    {
        return $this->requires_acknowledgment ? 
            '<span class="badge bg-label-warning">Required</span>' : 
            '<span class="badge bg-label-info">Optional</span>';
    }
}
