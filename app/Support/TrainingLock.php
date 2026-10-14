<?php

namespace App\Support;

use App\Enums\UserAccountStatus;
use App\Models\TrainingModule;
use App\Models\UserTrainingProgress;

/**
 * Is this person's account held until they pass their gating training, and
 * how far along are they? Drives the "complete your training to unlock your
 * account" banner. The account itself is released by
 * TrainingController::releaseAccountIfTrainingDone(), on the same modules
 * (config('training.onboarding_phase_ids')).
 */
class TrainingLock
{
    /** ['passed' => n, 'total' => m] while training holds the account, else null. */
    public static function progressFor($user): ?array
    {
        if (!$user) {
            return null;
        }
        $status = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;
        if ($status !== UserAccountStatus::ONBOARDING_SUBMITTED->value || !$user->is_training_required) {
            return null;
        }

        $moduleIds = TrainingModule::whereIn('phase_id', config('training.onboarding_phase_ids', [1, 2]))->pluck('id');
        if ($moduleIds->isEmpty()) {
            return null;
        }
        $passed = UserTrainingProgress::where('user_id', $user->id)->whereIn('module_id', $moduleIds)
            ->where('status', 'completed')->distinct('module_id')->count('module_id');
        $total = $moduleIds->count();

        // Real count only, never training_status: that column is set to 'completed'
        // as a side effect elsewhere (is_training_required toggled off, or HR's
        // approveTraining() override) regardless of how many modules were actually
        // passed, which used to hide this very banner from an account that was
        // still genuinely held.
        if ($passed >= $total) {
            return null;
        }

        return ['passed' => $passed, 'total' => $total];
    }
}
