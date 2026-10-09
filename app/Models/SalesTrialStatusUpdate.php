<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step on a trial's outcome timeline — a status (from config/trial_status.php),
 * a comment, and for Waiting/Retrial the next expected date.
 */
class SalesTrialStatusUpdate extends Model
{
    protected $table = 'sales_trial_status_updates';

    protected $fillable = [
        'tenant_id', 'sales_trial_id', 'status', 'comment', 'expected_date', 'logged_by_id',
    ];

    protected $casts = [
        'expected_date' => 'date',
    ];

    public function trial(): BelongsTo
    {
        return $this->belongsTo(SalesTrial::class, 'sales_trial_id');
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_id');
    }

    /** The human label / colour / flags for this update's status, from config. */
    public function meta(): array
    {
        return config('trial_status.statuses.' . $this->status, [
            'label' => ucfirst((string) $this->status), 'color' => '#64748b', 'terminal' => false, 'needs_date' => false,
        ]);
    }

    public function label(): string
    {
        return $this->meta()['label'];
    }

    public function color(): string
    {
        return $this->meta()['color'];
    }
}
