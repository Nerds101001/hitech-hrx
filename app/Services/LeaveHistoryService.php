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

            $status = $leave->status instanceof \UnitEnum ? $leave->status->value : $leave->status;

            // Split the request into what came off the paid balance and what
            // was unpaid. A 13-day request against a 4-day balance is 4 paid +
            // 9 LWP — it is NOT a balance of minus nine.
            $days    = $leave->is_half_day
                ? 0.5
                : (Carbon::parse($leave->from_date)->diffInDays(Carbon::parse($leave->to_date)) + 1);
            $lwpDays = (float) ($leave->lwp_days ?? 0);
            $paidDays = max(0, $days - $lwpDays);

            // Only an approved request ever touched the balance.
            $deducted = in_array($status, ['approved'], true) ? $paidDays : 0.0;

            $history->push((object)[
                'id'         => 'REQ-' . $leave->id,
                'type'       => 'Request',
                'leave_type' => $leave->leaveType->name ?? 'Unknown',
                'from_date'  => $leave->from_date,
                'to_date'    => $leave->to_date,
                'amount'     => -$deducted,
                'total_days' => $days,
                'paid_days'  => $deducted,
                'lwp_days'   => $lwpDays,
                'status'     => $status,
                'notes'      => $leave->user_notes,
                'created_at' => $leave->created_at,
                'is_adjustment' => false,
                'raw_request' => $leave
            ]);
        }

        // 2. Fetch Balance Audits via relationship
        $leaveBalances = $user->leaveBalances()->with('leaveType')->get();
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

            // Opening Balance = (Current + Used) - Sum(Recorded Adjustments)
            // We must add 'used' because approved leave requests deduct from the balance without generating credit audits.
            $openingBalance = ($currentBalance + (float)$bal->used) - $recordedAdjustments;

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
                    $earnedNote = "Earned for {$monthLabel}";

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
