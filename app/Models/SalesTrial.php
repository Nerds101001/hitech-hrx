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
        'booked_by_id', 'booked_by_role', 'status', 'current_status', 'next_expected_date',
        'trial_date', 'trial_time', 'topic', 'product', 'description', 'requirement',
        'contact_person', 'contact_number', 'result', 'remarks',
        'latitude', 'longitude', 'location_accuracy', 'location_address', 'completed_at',
    ];

    protected $casts = [
        'trial_date'         => 'date',
        'next_expected_date' => 'date',
        'completed_at'       => 'datetime',
        'latitude'           => 'float',
        'longitude'          => 'float',
        'location_accuracy'  => 'float',
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

    /** The outcome timeline — newest first. */
    public function statusUpdates(): HasMany
    {
        return $this->hasMany(SalesTrialStatusUpdate::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /** config/trial_status.php metadata for this trial's current outcome status. */
    public function statusMeta(): array
    {
        $key = $this->current_status ?: config('trial_status.default', 'pending');

        return config('trial_status.statuses.' . $key, [
            'label' => ucfirst((string) $key), 'color' => '#64748b', 'terminal' => false, 'needs_date' => false,
        ]);
    }

    public function statusLabel(): string
    {
        return $this->statusMeta()['label'];
    }

    public function statusColor(): string
    {
        return $this->statusMeta()['color'];
    }

    /** True when the trial is done in the field but its outcome is still open (and maybe overdue). */
    public function isFollowUpOpen(): bool
    {
        return $this->status === 'completed' && empty($this->statusMeta()['terminal']);
    }

    public function isFollowUpOverdue(): bool
    {
        return $this->isFollowUpOpen()
            && $this->next_expected_date
            && $this->next_expected_date->isPast();
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
