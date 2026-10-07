<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A day cannot be both worked and paid leave.
 *
 * When someone turns up on a day their approved PAID leave already covers, the
 * company pays twice: once as salary for the attendance, once as a leave day
 * off the balance. This returns that day to the All Purpose Leave pool.
 *
 * Rules, as specified by HR:
 *   - Per DAY, not per request. A 5-day leave with one day attended returns
 *     one day; the other four stand.
 *   - Any attendance counts — half day or full day.
 *   - Only PAID leave is reversed. An unpaid day cost the employee nothing to
 *     begin with, so the day simply shows as present and nothing is credited.
 *   - When every covered day turns out to be attended, the request itself is
 *     cancelled rather than left approved with nothing behind it.
 *
 * Idempotent: each reversal stamps the attendance row, and a stamped row is
 * never counted again, so the command is safe to re-run or schedule daily.
 */
class LeaveAttendanceReconciler
{
    /** Written to attendance.admin_reason; also the guard against double credit. */
    public const MARK = 'Present - this day attendance log found';

    /**
     * Only leave from this date forward is reconciled.
     *
     * Balances were reset on 2026-08-01 and re-opened with the Accounts
     * department's verified figure as at 31-Jul-2026. Any June or July clash is
     * already inside that figure, so crediting it again would add days on top
     * of a baseline the accounts team signed off. This rule governs new leave
     * only.
     */
    public const APPLIES_FROM = '2026-08-01';

    /** Total refunded to a request can never exceed the days it actually paid for. */
    private static function paidDaysOf(LeaveRequest $leave): float
    {
        $total = $leave->is_half_day
            ? 0.5
            : (Carbon::parse($leave->from_date)->diffInDays(Carbon::parse($leave->to_date)) + 1);

        return max(0.0, $total - (float) ($leave->lwp_days ?? 0));
    }

    /**
     * @return array{scanned:int,conflicts:int,credited:float,cancelled:int,rows:array}
     */
    public static function run(bool $dry = true, ?int $userId = null): array
    {
        $alp = LeaveType::where('code', 'ALP')->first();
        if (!$alp) {
            return ['scanned' => 0, 'conflicts' => 0, 'credited' => 0.0, 'cancelled' => 0,
                    'rows' => [['error' => 'All Purpose Leave type not found']]];
        }

        $requests = LeaveRequest::with(['leaveType', 'user'])
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereDate('from_date', '>=', self::APPLIES_FROM)
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->get();

        $scanned = 0; $conflicts = 0; $credited = 0.0; $cancelled = 0; $rows = [];

        foreach ($requests as $leave) {
            $scanned++;
            $type = $leave->leaveType;
            if (!$type) {
                continue;
            }

            // Maternity and paternity keep their own pool and are never
            // auto-reversed.
            if (in_array($type->code, ['ML', 'MAT', 'PL_PAT', 'PAT'], true)) {
                continue;
            }

            // Short leave and half day are PART-worked days by definition, so
            // the mere existence of attendance proves nothing — it is expected.
            // They are only given back when the employee turned out to work a
            // FULL day, which means the partial leave was not taken after all.
            // A full-day leave is reverted on any attendance, half or full.
            $needsFullDay = (bool) ($leave->is_short_leave || $leave->is_half_day)
                || in_array($type->code, ['SHL', 'HDL'], true);

            // Unpaid leave never took anything off a balance, so there is
            // nothing to give back — the day just reads as present.
            $isPaid = (bool) $type->is_paid && !$leave->is_lwp;

            // WFH is paid but, like unpaid leave, never took anything off a
            // balance either — it's paid directly via PayrollService, not by
            // deducting ALP. Crediting ALP here would invent days that were
            // never spent.
            $isWfh = strtoupper($type->code) === 'WFH';

            // Mirror LeaveRequest::processApproval()'s own pooling rule: most
            // paid types deduct from the shared ALP pool, but Short Leave (and
            // Maternity/Paternity, already skipped above) keep their own
            // separate balance. This used to always refund ALP regardless of
            // where the day actually came from, so a Short Leave conflict
            // cancelled the request but left the Short Leave balance drained
            // while crediting a day the employee never lost from ALP in the
            // first place.
            $excludedPoolCodes = ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL', 'WFH'];
            $poolTypeId = ($alp && !in_array($type->code, $excludedPoolCodes, true))
                ? $alp->id
                : $leave->leave_type_id;

            $perDay   = $leave->is_half_day ? 0.5 : 1.0;
            $refundCap = self::paidDaysOf($leave);
            $refunded  = 0.0;

            $covered = 0; $attended = 0;
            $cursor = Carbon::parse($leave->from_date);
            $end    = Carbon::parse($leave->to_date);

            while ($cursor->lte($end)) {
                $covered++;
                $date = $cursor->toDateString();

                // Approving a leave CREATES attendance rows for the covered days,
                // so "a row exists" proves nothing. Only a row that is not the
                // leave's own, and that records actual presence, means the
                // employee turned up. Without this the reconciler treats every
                // approved leave as fully attended.
                $att = Attendance::where('user_id', $leave->user_id)
                    ->whereDate('check_in_time', $date)
                    ->whereNotNull('check_in_time')
                    ->whereNotNull('check_out_time')
                    ->where(function ($q) use ($leave) {
                        $q->whereNull('leave_request_id')
                          ->orWhere('leave_request_id', '!=', $leave->id);
                    })
                    ->whereNotIn(DB::raw('LOWER(status)'), [
                        'leave', 'paid_leave', 'unpaid_leave', 'on_leave', 'absent', 'holiday', 'weekly_off',
                    ])
                    ->first();

                // A short/half-day leave only counts as clashing when the day
                // was worked in FULL. 465 minutes is the full-day threshold
                // payroll already uses.
                if ($att && $needsFullDay) {
                    $mins = ($att->check_in_time && $att->check_out_time)
                        ? \App\Services\AttendanceDayStatus::workedMinutes(Carbon::parse($att->check_in_time), Carbon::parse($att->check_out_time))
                        : 0;
                    if ($mins < 465) {
                        $att = null;   // part-day attendance is exactly what this leave expects
                    }
                }

                if ($att) {
                    $attended++;
                    $already = $att->admin_reason && str_contains($att->admin_reason, self::MARK);

                    if (!$already && $isPaid && !$isWfh && ($refunded + $perDay) <= $refundCap + 0.001) {
                        $conflicts++;
                        $refunded += $perDay;

                        $rows[] = [
                            'user'    => optional($leave->user)->getFullName(),
                            'leave'   => $leave->id,
                            'type'    => $type->name,
                            'date'    => $date,
                            'days'    => $perDay,
                        ];

                        if (!$dry) {
                            self::credit($leave, $poolTypeId, $perDay, $date);
                            $att->admin_reason = trim(($att->admin_reason ? $att->admin_reason . ' | ' : '')
                                . self::MARK);
                            $att->save();
                        }
                    } elseif (!$already && !$isPaid) {
                        // Nothing to credit; record it so the day is explained.
                        $rows[] = [
                            'user'  => optional($leave->user)->getFullName(),
                            'leave' => $leave->id,
                            'type'  => $type->name . ' (unpaid - no credit)',
                            'date'  => $date,
                            'days'  => 0,
                        ];
                    } elseif (!$already && $isWfh) {
                        // Still counts toward $attended below (so the request
                        // still auto-cancels once fully attended) — just
                        // nothing to hand back to a pool that was never
                        // touched. Stamped so it isn't re-flagged tomorrow.
                        $rows[] = [
                            'user'  => optional($leave->user)->getFullName(),
                            'leave' => $leave->id,
                            'type'  => $type->name . ' (WFH - no pool to credit)',
                            'date'  => $date,
                            'days'  => 0,
                        ];
                        if (!$dry) {
                            $att->admin_reason = trim(($att->admin_reason ? $att->admin_reason . ' | ' : '')
                                . self::MARK);
                            $att->save();
                        }
                    }
                }

                $cursor->addDay();
            }

            $credited += $refunded;

            // Every covered day was worked — the request has nothing left.
            // Unpaid leave is never cancelled: it cost nothing, so the day just
            // reads as present and the record stands.
            if ($isPaid && $covered > 0 && $attended === $covered) {
                $cancelled++;
                if (!$dry) {
                    $leave->status        = 'cancelled';
                    $leave->cancel_reason = self::MARK;
                    $leave->cancelled_at  = now();
                    $leave->saveQuietly();   // balance already settled above via
                                              // self::credit(); saveQuietly() skips
                                              // LeaveRequest::booted()'s own reversal
                                              // so it cannot refund the same days twice.

                    // But that reversal is also the ONLY place that deletes the
                    // placeholder attendance row the approval auto-created (WFH,
                    // half-day, or full-day leave alike) — skipped here for the
                    // same reason, its absence left that row behind next to the
                    // real, actually-worked punch. Two rows for one date then fed
                    // PayrollService's calculateAttendanceStats() twice — 0.5 (the
                    // stale leave placeholder) plus 1.0 (the real punch) instead of
                    // the correct 1.0, silently overpaying every day this reconciler
                    // reverted. Delete it explicitly instead of via the event hook.
                    Attendance::where('user_id', $leave->user_id)
                        ->whereBetween('check_in_time', [
                            $leave->from_date->copy()->startOfDay(),
                            $leave->to_date->copy()->endOfDay(),
                        ])
                        ->where(function ($q) use ($leave) {
                            $q->whereIn('status', ['leave', 'paid_leave', 'unpaid_leave', 'on_leave', 'half-Day', 'work_from_home'])
                              ->orWhere('leave_request_id', $leave->id);
                        })
                        ->delete();
                }
            }
        }

        return compact('scanned', 'conflicts', 'credited', 'cancelled', 'rows');
    }

    /** Return one day to whichever balance the leave actually drew from, with a ledger line. */
    private static function credit(LeaveRequest $leave, int $poolTypeId, float $days, string $date): void
    {
        DB::transaction(function () use ($leave, $poolTypeId, $days, $date) {
            $balance = LeaveBalance::firstOrCreate(
                ['user_id' => $leave->user_id, 'leave_type_id' => $poolTypeId],
                ['tenant_id' => $leave->tenant_id, 'balance' => 0, 'used' => 0, 'accrued_this_year' => 0]
            );

            // Give the day back by reducing what was consumed, never by inflating
            // the entitlement — used can never go below zero.
            $before = (float) $balance->used;
            $balance->used = max(0, $before - $days);

            LeaveBalance::$auditReason = self::MARK . ' (' . Carbon::parse($date)->format('d M Y') . ')';
            $balance->save();

            Log::info("Leave day returned to ALP: user {$leave->user_id}, leave {$leave->id}, {$date}, {$days}d");
        });
    }
}
