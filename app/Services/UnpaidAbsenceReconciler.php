<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;

/**
 * A leave marked unpaid (LWP) for lack of balance at approval time can
 * become payable later the same month - most commonly because the employee
 * worked a Sunday, a holiday, or their own designated off-Saturday and
 * earned a comp-off day (see Attendance.php's own crediting hook, and
 * LeavePolicyService::saturdayOffState()). Nothing previously revisited an
 * old LWP decision once new balance arrived, so the absence stayed unpaid
 * forever even once the employee genuinely had the days to cover it.
 * Reported live: an employee's Monday leave was marked Absent for lack of
 * balance, then they worked the following Sunday and earned exactly the
 * day that would have covered it - the Monday never updated.
 *
 * Rules, mirroring LeaveAttendanceReconciler's own safety rules:
 *   - Same calendar month only. A leave's from_date and the balance that
 *     now covers it must fall in the same month - a later month's credit
 *     does not reach back into an earlier month's absence.
 *   - Never before LEDGER_OPENS (2026-07-31). Balances before that date are
 *     already inside the Accounts department's manually verified opening
 *     figure; reopening an old LWP decision there risks paying for a day
 *     that verified figure may already assume was unpaid. See the same
 *     lesson learned reverting 44 pre-cutoff half-day corrections earlier.
 *   - Oldest absence first, per employee. A credited day only ever covers
 *     what it is actually worth - a leave is converted in full or not at
 *     all, never partially.
 *   - Only leave types that pool into All Purpose Leave (mirrors
 *     LeaveRequest::processApproval()'s own pooling rule) - Short Leave,
 *     Maternity/Paternity and WFH are excluded, matching every other
 *     reconciliation this session.
 */
class UnpaidAbsenceReconciler
{
    public const LEDGER_OPENS = '2026-07-31';

    private const EXCLUDED_POOL_CODES = ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL', 'WFH'];

    /**
     * @return array{scanned:int,converted:int,daysPaid:float,rows:array}
     */
    public static function run(bool $dry = true, ?int $userId = null, ?int $month = null, ?int $year = null): array
    {
        $month = $month ?? (int) now()->month;
        $year  = $year ?? (int) now()->year;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd   = $monthStart->copy()->endOfMonth();
        $ledgerOpens = Carbon::parse(self::LEDGER_OPENS);

        $alp = LeaveType::where('code', 'ALP')->first();
        if (!$alp) {
            return ['scanned' => 0, 'converted' => 0, 'daysPaid' => 0.0, 'rows' => [['error' => 'All Purpose Leave type not found']]];
        }

        // A month that ends before the cutoff has nothing safe to reconcile at all.
        if ($monthEnd->lt($ledgerOpens)) {
            return ['scanned' => 0, 'converted' => 0, 'daysPaid' => 0.0, 'rows' => []];
        }
        $rangeStart = $monthStart->greaterThan($ledgerOpens) ? $monthStart : $ledgerOpens->copy()->addDay();

        $requests = LeaveRequest::with(['leaveType', 'user'])
            ->where('status', 'approved')
            ->where('is_lwp', true)
            ->whereDate('from_date', '>=', $rangeStart)
            ->whereDate('from_date', '<=', $monthEnd)
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->orderBy('from_date')
            ->get();

        $scanned = $requests->count();
        $converted = 0;
        $daysPaid = 0.0;
        $rows = [];

        $groups = [];
        foreach ($requests as $leave) {
            $type = $leave->leaveType;
            if (!$type || !$type->is_paid || in_array($type->code, self::EXCLUDED_POOL_CODES, true)) {
                continue; // not pooled into ALP, or not a paid type - nothing to reconcile here
            }
            $groups[$leave->user_id][] = $leave;
        }

        foreach ($groups as $groupUserId => $leaves) {
            usort($leaves, fn($a, $b) => $a->from_date <=> $b->from_date);

            $balance = LeaveBalance::where('user_id', $groupUserId)->where('leave_type_id', $alp->id)->first();
            if (!$balance) continue;

            // Eligibility is decided against the balance AS OF THE END OF THE
            // TARGET MONTH, not today's live balance - the live balance can
            // already include a later month's own accrual (e.g. running this
            // for August in September, after September 1st's accrual has
            // posted), which would silently let a later month's credit reach
            // back into an earlier month's absence - exactly the cross-month
            // reconciliation this service explicitly promises never to do.
            // Reported live: an August absence's "covering" credit turned out
            // to be dated September 1st.
            [$balAsOfMonthEnd, $usedAsOfMonthEnd] = self::balanceAsOfMonthEnd($balance, $monthEnd);

            // Tracked locally so a dry run simulates the same running balance
            // an --apply run would actually produce - $balance->used is only
            // ever persisted below when !$dry, so relying on it directly here
            // left every leave in a multi-leave month re-reading the SAME
            // starting balance, showing all of them as payable even when only
            // the first genuinely was.
            $simulatedUsed = $usedAsOfMonthEnd;

            foreach ($leaves as $leave) {
                $available = $balAsOfMonthEnd - $simulatedUsed;
                $unpaidDays = (float) $leave->lwp_days;
                if ($unpaidDays <= 0 || $available + 0.001 < $unpaidDays) continue;
                $simulatedUsed += $unpaidDays;

                $rows[] = [
                    'user'  => optional($leave->user)->getFullName(),
                    'leave' => $leave->id,
                    'type'  => $leave->leaveType->name,
                    'date'  => $leave->from_date->format('Y-m-d'),
                    'days'  => $unpaidDays,
                ];
                $converted++;
                $daysPaid += $unpaidDays;

                if (!$dry) {
                    DB::transaction(function () use ($leave, $balance, $unpaidDays) {
                        $leave->update(['is_lwp' => false, 'lwp_days' => 0]);

                        LeaveBalance::$auditReason = "Retroactively marked paid: sufficient All Purpose Leave balance became available within "
                            . Carbon::parse($leave->from_date)->format('F Y')
                            . " to cover leave #{$leave->id} ({$leave->from_date->format('d M Y')}), previously unpaid for lack of balance at approval time.";
                        $balance->used = (float) $balance->used + $unpaidDays;
                        $balance->save();
                        $balance->refresh();
                    });
                }
            }
        }

        return compact('scanned', 'converted', 'daysPaid', 'rows');
    }

    /**
     * Replay this balance's own audit trail to reconstruct what `balance`
     * and `used` actually were at the end of the target month - not what
     * they are right now, which may already include credits/debits from
     * later months. Falls back to 0/0 for a balance with no audit history
     * before that point (a genuinely fresh balance created after the target
     * month has nothing to reconstruct).
     *
     * @return array{0:float,1:float} [balance, used] as of $monthEnd
     */
    private static function balanceAsOfMonthEnd(LeaveBalance $balance, Carbon $monthEnd): array
    {
        $bal = 0.0;
        $used = 0.0;

        $audits = Audit::where('auditable_type', LeaveBalance::class)
            ->where('auditable_id', $balance->id)
            ->where('created_at', '<=', $monthEnd)
            ->orderBy('created_at')
            ->get();

        foreach ($audits as $audit) {
            $newValues = $audit->new_values ?? [];
            if (array_key_exists('balance', $newValues)) {
                $bal = (float) $newValues['balance'];
            }
            if (array_key_exists('used', $newValues)) {
                $used = (float) $newValues['used'];
            }
        }

        return [$bal, $used];
    }
}
