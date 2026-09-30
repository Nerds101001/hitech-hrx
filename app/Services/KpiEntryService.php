<?php

namespace App\Services;

use App\Models\KpiAssignment;
use App\Models\KpiCheck;
use App\Models\KpiEntry;
use App\Models\User;
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
        // The check for the period being REPORTED, not the one today falls in:
        // a figure filed in October for September belongs to September.
        $check = KpiPeriodService::sync($a, $start);

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

    /**
     * A KPI data owner fills the number on somebody else's behalf.
     *
     * Unlike submit(), this never waits for approval. The owner IS the source
     * -- call hours come off the gateway they run, quotation counts out of the
     * CRM they keep -- so there is nobody better placed to sign it off, and
     * routing it through the approval queue only recreated the bottleneck that
     * left five of seventeen people reported in September.
     *
     * Recorded as reviewed by the owner so the audit trail still shows who
     * stands behind the figure.
     */
    public static function fillForTeam(
        KpiAssignment $a,
        ?float $value,
        int $ownerId,
        ?string $note = null,
        ?Carbon $day = null
    ): ?KpiEntry {
        [$start, $end] = self::periodFor($a, $day);

        // A blank clears the figure rather than storing a zero: "not reported"
        // and "reported as none" score the same today, but they are different
        // facts and a zero would hide a gap.
        if ($value === null) {
            $existing = KpiEntry::where('kpi_assignment_id', $a->id)
                ->where('period_start', $start->toDateString())->first();

            if ($existing) {
                $existing->delete();
                self::recalculate($a, KpiPeriodService::sync($a, $start));
            }

            return null;
        }

        $check = KpiPeriodService::sync($a, $start);

        $entry = KpiEntry::updateOrCreate(
            ['kpi_assignment_id' => $a->id, 'period_start' => $start->toDateString()],
            [
                'tenant_id'      => $a->tenant_id,
                'kpi_check_id'   => $check->id,
                'user_id'        => $a->user_id,
                'period_end'     => $end->toDateString(),
                'value'          => $value,
                'note'           => $note,
                'status'         => KpiEntry::APPROVED,
                'reviewed_by_id' => $ownerId,
                'reviewed_at'    => now(),
                'review_note'    => 'Filled in bulk by the KPI data owner',
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
    public static function recalculate(KpiAssignment $a, ?KpiCheck $check = null, ?Carbon $day = null): ?KpiCheck
    {
        // An auto KPI is filled by kpi:auto-score from real data and has no
        // entries of its own. Recalculating one would set its achieved value to
        // the sum of nothing and wipe a figure that was correct.
        if (!self::isManual($a)) {
            return null;
        }

        $check = $check ?: KpiPeriodService::sync($a, $day);
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
        // A figure can arrive after the period closed -- the owner of the
        // number reports in the first week of the next month, or a correction
        // lands weeks later. sync() will have stamped that check 'missed', and
        // leaving it there would score a reported figure as unreported. A
        // total we now hold reopens it; late, but not missing.
        if ($total > 0 && !$pending && !in_array($check->status, ['completed_on_time', 'completed_late'], true)) {
            $check->status = Carbon::today()->lte(Carbon::parse($check->due_date))
                ? 'completed_on_time' : 'completed_late';
            $check->completed_at = now();
        }
        $check->save();

        return $check;
    }

    /**
     * HR and admin review every KPI. Anyone else -- the "manager" role is held by
     * dozens of people across every department -- reviews only the KPIs that
     * name them as approver, and never their own numbers.
     */
    public static function reviewsEverything(User $reviewer): bool
    {
        return $reviewer->hasRole(['admin', 'hr']);
    }

    /**
     * Should this person see the KPI Approvals page at all? HR/admin always;
     * anyone else only while some KPI names them as its approver. Everyone else
     * would open an empty screen, so the menu hides it instead.
     */
    public static function canOpenApprovals(User $user): bool
    {
        return self::reviewsEverything($user)
            || KpiAssignment::where('approver_id', $user->id)->where('is_active', true)->exists();
    }

    public static function canReview(User $reviewer, KpiEntry $entry): bool
    {
        if (self::reviewsEverything($reviewer)) {
            return true;
        }

        $approverId = optional($entry->assignment)->approver_id;

        return $approverId !== null
            && (int) $approverId === (int) $reviewer->id
            && (int) $entry->user_id !== (int) $reviewer->id;
    }

    /** Narrow an entries query to what this reviewer is allowed to see. */
    public static function scopeToReviewer($query, User $reviewer)
    {
        if (self::reviewsEverything($reviewer)) {
            return $query;
        }

        return $query->where('user_id', '!=', $reviewer->id)
            ->whereHas('assignment', fn ($a) => $a->where('approver_id', $reviewer->id));
    }

    /**
     * Entries in one status (default: awaiting review), newest week first.
     * The Approvals page's three tabs (pending/approved/rejected) and its
     * Excel export all read through this one query, so the reviewer is a
     * required argument: there is no way to get the unfiltered list by
     * forgetting one.
     */
    public static function queue(?int $departmentId, string $status, ?int $userId, User $reviewer)
    {
        return self::scopeToReviewer(KpiEntry::query(), $reviewer)
            ->where('status', $status)
            ->with(['assignment', 'user.department', 'reviewer'])
            ->when($departmentId, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('department_id', $departmentId)))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('period_start')
            ->get();
    }
}
