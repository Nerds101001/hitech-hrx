<?php

namespace App\Services;

use App\Models\KpiAssignment;
use App\Models\KpiCheck;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * One person's KPI/KRA scorecard for one calendar month, and the PDF of it.
 *
 * The on-screen My Scorecard is a fiscal-year grid; this is the single-month
 * page HR signs and files. It reads only what already exists (assignments and
 * the checks the scoring engine wrote) and never writes, so downloading a
 * scorecard can never change one.
 */
class KpiScorecardService
{
    /** Most people one bulk PDF will hold: dompdf builds the whole file in memory. */
    public const BULK_LIMIT = 100;

    /** Same buckets the Scorecards listing uses. */
    public static function gradeFor(?float $pct): ?string
    {
        return match (true) {
            $pct === null => null,
            $pct >= 80    => 'A - Outstanding',
            $pct >= 70    => 'B - Exceeds Expectation',
            $pct >= 60    => 'C - Meets Expectation',
            default       => 'D - Needs Improvement',
        };
    }

    /** What each grade means in percent, for the line under the total. */
    public static function gradeBand(?string $grade): string
    {
        return match (strtoupper(substr((string) $grade, 0, 1))) {
            'A' => '80% and above',
            'B' => '70% to under 80%',
            'C' => '60% to under 70%',
            'D' => 'under 60%',
            default => '',
        };
    }

    /** "2026-09" → first day of that month; null when malformed or in the future. */
    /**
     * The person's shown-not-scored figures for a month (see KpiAssignment::INFO),
     * title => value, or null for one not entered yet. Empty when they have none.
     */
    public static function infoFor(int $userId, Carbon $month): array
    {
        $out = [];
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $assignments = KpiAssignment::where('user_id', $userId)->where('is_active', true)
            ->where('data_source', KpiAssignment::INFO)->orderBy('id')->get();
        foreach ($assignments as $a) {
            $checks = \App\Models\KpiCheck::where('kpi_assignment_id', $a->id)
                ->whereDate('period_start', '>=', $start->toDateString())
                ->whereDate('period_start', '<=', $end->toDateString())->get();
            $out[$a->title] = $checks->every(fn ($c) => $c->achieved_value === null) ? null : (float) $checks->sum('achieved_value');
        }

        return $out;
    }

    public static function parseMonth(?string $ym): ?Carbon
    {
        if (!is_string($ym) || !preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $ym)) {
            return null;
        }
        $month = Carbon::createFromFormat('!Y-m', $ym);

        return $month->gt(Carbon::today()->startOfMonth()) ? null : $month;
    }

    /** The picker's options: this month and the eleven before it, newest first. */
    public static function months(int $count = 12): array
    {
        $out = [];
        $cursor = Carbon::today()->startOfMonth();
        for ($i = 0; $i < $count; $i++) {
            $out[] = ['key' => $cursor->format('Y-m'), 'label' => $cursor->format('F Y')];
            $cursor->subMonthNoOverflow();
        }

        return $out;
    }

    private const STATUS = [
        'completed_on_time' => 'On time',
        'completed_late'    => 'Late',
        'missed'            => 'Missed',
        'pending'           => 'Pending',
    ];

    /**
     * What the row says about the month. A check that exists but is still
     * "pending" with no figure means nothing has been reported yet, however
     * early the scoring engine created the empty check.
     */
    private static function statusOf(?KpiCheck $latest, ?float $achieved, bool $awaitingApproval, string $frequency = 'monthly'): string
    {
        // A yearly or quarterly figure is a running total until its period closes.
        if ($latest && in_array($frequency, ['quarterly', 'yearly'], true)
            && $achieved !== null && !in_array($latest->status, ['missed'], true)) {
            return 'Year to date';
        }

        if (!$latest) {
            return $awaitingApproval ? 'Awaiting approval' : 'Not reported';
        }
        if ($latest->status !== 'pending') {
            return self::STATUS[$latest->status] ?? ucfirst((string) $latest->status);
        }
        if ($awaitingApproval) {
            return 'Awaiting approval';
        }

        return $achieved === null ? 'Not reported' : 'In progress';
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection  user id => {user, rows, earned, possible, pct, grade}
     */
    public static function forMonth(Collection $users, Carbon $month, bool $live = false): Collection
    {
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();
        $ids   = $users->pluck('id')->all();
        if (!$ids) {
            return collect();
        }

        $inMonth = KpiCheck::whereIn('user_id', $ids)
            ->whereDate('period_start', '>=', $start->toDateString())
            ->whereDate('period_start', '<=', $end->toDateString())
            ->orderBy('period_start')
            ->get();

        // A quarterly or yearly KPI has one check that starts months before this
        // one and is still running; it belongs on every month's scorecard it spans.
        $spanning = KpiCheck::whereIn('user_id', $ids)
            ->whereDate('period_start', '<', $start->toDateString())
            ->whereDate('period_end', '>=', $start->toDateString())
            ->whereHas('assignment', fn ($q) => $q->whereIn('frequency', ['quarterly', 'yearly']))
            ->get();

        $checks = $inMonth->concat($spanning)->sortBy('period_start')->groupBy('kpi_assignment_id');

        // Everything live at the end of the month, plus anything that was
        // scored in it even if it has since been switched off.
        $assignments = KpiAssignment::with('template')
            ->whereIn('user_id', $ids)
            ->where(function ($q) use ($end, $checks) {
                $q->where(fn ($w) => $w->where('is_active', true)->whereDate('created_at', '<=', $end->toDateString()))
                  ->orWhereIn('id', $checks->keys()->all());
            })
            ->get()
            ->groupBy('user_id');

        // Numbers someone has entered that are still waiting for an approver.
        $awaiting = \App\Models\KpiEntry::whereIn('user_id', $ids)
            ->where('status', \App\Models\KpiEntry::SUBMITTED)
            ->whereDate('period_start', '>=', $start->toDateString())
            ->whereDate('period_start', '<=', $end->toDateString())
            ->pluck('kpi_assignment_id')->unique();

        $managers = User::whereIn('id', $users->pluck('reporting_to_id')->filter()->unique())
            ->get(['id', 'first_name', 'last_name'])->keyBy('id');

        return $users->mapWithKeys(function (User $user) use ($assignments, $checks, $managers, $awaiting, $live) {
            $rows = [];
            $info = [];
            foreach (($assignments->get($user->id) ?? collect()) as $a) {
                $mine   = $checks->get($a->id) ?? collect();
                // A figure shown, never scored (e.g. Total Leads Allocated): a card
                // on the scorecard, kept out of the rows, the total and the grade.
                if ($a->isInfo()) {
                    $info[$a->title] = $mine->every(fn ($c) => $c->achieved_value === null) ? null : (float) $mine->sum('achieved_value');
                    continue;
                }
                $latest = $mine->last();
                $achieved = $mine->isEmpty() || $mine->every(fn ($c) => $c->achieved_value === null)
                    ? null
                    : (float) $mine->sum('achieved_value');
                $score = (float) $mine->sum('score');

                // $live: read the source again instead of trusting the stored
                // figure. A check is written when the scoring job last ran, but
                // the data behind it keeps moving -- the sales report stays open
                // past month end and is often reopened -- so a scorecard printed
                // today could otherwise show a number that stopped being true
                // weeks ago. Printing is the moment someone acts on it, so the
                // print recomputes. Nothing is written back: a download must
                // never change a score.
                if ($live && $mine->isNotEmpty() && str_starts_with((string) $a->data_source, 'auto:')) {
                    $from = Carbon::parse($mine->first()->period_start);
                    $to   = Carbon::parse($mine->last()->period_end)->endOfDay();
                    $fresh = \App\Services\KpiAutoSourceService::compute($a, $from, $to);
                    if ($fresh !== null) {
                        $achieved = (float) $fresh;
                        $score = KpiPeriodService::targetScore($a, $achieved);
                    }
                }

                // Nothing to measure it by yet (New Customer Wins before the Sales
                // Report existed): no marks either way - graded on the rest. Any
                // figure stored for that month is from an older rule and is ignored.
                $na = $mine->isNotEmpty()
                    && \App\Services\KpiAutoSourceService::notMeasurable($a, Carbon::parse($mine->last()->period_end));
                if ($na) {
                    $achieved = null;
                    $score = 0.0;
                }

                $rows[] = (object) [
                    'kra'      => $a->kra_group ?: 'Other',
                    'title'    => $a->title,
                    'basis'    => optional($a->template)->description,
                    'category' => $a->category,
                    'target'   => (float) $a->target_value,
                    'achieved' => $achieved,
                    // Only a target KPI has a "% of target" to speak of.
                    'result'   => ($a->scoring_type === 'target' && $achieved !== null)
                        ? KpiPeriodService::resultPercent($a, $achieved) : null,
                    'max'      => (float) $a->max_marks * max(1, $mine->count()),
                    'score'    => $score,
                    'checks'   => $mine->count(),
                    'status'   => $na ? 'Not measurable' : self::statusOf($latest, $achieved, $awaiting->contains($a->id), (string) $a->frequency),
                    'na'       => $na,
                    'banded'   => $a->scoring_type === 'banded',
                    'order'    => (int) $a->id,
                ];
            }
            // The order the department's scorecard was set up in (assignments are
            // stamped in template order), KRA headings by where their first KPI
            // sits, not alphabetically. Hand-added rows without a KRA go last.
            $firstOf = [];
            foreach ($rows as $r) {
                $firstOf[$r->kra] = min($firstOf[$r->kra] ?? PHP_INT_MAX, $r->order);
            }
            usort($rows, fn ($x, $y) => [$x->kra === 'Other', $firstOf[$x->kra], $x->order] <=> [$y->kra === 'Other', $firstOf[$y->kra], $y->order]);

            $earned   = round(array_sum(array_column($rows, 'score')), 2);
            $naMax    = array_sum(array_map(fn ($r) => $r->na ? $r->max : 0, $rows));
            $rowMax   = round(array_sum(array_column($rows, 'max')) - $naMax, 2);
            // The Sales Assessment rule book is graded on raw points out of 100 (60 to 69
            // points is a C), and its parameter weights add to 110, so dividing by the
            // weights would turn the sheet's 63-point C into a D. Every other scorecard
            // is graded on its own marks.
            $onPoints = collect($rows)->contains(fn ($r) => $r->kra === \App\Support\SalesRuleBook::KRA_TITLE);
            // A KPI that cannot be measured comes off the 100 too (e.g. 87).
            $possible = $onPoints ? round(100.0 - $naMax, 2) : $rowMax;
            $pct      = $possible > 0 ? round($earned / $possible * 100, 1) : null;
            $manager  = $managers->get($user->reporting_to_id);

            return [$user->id => (object) [
                'user'     => $user,
                'manager'  => $manager ? trim($manager->first_name . ' ' . $manager->last_name) : null,
                'rows'     => $rows,
                'earned'   => $earned,
                'possible' => $possible,
                'onPoints' => $onPoints,
                'pct'      => $pct,
                'grade'    => self::gradeFor($pct),
                'info'     => $info,
            ]];
        });
    }

    /**
     * Financial-year-to-date, month by month (April → $month), for page two.
     * One bulk pass per month, so the cost does not grow with the number of people.
     *
     * @return array<int, list<object>>  user id => rows of {label, earned, possible, pct, grade, current}
     */
    public static function yearToDate(Collection $users, Carbon $month): array
    {
        $fyStart = $month->month >= 4
            ? Carbon::create($month->year, 4, 1)->startOfDay()
            : Carbon::create($month->year - 1, 4, 1)->startOfDay();

        $out = [];
        for ($m = $fyStart->copy(); $m->lte($month); $m->addMonthNoOverflow()) {
            foreach (self::forMonth($users, $m) as $uid => $c) {
                if (empty($c->rows)) {
                    continue;   // not yet holding a KPI that month
                }
                $out[$uid][] = (object) [
                    'label'    => $m->format('M Y'),
                    'earned'   => $c->earned,
                    'possible' => $c->possible,
                    'pct'      => $c->pct,
                    'grade'    => $c->grade,
                    'current'  => $m->isSameMonth($month),
                ];
            }
        }

        return $out;
    }

    /** KPIs worth a conversation this month, biggest marks at stake first. */
    public static function attention(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $why = match (true) {
                $r->status === 'Not reported'     => 'Nothing was reported for the month.',
                $r->status === 'Awaiting approval' => 'Reported, waiting for the approver.',
                $r->status === 'Missed'       => 'Marked as missed.',
                $r->status === 'Late'         => 'Reported late.',
                $r->max > 0 && $r->result !== null && $r->score < $r->max * 0.5
                    => rtrim(rtrim(number_format($r->result, 1), '0'), '.') . '% of target; '
                       . rtrim(rtrim(number_format($r->score, 2), '0'), '.') . ' of ' . rtrim(rtrim(number_format($r->max, 2), '0'), '.') . ' marks.',
                default => null,
            };
            if ($why !== null) {
                $out[] = (object) ['title' => $r->title, 'why' => $why, 'max' => $r->max];
            }
        }
        usort($out, fn ($a, $b) => $b->max <=> $a->max);

        return array_slice($out, 0, 12);
    }

    /** Only the people who actually hold a KPI that month, in the order given. */
    public static function cardsFor(Collection $users, Carbon $month): Collection
    {
        $users = \Illuminate\Database\Eloquent\Collection::make($users->all())->loadMissing(['department', 'designation', 'site']);

        // live: the printed sheet is the one people act on, so its figures are
        // read from the source at print time rather than from the last scoring
        // run. The year-to-date table below stays on stored figures -- it is a
        // trend, and recomputing twelve months per person would make a bulk
        // download crawl.
        $cards = self::forMonth($users, $month, true)->filter(fn ($c) => !empty($c->rows))->values();
        if ($cards->isEmpty()) {
            return $cards;
        }

        $ytd = self::yearToDate($cards->pluck('user'), $month);
        $from = $month->copy()->startOfMonth();
        $to   = $month->copy()->endOfMonth()->endOfDay();
        foreach ($cards as $c) {
            $c->ytd       = $ytd[$c->user->id] ?? [];
            $c->attention = self::attention($c->rows);
            // Shown on the sheet whether or not the person carries an
            // attendance KPI: it is context for every other number on it.
            $c->attendance = \App\Services\KpiAutoSourceService::attendancePercentFor($c->user->id, $from, $to);
        }

        return $cards;
    }

    /** The PDF download: one page per card. */
    public static function download(Collection $cards, Carbon $month, string $filename)
    {
        return Pdf::loadView('tenant.kpi.scorecard-pdf', [
            'cards'       => $cards,
            'month'       => $month,
            'companyName' => optional(\App\Models\Settings::first())->company_name,
        ])->setPaper('a4', 'portrait')->download($filename);
    }
}
