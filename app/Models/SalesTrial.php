<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A product trial. Either lodged complete by the salesperson, or pre-booked by a CCare/NewBiz
 * agent and completed later by the salesperson. The company is a sales_pipelines row.
 */
class SalesTrial extends Model
{
    protected $table = 'sales_trials';

    protected $fillable = [
        'tenant_id', 'salesperson_id', 'sales_pipeline_id', 'company_name',
        'booked_by_id', 'booked_by_role', 'status',
        'trial_date', 'trial_time', 'topic', 'product', 'description', 'requirement',
        'contact_person', 'contact_number', 'result', 'remarks',
        'latitude', 'longitude', 'location_accuracy', 'location_address', 'completed_at',
    ];

    protected $casts = [
        'trial_date'        => 'date',
        'completed_at'      => 'datetime',
        'latitude'          => 'float',
        'longitude'         => 'float',
        'location_accuracy' => 'float',
    ];

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(SalesPipeline::class, 'sales_pipeline_id');
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(SalesTrialImage::class);
    }

    public function scopeForSalesperson($q, int $userId)
    {
        return $q->where('salesperson_id', $userId);
    }

    public function scopeCompleted($q)
    {
        return $q->where('status', 'completed');
    }

    public function scopeBooked($q)
    {
        return $q->where('status', 'booked');
    }

    public function isBooked(): bool
    {
        return $this->status === 'booked';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'booked'    => 'bg-label-warning',
            'completed' => 'bg-label-success',
            'cancelled' => 'bg-label-danger',
            default     => 'bg-label-secondary',
        };
    }

    /** "Booked by CCare" / "Booked by New Biz" / "Self-lodged". */
    public function sourceLabel(): string
    {
        if (!$this->booked_by_id || $this->booked_by_role === 'self') {
            return 'Self-lodged';
        }

        return match ($this->booked_by_role) {
            'ccare'  => 'Booked by CCare',
            'newbiz' => 'Booked by New Biz',
            'admin'  => 'Booked (admin)',
            default  => 'Pre-booked',
        };
    }
}
