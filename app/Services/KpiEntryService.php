<?php

namespace App\Services;

use App\Models\KpiAssignment;
use App\Models\KpiCheck;
use App\Models\KpiEntry;
use Carbon\Carbon;

/**
 * The weekly submit → HR approve loop for manually measured KPIs.
 *
 * Scoring stays on the monthly KpiCheck. A weekly *check* would award its full
 * marks every week and turn a 150-mark scorecard into roughly 250, so staff log
 * a number per week here instead and the month's achieved value is the sum of
 * whatever HR has approved.
 */
class KpiEntryService
{
    /** Sources that a person fills in themselves. Auto ones are never entered. */
    public static function isManual(KpiAssignment $a): bool
    {
        return !str_starts_with((string) $a->data_source, 'auto:');
    }

    /** Whether HR has to sign the number off before it scores. */
    public static function needsApproval(KpiAssignment $a): bool
    {
        return $a->data_source === 'approval';
    }

    /** Monday–Sunday week containing $day. */
    public static function weekOf(?Carbon $day = null): array
    {
        $day = ($day ?: Carbon::today())->copy()->startOfDay();

        return [$day->copy()->startOfWeek(Carbon::MONDAY), $day->copy()->endOfWeek(Carbon::SUNDAY)];
    }

    /**
     * Record what someone is reporting for a week. Re-submitting the same week
     * overwrites it, so a correction does not create a second row.
     */
    public static function submit(KpiAssignment $a, float $value, ?string $note = null, ?Carbon $day = null): KpiEntry
    {
        [$start, $end] = self::weekOf($day);
        $check = KpiPeriodService::sync($a);   // the monthly check this rolls into

        $entry = KpiEntry::updateOrCreate(
            ['kpi_assignment_id' => $a->id, 'period_start' => $start->toDateString()],
            [
                'tenant_id'    => $a->tenant_id,
                'kpi_check_id' => $check->id,
                'user_id'      => $a->user_id,
                'period_end'   => $end->toDateString(),
                'value'        => $value,
                'note'         => $note,
                // A KPI that needs approval waits for HR; one that does not is
                // taken at face value and counts straight away.
                'status'       => self::needsApproval($a) ? KpiEntry::SUBMITTED : KpiEntry::APPROVED,
                'reviewed_by_id' => null,
                'reviewed_at'    => null,
                'review_note'    => null,
            ]
        );

        self::recalculate($a, $check);

        return $entry;
    }

    /** HR accepts the number: it starts counting toward the month. */
    public static function approve(KpiEntry $entry, int $reviewerId, ?string $note = null): KpiEntry
    {
        $entry->update([
            'status'         => KpiEntry::APPROVED,
            'reviewed_by_id' => $reviewerId,
            'reviewed_at'    => now(),
            'review_note'    => $note,
        ]);

        if ($entry->assignment) {
            self::recalculate($entry->assignment, $entry->check);
        }

        return $entry;
    }

    /** HR sends it back. The employee can correct and submit that week again. */
    public static function reject(KpiEntry $entry, int $reviewerId, ?string $note = null): KpiEntry
    {
        $entry->update([
            'status'         => KpiEntry::REJECTED,
            'reviewed_by_id' => $reviewerId,
            'reviewed_at'    => now(),
            'review_note'    => $note,
        ]);

        if ($entry->assignment) {
            self::recalculate($entry->assignment, $entry->check);
        }

        return $entry;
    }

    /**
     * The month's achieved value is the sum of approved weeks inside it. Runs
     * the normal scoring so the check behaves exactly like an HR-typed figure.
     */
    public static function recalculate(KpiAssignment $a, ?KpiCheck $check = null): ?KpiCheck
    {
        $check = $check ?: KpiPeriodService::sync($a);
        if (!$check) {
            return null;
        }

        $total = (float) KpiEntry::where('kpi_assignment_id', $a->id)
            ->where('status', KpiEntry::APPROVED)
            ->whereDate('period_start', '>=', $check->period_start)
            ->whereDate('period_start', '<=', $check->period_end)
            ->sum('value');

        $check->achieved_value = $total;
        $check->score = KpiPeriodService::targetScore($a, $total);
        // Anything still waiting on HR keeps the check visibly unfinished.
        $pending = KpiEntry::where('kpi_assignment_id', $a->id)
            ->where('status', KpiEntry::SUBMITTED)
            ->whereDate('period_start', '>=', $check->period_start)
            ->whereDate('period_start', '<=', $check->period_end)
            ->exists();
        if ($check->status === 'pending' && $total > 0 && !$pending) {
            $check->status = Carbon::today()->lte(Carbon::parse($check->due_date))
                ? 'completed_on_time' : 'completed_late';
            $check->completed_at = now();
        }
        $check->save();

        return $check;
    }

    /** Everything a reviewer still has to act on, newest week first. */
    public static function queue(?int $departmentId = null)
    {
        return KpiEntry::awaitingReview()
            ->with(['assignment', 'user.department'])
            ->when($departmentId, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('department_id', $departmentId)))
            ->orderByDesc('period_start')
            ->get();
    }
}
