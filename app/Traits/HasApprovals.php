<?php

namespace App\Traits;

use App\Models\RequestApproval;
use Illuminate\Support\Facades\Auth;

trait HasApprovals
{
    /**
     * Boot the trait to hook into model events.
     */
    public static function bootHasApprovals()
    {
        static::updated(function ($model) {
            if ($model->wasChanged('status')) {
                // Try to infer remarks or notes based on common column names
                $remarks = $model->approval_notes ?? $model->cancel_reason ?? $model->reject_reason ?? null;

                $model->approvals()->create([
                    'status'    => is_object($model->status) ? $model->status->value : $model->status,
                    'user_id'   => Auth::id() ?? ($model->approved_by_id ?? $model->updated_by_id),
                    'remarks'   => $remarks,
                    'tenant_id' => $model->tenant_id,
                ]);
            }
        });
    }

    /**
     * Polymorphic relationship for approvals.
     */
    public function approvals()
    {
        return $this->morphMany(RequestApproval::class, 'approvable')->orderBy('created_at', 'desc');
    }

    /**
     * Get the latest approval record.
     */
    public function latestApproval()
    {
        return $this->morphOne(RequestApproval::class, 'approvable')->latestOfMany();
    }
}
