<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use OwenIt\Auditing\Models\Audit;
use Carbon\Carbon;

class LeaveHistoryService
{
    /**
     * The ledger opens here. Balances were reset on 2026-08-01 and re-opened
     * with the Accounts department's verified figure as at 31-Jul-2026, so any
     * leave taken before that is ALREADY reflected in that opening number —
     * listing it again showed the same days twice and made the log read as a
     * running history rather than a balance ledger.
     *
     * Older leave is not deleted; it stays on the Leave Requests page and on
     * attendance. This only decides what the balance ledger displays.
     */
    public const LEDGER_OPENS = '2026-07-31';

    /**
     * Types tracked outside the main ALP/PL pool the "Total Allocated" /
     * "Available Balance" cards summarize — Maternity, Paternity, Short
     * Leave (own card, hours-based) and WFH (never pooled, always paid
     * directly). Mirrors LeaveRequest::processApproval()'s own pooling
     * exclusion list. Mixing these into the same flat ledger made a
     * Short Leave "Earned for August" credit look like part of the ALP/PL
     * balance it was never counted in, so the ledger's own credit/debit
     * sum silently diverged from the cards above it.
     *
     * This governs POOL ARITHMETIC ONLY — never whether a request is listed.
     * It was previously named EXCLUDED_FROM_LEDGER and used to `continue`
     * past these requests entirely, which also removed them from the history
     * table this same collection feeds. The result: an approved Short Leave
     * deducted its balance and then appeared nowhere on My Leaves, the admin
     * Leave Statement or My Profile, so employees re-applied for leave they
     * had already been granted. 106 short leaves across 76 people were
     * invisible this way. Keep the rows; zero their pool contribution.
     */
    private const EXCLUDED_FROM_POOL = ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL', 'WFH'];

    public static function getUnifiedHistory($userOrId)
    {
        $history = collect();
        
        if (is_numeric($userOrId)) {
            $user = \App\Models\User::find($userOrId);
        } else {
            $user = $userOrId;
        }

        if (!$user) return $history;

        $userId = $user->id;

        // 1. Fetch Leave Requests via relationship to ensure scopes are handled
        $requests = $user->leaveRequests()->with(['leaveType' => function ($q) {
            $q->withTrashed(); // old requests may reference soft-deleted types
        }])->get();
        foreach ($requests as $leave) {
            // Anything before the ledger opened is already inside the opening
            // balance — see LEDGER_OPENS.
            if ($leave->from_date && Carbon::parse($leave->from_date)->lt(Carbon::parse(self::LEDGER_OPENS))) {
                continue;
            }

            // Listed either way — see EXCLUDED_FROM_POOL. This only decides
            // whether the row moves the pooled figure, not whether it shows.
            $affectsPool = !in_array(strtoupper($leave->leaveType->code ?? ''), self::EXCLUDED_FROM_POOL, true);

            $status = $leave->status instanceof \UnitEnum ? $leave->status->value : $leave->status;

            // Split the request into what came off the paid balance and what
            // was unpaid. A 13-day request against a 4-day balance is 4 paid +
            // 9 LWP — it is NOT a balance of minus nine.
            // Working days, the way approval charges them: a leave across a
            // Sunday or holiday is not charged for it. Calendar days made the
            // statement disagree with Total Used for 10 people (2026-09-30) -
            // CHANCHAL LDH's 12-18 Sep leave showed 6 days more than was taken.
            $days    = $leave->is_half_day
                ? 0.5
                : \App\Services\LeavePolicyService::calculateWorkingDays(
                    $user, $leave->leave_type_id, Carbon::parse($leave->from_date)->toDateString(), Carbon::parse($leave->to_date)->toDateString(), false);
            $lwpDays = (float) ($leave->lwp_days ?? 0);
            $paidDays = max(0, $days - $lwpDays);

            // Only an approved request ever touched the balance.
            $deducted = in_array($status, ['approved'], true) ? $paidDays : 0.0;

            // What this row takes off the ALP/PL pool. A Short Leave or WFH
            // consumed its own separate allowance, never the pool, so it
            // contributes nothing here — but it still gets listed, and
            // paid_days below still reports what was actually taken so the
            // statement can show it.
            $pooledDeduction = $affectsPool ? $deducted : 0.0;

            $history->push((object)[
                'id'         => 'REQ-' . $leave->id,
                'type'       => 'Request',
                'leave_type' => $leave->leaveType->name ?? 'Unknown',
                'from_date'  => $leave->from_date,
                'to_date'    => $leave->to_date,
                'amount'     => -$pooledDeduction,
                'total_days' => $days,
                'paid_days'  => $deducted,
                'lwp_days'   => $lwpDays,
                'affects_pool' => $affectsPool,
                'status'     => $status,
                'notes'      => $leave->user_notes,
                'created_at' => $leave->created_at,
                'is_adjustment' => false,
                'raw_request' => $leave
            ]);
        }

        // 1b. Absences CCare's register covered from the balance itself (it
        // auto-adjusts an absence with no leave behind it). They raise `used`
        // and never `balance`, so the audit step below cannot see them: the
        // statement listed fewer deductions than Total Used - SHRI KANT's
        // showed 2 against 4 (2026-09-30). A day later tied to a leave request
        // is already that request's line.
        $covered = \App\Models\Attendance::where('user_id', $userId)
            ->whereNull('leave_request_id')
            ->where('admin_reason', 'like', '%Auto-adjusted against Earned Leave%')
            ->where('check_in_time', '>=', self::LEDGER_OPENS)
            ->get(['id', 'check_in_time', 'created_at']);
        foreach ($covered as $day) {
            $history->push((object)[
                'id'         => 'ABS-' . $day->id,
                'type'       => 'Deduction',
                'leave_type' => 'All Purpose Leave',
                'from_date'  => null,
                'to_date'    => null,
                'amount'     => -1.0,
                'status'     => 'Processed',
                'notes'      => 'Absence on ' . $day->check_in_time->format('d M Y') . ' covered from balance (marked by Customer Care)',
                'created_at' => $day->created_at,
                'is_adjustment' => true,
            ]);
        }

        // 2. Fetch Balance Audits via relationship
        $leaveBalances = $user->leaveBalances()->with('leaveType')->get()
            ->reject(fn($b) => in_array(strtoupper($b->leaveType->code ?? ''), self::EXCLUDED_FROM_POOL, true));
        $balanceIds = $leaveBalances->pluck('id');

        // SPECIAL CASE: For User 248, we ignore automated audits for balance changes 
        // to prevent duplication with the synthetic '14+1+1' breakdown they requested.
        if ($userId != 248) {
            $adjustments = Audit::where('auditable_type', 'App\Models\LeaveBalance')
                ->whereIn('auditable_id', $balanceIds)
                ->latest()
                ->get();

            foreach ($adjustments as $audit) {
                $old = (float)($audit->old_values['balance'] ?? 0);
                $new = (float)($audit->new_values['balance'] ?? 0);
                $diff = $new - $old;
                
                if (abs($diff) < 0.01) continue;

                $balance = $leaveBalances->where('id', $audit->auditable_id)->first();
                
                $history->push((object)[
                    'id'         => 'ADJ-' . $audit->id,
                    'type'       => $diff > 0 ? 'Credit' : 'Deduction',
                    'leave_type' => $balance->leaveType->name ?? 'System Adjustment',
                    'from_date'  => null,
                    'to_date'    => null,
                    'amount'     => $diff,
                    'status'     => 'Processed',
                    'notes'      => $audit->user_agent ?: "Balance Adjusted by Admin",
                    'created_at' => $audit->created_at,
                    'is_adjustment' => true
                ]);
            }
            $recordedAdjustmentsSum = $adjustments->groupBy('auditable_id');
        } else {
            $adjustments = collect(); // Ignore audits for 248
        }

        // 3. Add Opening Balance Entries for Legacy Data
        foreach ($leaveBalances as $bal) {
            $currentBalance = (float)$bal->balance;

            // Calculate sum of all recorded adjustments for this balance
            $recordedAdjustments = $adjustments->where('auditable_id', $bal->id)->sum(function($a) {
                $old = (float)($a->old_values['balance'] ?? 0);
                $new = (float)($a->new_values['balance'] ?? 0);
                return $new - $old;
            });

            // A balance created on/after LEDGER_OPENS has its entire life inside
            // the tracked period - every credit is a real audit row, and every
            // debit is already its own separate Request-type entry from step 1
            // above. Adding 'used' back in for one of those double-counts the
            // exact same day: once as the correctly-tracked credit, again here
            // as a phantom "opening balance" equal to whatever has since been
            // used. Confirmed live - a Short Leave balance created 2026-08-01
            // with one +1 credit audit and, weeks later, a genuine -1 deduction
            // (its own Request line already in the ledger) manufactured a
            // second, fake +1 "Earned for August 2026" line here on top of the
            // real one. 'used' only belongs in this reconstruction for
            // genuinely legacy balances that predate the tracked window, where
            // it is the only way to recover leave already spent before audits
            // existed to record it.
            $isLegacyBalance = $bal->created_at && $bal->created_at->lt(Carbon::parse(self::LEDGER_OPENS));
            $openingBalance = $isLegacyBalance
                ? ($currentBalance + (float)$bal->used) - $recordedAdjustments
                : $currentBalance - $recordedAdjustments;

            if (abs($openingBalance) > 0.001) {
                $cfAmount = (float)($bal->carry_forward_last_year ?? 0);
                $accruedAmount = $openingBalance - $cfAmount;

                // 1. Carry Forward Entry (if part of opening balance)
                if ($cfAmount > 0) {
                    $cfDate = $bal->created_at ? $bal->created_at->copy()->startOfYear() : Carbon::create(now()->year, 4, 1);
                    $prevMonth = $cfDate->copy()->subMonth()->format('F Y');
                    $history->push((object)[
                        'id'         => 'SYS-CF-' . $bal->id,
                        'type'       => 'Carry Forward',
                        'leave_type' => $bal->leaveType->name ?? 'Unknown',
                        'from_date'  => null,
                        'to_date'    => null,
                        'amount'     => $cfAmount,
                        'status'     => 'Processed',
                        'notes'      => "Carry Forward of {$prevMonth} earned leave",
                        'created_at' => $cfDate,
                        'is_adjustment' => true
                    ]);
                }

                // 2. Initial Allotment / Accrued Entry
                if (abs($accruedAmount) > 0.001) {
                    // Try to derive a month label from the balance creation date
                    $createdAt = $bal->created_at ?? Carbon::create(now()->year, 4, 1);
                    $monthLabel = $createdAt->format('F Y');
                    // Labelled as an actual opening balance, not "Earned" -
                    // this is a reconstructed starting figure, not a real
                    // accrual event, and showing it as "Earned for {month}"
                    // is exactly what made it indistinguishable from a real
                    // monthly credit for the same month.
                    $earnedNote = $isLegacyBalance
                        ? "Opening Balance as of {$monthLabel}"
                        : "Earned for {$monthLabel}";

                    $history->push((object)[
                        'id'         => 'SYS-OPEN-' . $bal->id,
                        'type'       => 'Credit',
                        'leave_type' => $bal->leaveType->name ?? 'Unknown',
                        'from_date'  => null,
                        'to_date'    => null,
                        'amount'     => $accruedAmount,
                        'status'     => 'Processed',
                        'notes'      => $earnedNote,
                        'created_at' => $createdAt->copy()->startOfDay(),
                        'is_adjustment' => true
                    ]);
                }
            }
        }

        return $history->sortByDesc('created_at')->values();
    }
}
