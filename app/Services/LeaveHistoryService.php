<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use OwenIt\Auditing\Models\Audit;
use Carbon\Carbon;

class LeaveHistoryService
{
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
        $requests = $user->leaveRequests()->with('leaveType')->get();
        foreach ($requests as $leave) {
            $history->push((object)[
                'id'         => 'REQ-' . $leave->id,
                'type'       => 'Request',
                'leave_type' => $leave->leaveType->name ?? 'Unknown',
                'from_date'  => $leave->from_date,
                'to_date'    => $leave->to_date,
                'amount'     => 0, // Handled in view
                'status'     => $leave->status instanceof \UnitEnum ? $leave->status->value : $leave->status,
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
