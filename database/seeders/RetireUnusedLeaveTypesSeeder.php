<?php

namespace Database\Seeders;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Retires the legacy leave types that ALP replaced, merging any residual
 * balance into each employee's ALP pool first so nobody loses a day.
 *
 * Why: the inactive 'PL' (Paid Leave) type still held 43 days across 26
 * employees and was being picked up as the comp-off target by
 * Attendance::booted (lowest id won an unordered orWhere chain), so
 * Sunday/holiday credits were banked into a pool employees could not spend.
 *
 * KEPT deliberately: ML (maternity), PL_PAT (paternity), WFH — real leave
 * kinds with their own rules, not superseded by ALP; plus the active
 * SHL / ALP / HDL.
 *
 * Historical leave_requests keep pointing at the retired types: the types are
 * SOFT-deleted and LeaveController already loads leaveType withTrashed(), so
 * past records still display their original name.
 *
 * Idempotent — re-running finds nothing left to move.
 */
class RetireUnusedLeaveTypesSeeder extends Seeder
{
    /** Codes to retire (superseded by All Purpose Leave) */
    private const RETIRE = ['CL', 'SL', 'PL', 'UL', 'Comp off'];

    /** Never retire these */
    private const KEEP = ['ML', 'PL_PAT', 'WFH', 'ALP', 'SHL', 'HDL'];

    public function run(): void
    {
        $alp = LeaveType::where('code', 'ALP')->first();
        if (!$alp) {
            $this->command->error('ALP (All Purpose Leave) not found — aborting, nothing changed.');
            return;
        }

        $targets = LeaveType::whereIn('code', self::RETIRE)
            ->whereNotIn('code', self::KEEP)
            ->get();

        if ($targets->isEmpty()) {
            $this->command->info('Nothing to retire — already done.');
            return;
        }

        $movedDays = 0.0; $movedUsed = 0.0; $rowsMerged = 0; $usersTouched = [];

        DB::transaction(function () use ($targets, $alp, &$movedDays, &$movedUsed, &$rowsMerged, &$usersTouched) {
            foreach ($targets as $type) {
                $balances = LeaveBalance::where('leave_type_id', $type->id)->get();

                foreach ($balances as $bal) {
                    $balAmt  = (float) $bal->balance;
                    $usedAmt = (float) $bal->used;

                    // Always merge (even zero rows) so the stale record goes away
                    $target = LeaveBalance::firstOrCreate(
                        ['user_id' => $bal->user_id, 'leave_type_id' => $alp->id],
                        [
                            'tenant_id' => $bal->tenant_id,
                            'balance' => 0, 'used' => 0,
                            'accrued_this_year' => 0, 'carry_forward_last_year' => 0,
                        ]
                    );

                    if ($balAmt != 0.0 || $usedAmt != 0.0) {
                        LeaveBalance::$auditReason =
                            "Merged {$type->code} balance into ALP (legacy type retired)";
                        $target->increment('balance', $balAmt);
                        $target->increment('used', $usedAmt);
                        $target->increment('accrued_this_year', $balAmt);

                        $movedDays += $balAmt;
                        $movedUsed += $usedAmt;
                        $usersTouched[$bal->user_id] = true;
                    }

                    $bal->delete();
                    $rowsMerged++;
                }

                // Drop policy-profile rules pointing at a retired type so the
                // balance auditor stops re-creating its balance rows.
                $ruleCount = DB::table('leave_policy_profile_rules')
                    ->where('leave_type_id', $type->id)->count();
                if ($ruleCount) {
                    DB::table('leave_policy_profile_rules')->where('leave_type_id', $type->id)->delete();
                    $this->command->warn("  removed {$ruleCount} policy rule(s) for {$type->code}");
                }

                $type->status = 'inactive';
                $type->save();
                $type->delete(); // soft delete — history still resolves withTrashed()

                $this->command->info("Retired {$type->code} ({$type->name})");
            }
        });

        $this->command->info("\nBalance rows merged     : {$rowsMerged}");
        $this->command->info('Days moved into ALP     : ' . number_format($movedDays, 1));
        $this->command->info('Used-days moved into ALP: ' . number_format($movedUsed, 1));
        $this->command->info('Employees affected      : ' . count($usersTouched));

        $remaining = LeaveType::whereNull('deleted_at')->orderBy('id')->get(['code', 'name', 'status']);
        $this->command->info("\nRemaining leave types:");
        foreach ($remaining as $r) {
            $status = $r->status instanceof \App\Enums\Status ? $r->status->value : (string) $r->status;
            $this->command->line("  {$r->code} — {$r->name} ({$status})");
        }
    }
}
