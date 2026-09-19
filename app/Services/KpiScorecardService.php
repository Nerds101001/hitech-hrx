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
     * @param  Collection<int, User>  $users
     * @return Collection  user id => {user, rows, earned, possible, pct, grade}
     */
    public static function forMonth(Collection $users, Carbon $month): Collection
    {
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();
        $ids   = $users->pluck('id')->all();
        if (!$ids) {
            return collect();
        }

        $checks = KpiCheck::whereIn('user_id', $ids)
            ->whereDate('period_start', '>=', $start->toDateString())
            ->whereDate('period_start', '<=', $end->toDateString())
            ->orderBy('period_start')
            ->get()
            ->groupBy('kpi_assignment_id');

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

        $managers = User::whereIn('id', $users->pluck('reporting_to_id')->filter()->unique())
            ->get(['id', 'first_name', 'last_name'])->keyBy('id');

        return $users->mapWithKeys(function (User $user) use ($assignments, $checks, $managers) {
            $rows = [];
            foreach (($assignments->get($user->id) ?? collect()) as $a) {
                $mine   = $checks->get($a->id) ?? collect();
                $latest = $mine->last();
                $achieved = $mine->isEmpty() || $mine->every(fn ($c) => $c->achieved_value === null)
                    ? null
                    : (float) $mine->sum('achieved_value');

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
                    'score'    => (float) $mine->sum('score'),
                    'checks'   => $mine->count(),
                    'status'   => $latest ? (self::STATUS[$latest->status] ?? ucfirst((string) $latest->status)) : 'Not reported',
                ];
            }
            usort($rows, fn ($x, $y) => [$x->kra === 'Other', $x->kra, $x->title] <=> [$y->kra === 'Other', $y->kra, $y->title]);

            $earned   = round(array_sum(array_column($rows, 'score')), 2);
            $possible = round(array_sum(array_column($rows, 'max')), 2);
            $pct      = $possible > 0 ? round($earned / $possible * 100, 1) : null;
            $manager  = $managers->get($user->reporting_to_id);

            return [$user->id => (object) [
                'user'     => $user,
                'manager'  => $manager ? trim($manager->first_name . ' ' . $manager->last_name) : null,
                'rows'     => $rows,
                'earned'   => $earned,
                'possible' => $possible,
                'pct'      => $pct,
                'grade'    => self::gradeFor($pct),
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
                $r->status === 'Not reported' => 'Nothing was reported for the month.',
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

        $cards = self::forMonth($users, $month)->filter(fn ($c) => !empty($c->rows))->values();
        if ($cards->isEmpty()) {
            return $cards;
        }

        $ytd = self::yearToDate($cards->pluck('user'), $month);
        foreach ($cards as $c) {
            $c->ytd       = $ytd[$c->user->id] ?? [];
            $c->attention = self::attention($c->rows);
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
