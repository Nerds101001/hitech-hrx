<?php

namespace App\Services;

use App\Models\KpiAssignment;
use App\Models\KpiCheck;
use App\Models\KpiEntry;
use Carbon\Carbon;

/**
 * The submit → HR approve loop for manually measured KPIs.
 *
 * Scoring stays on the monthly KpiCheck. A weekly *check* would award its full
 * marks every week and turn a 150-mark scorecard into roughly 250, so staff log
 * a number here instead and the month's achieved value is the sum of whatever
 * HR has approved.
 *
 * How often they log it follows the KPI's own frequency — see periodFor(). A
 * monthly KPI is reported once for the month, which is the only correct thing
 * to do for the ones that count unique customers.
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
     * The window one entry covers, taken from the KPI's own frequency.
     *
     * This matters beyond tidiness. Several of these count *unique customers* —
     * visits, quotations, freight, upgrades — and a customer seen in two
     * different weeks is still one customer. Summing weekly entries would count
     * them twice, so a monthly KPI is reported once for the whole month.
     */
    public static function periodFor(KpiAssignment $a, ?Carbon $day = null): array
    {
        $day = ($day ?: Carbon::today())->copy()->startOfDay();

        return match ($a->frequency) {
            'weekly', 'fortnightly' => self::weekOf($day),
            'daily'                 => [$day->copy(), $day->copy()],
            default                 => [$day->copy()->startOfMonth(), $day->copy()->endOfMonth()],
        };
    }

    /** How the entry window reads on screen. */
    public static function periodLabel(KpiAssignment $a, ?Carbon $day = null): string
    {
        [$start, $end] = self::periodFor($a, $day);

        return in_array($a->frequency, ['weekly', 'fortnightly'], true)
            ? $start->format('d M') . ' – ' . $end->format('d M Y')
            : $start->format('F Y');
    }

    /**
     * Record what someone is reporting for a week. Re-submitting the same week
     * overwrites it, so a correction does not create a second row.
     */
    public static function submit(KpiAssignment $a, float $value, ?string $note = null, ?Carbon $day = null): KpiEntry
    {
        [$start, $end] = self::periodFor($a, $day);
        $check = KpiPeriodService::sync($a);   // the check this rolls into

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
        // An auto KPI is filled by kpi:auto-score from real data and has no
        // entries of its own. Recalculating one would set its achieved value to
        // the sum of nothing and wipe a figure that was correct.
        if (!self::isManual($a)) {
            return null;
        }

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

    /**
     * Entries in one status (default: awaiting review), newest week first.
     * The Approvals page's three tabs (pending/approved/rejected) and its
     * Excel export all read through this one query.
     */
    public static function queue(?int $departmentId = null, string $status = KpiEntry::SUBMITTED)
    {
        return KpiEntry::query()
            ->where('status', $status)
            ->with(['assignment', 'user.department', 'reviewer'])
            ->when($departmentId, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('department_id', $departmentId)))
            ->orderByDesc('period_start')
            ->get();
    }
}
