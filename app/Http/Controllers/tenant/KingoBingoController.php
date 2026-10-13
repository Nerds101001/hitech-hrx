<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\CcSalespersonMap;
use App\Models\KingoBingoScore;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use App\Models\SalespersonTarget;
use App\Models\SalesVisit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class KingoBingoController extends Controller
{
    public function index(Request $request)
    {
        $user     = auth()->user();
        $isAdmin  = $user->hasRole(['admin', 'Admin', 'hr', 'HR']) || $user->can('kingo_bingo.manage');
        $isCcare  = $user->isCcareAgent();
        $isNewBiz = $user->isNewBizAgent();

        // Build FY
        $now = Carbon::now();
        $currentMonth = $now->month;
        $currentYear  = $now->year;
        $fyStartYear  = $currentMonth >= 4 ? $currentYear : $currentYear - 1;
        $defaultFy    = $fyStartYear . '-' . ($fyStartYear + 1);
        $selectedFy   = $request->input('fy', $defaultFy);

        $parts = explode('-', $selectedFy);
        if (count($parts) == 2) $fyStartYear = (int)$parts[0];

        $fyStart = Carbon::create($fyStartYear, 4, 1);
        $fyEnd   = Carbon::create($fyStartYear + 1, 3, 31);

        $months = [];
        $temp   = $fyStart->copy();
        while ($temp <= $fyEnd) {
            $months[] = ['key' => $temp->format('Y-m'), 'display' => strtoupper($temp->format('M'))];
            $temp->addMonth();
        }
        $currentMonthKey = $now->format('Y-m');

        // ── Determine which salespersons this user may view ──
        //   Admin/HR      → everyone (null)
        //   Manager       → self + their direct-report team
        //   CCare/New Biz → self + their mapped salespersons (+ derived from data)
        //   Salesperson   → only themselves
        // Single source of truth in SalesScope.
        $allowedSpIds = \App\Support\SalesScope::allowedUserIds($user);

        // A sales manager defaults to their OWN scorecard. They can switch to
        // the whole team (?team=1) or jump to one member (?salesperson_id=X).
        $isManager = $user->hasRole(['manager', 'Manager']);
        $teamView  = $request->boolean('team');
        $hasTeam   = $isManager && $allowedSpIds !== null && count($allowedSpIds) > 1;

        // Only the sales organisation belongs on this page — Sales, New Biz and
        // Customer Care departments (not the whole employee directory).
        $salesOrgDept = function ($q) {
            $q->where('name', 'like', '%Sales%')
              ->orWhere('name', 'like', '%New Biz%')
              ->orWhere('name', 'like', '%New Business%')
              ->orWhere('name', 'like', '%Customer Care%')
              ->orWhere('name', 'CCARE');
        };

        // Honour an explicit salesperson filter only if the requester may see that person
        if ($request->filled('salesperson_id')) {
            $reqId = (int) $request->salesperson_id;
            if ($allowedSpIds === null || in_array($reqId, $allowedSpIds)) {
                $salespersons = User::where('id', $reqId)->get();
            } else {
                $salespersons = collect(); // silently deny
            }
        } elseif ($isManager && !$teamView) {
            // Default manager view: only their own card.
            $salespersons = User::where('id', $user->id)->get();
        } elseif ($allowedSpIds === null) {
            $salespersons = User::where('status', 'active')
                ->whereHas('department', $salesOrgDept)
                ->orderBy('first_name')->get();
        } else {
            $salespersons = User::whereIn('id', $allowedSpIds)->orderBy('first_name')->get();
        }

        $monthKeys      = array_column($months, 'key');
        $salespersonIds = $salespersons->pluck('id')->toArray();

        // Order the scorecards so salespersons who actually have data (imported
        // targets, then pipeline rows) appear first, and the rest fall to the
        // bottom. The users.name column is frequently NULL (the display name is
        // built from first_name/last_name), so orderBy('name') gave a random
        // order that could lead with an empty, brand-new salesperson.
        if ($salespersons->count() > 1) {
            $idsWithTargets = SalespersonTarget::whereIn('salesperson_id', $salespersonIds)
                ->whereIn('month_year', $monthKeys)->distinct()->pluck('salesperson_id')->flip();
            $idsWithPipeline = SalesPipeline::whereNull('deleted_at')
                ->whereIn('salesperson_id', $salespersonIds)->distinct()->pluck('salesperson_id')->flip();
            $salespersons = $salespersons->sortBy(function ($sp) use ($idsWithTargets, $idsWithPipeline) {
                $t = $idsWithTargets->has($sp->id) ? 0 : 1;
                $p = $idsWithPipeline->has($sp->id) ? 0 : 1;
                return sprintf('%d-%d-%s', $t, $p, strtolower(trim(($sp->first_name ?? '') . ' ' . ($sp->last_name ?? ''))));
            })->values();
            $salespersonIds = $salespersons->pluck('id')->toArray();
        }

        // Bingo/Kingo targets
        $targets = SalespersonTarget::whereIn('salesperson_id', $salespersonIds)
            ->whereIn('month_year', $monthKeys)
            ->get()->groupBy('salesperson_id');

        // Manually-set targets (razor blade target numbers, payment targets, etc.)
        $kbTargets = KingoBingoScore::whereIn('salesperson_id', $salespersonIds)
            ->whereIn('month_year', $monthKeys)
            ->get()->groupBy('salesperson_id');

        // ── Auto-calculate achieved values ──
        $autoAchieved = [];

        // Load all completed visits for these salespersons in the FY
        $fyStartDate = Carbon::create($fyStartYear, 4, 1)->startOfDay();
        $fyEndDate   = Carbon::create($fyStartYear + 1, 3, 31)->endOfDay();

        $allVisits = SalesVisit::whereIn('salesperson_id', $salespersonIds)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$fyStartDate, $fyEndDate])
            ->get();

        // Group visits by salesperson and month
        $visitsBySpMonth = [];
        foreach ($allVisits as $v) {
            $mk = Carbon::parse($v->completed_at)->format('Y-m');
            $visitsBySpMonth[$v->salesperson_id][$mk][] = $v;
        }

        foreach ($salespersonIds as $spId) {
            // Every customer this person is tagged on, in any capacity — as the
            // salesperson, as CCare, as New Biz, or as an additional rep. All of
            // that is their sale and all of it counts.
            //
            // Each customer is counted ONCE even when they hold several roles on
            // it, which is common: of Manpreet Kaur Kohli's 136 extra-rep tags,
            // 96 are on customers she already holds as CCare. This is a WHERE/OR
            // with an EXISTS subquery, so a row satisfying two conditions still
            // comes back a single time — a JOIN onto sales_pipeline_extra_reps
            // would repeat it once per tag and inflate the total.
            //
            // Matches what the Sales Report shows the person for themselves.
            $pipelines = SalesPipeline::with(['months' => function ($q) use ($monthKeys) {
                $q->whereIn('month_year', $monthKeys);
            }])->where(function ($q) use ($spId) {
                $q->where('salesperson_id', $spId)
                  ->orWhere('ccare_id', $spId)
                  ->orWhere('new_biz_id', $spId)
                  ->orWhere('new_biz_2_id', $spId)
                  ->orWhere('former_new_biz_id', $spId)
                  ->orWhereExists(function ($s) use ($spId) {
                      $s->selectRaw('1')->from('sales_pipeline_extra_reps as er')
                        ->whereColumn('er.sales_pipeline_id', 'sales_pipelines.id')
                        ->where('er.user_id', $spId);
                  });
                // Customers held before a leaver handover still count for the earlier months.
                $heldBefore = \App\Models\SalesPipelineHandover::pipelineIdsHeldBefore([(int) $spId]);
                if ($heldBefore->isNotEmpty()) {
                    $q->orWhereIn('id', $heldBefore->all());
                }
            })->get();

            $lockedPipelines = $pipelines->where('is_locked', true);

            // Forecast is split by the capacity the person holds on each
            // customer. A service second-representative tag puts that
            // customer's forecast in the Service column; everything else they
            // are tagged on lands in their own. A customer is counted once
            // either way -- one person can hold both roles on the same row
            // (1 of the 52 service tags does today), so the service set is
            // subtracted from the main one rather than added alongside it.
            $serviceRowIds = \App\Models\SalesPipelineExtraRep::where('user_id', $spId)
                ->where('type', 'service')
                ->whereIn('sales_pipeline_id', $pipelines->pluck('id'))
                ->pluck('sales_pipeline_id')
                ->all();

            // A customer that moved New Biz -> CCare counts its months before
            // the move for the New Biz person and the rest for CCare.
            $monthOf = fn ($p, $mk) => $p->creditsMonth((int) $spId, $mk) ? $p->months->firstWhere('month_year', $mk) : null;

            foreach ($monthKeys as $mk) {
                // Bingo/Kingo actual (locked pipelines only)
                $actualSale = $lockedPipelines->sum(function ($p) use ($mk, $monthOf) {
                    $m = $monthOf($p, $mk);
                    return $m ? (float)$m->sale_amount : 0;
                });

                // Payment collection (in lacs): sale_amounts already stored in lakh units
                $paymentAchieved = round($pipelines->sum(function ($p) use ($mk, $monthOf) {
                    $m = $monthOf($p, $mk);
                    return $m ? (float)$m->sale_amount : 0;
                }), 2);

                // KPI counts from completed visits this month
                $monthVisits = collect($visitsBySpMonth[$spId][$mk] ?? []);

                // Forecast for the month, split the same way.
                $forecastOwn = round($pipelines->reject(fn($p) => in_array($p->id, $serviceRowIds))
                    ->sum(function ($p) use ($mk, $monthOf) {
                        $m = $monthOf($p, $mk);
                        return $m ? (float) $m->forecast_amount : 0;
                    }), 2);
                $forecastService = round($pipelines->filter(fn($p) => in_array($p->id, $serviceRowIds))
                    ->sum(function ($p) use ($mk, $monthOf) {
                        $m = $monthOf($p, $mk);
                        return $m ? (float) $m->forecast_amount : 0;
                    }), 2);

                $autoAchieved[$spId][$mk] = [
                    'actual_sale'       => $actualSale,
                    'forecast'          => $forecastOwn,
                    'forecast_service'  => $forecastService,
                    'razor_achieved'    => $monthVisits->where('razor_blade', true)->count(),
                    'new_cust'          => $monthVisits->where('is_new_customer', true)->count(),
                    'upsell_achieved'   => $monthVisits->where('is_upsell', true)->count(),
                    'payment_achieved'  => $paymentAchieved,
                    'rate_achieved'     => $monthVisits->where('rate_increase', true)->count(),
                    'nif_achieved'      => $monthVisits->where('is_nif', true)->count(),
                    'training_achieved' => $monthVisits->where('training_done', true)->count(),
                    'mom_achieved'      => $monthVisits->where('mom_submitted', true)->count(),
                    'competitor_insights' => $monthVisits->where('competitor_insight', true)->count(),
                    'product_ideas'     => $monthVisits->where('product_idea', true)->count(),
                    'total_visits'      => $monthVisits->count(),
                    // Customer/Order ratio from pipeline
                    'total_customers'   => $pipelines->filter(fn ($p) => $p->creditsMonth((int) $spId, $mk))->count(),
                    'total_orders'      => $pipelines->filter(function ($p) use ($mk, $monthOf) {
                        $m = $monthOf($p, $mk);
                        return $m && (float)$m->sale_amount > 0;
                    })->count(),
                ];
            }
        }

        $canEditTargets = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']) ||
            $user->isCcareAgent() ||
            $user->can('kingo_bingo.manage');

        // Dropdown for salesperson filter — respect the same access scope, and
        // list only the sales organisation (Sales / New Biz / Customer Care).
        if ($allowedSpIds === null) {
            $allUsers = User::where('status', 'active')
                ->whereHas('department', $salesOrgDept)
                ->orderBy('first_name')->get();
        } elseif (!empty($allowedSpIds)) {
            $allUsers = User::whereIn('id', $allowedSpIds)->orderBy('first_name')->get();
        } else {
            $allUsers = collect();
        }

        $fyOptions = [];
        for ($y = 2021; $y <= Carbon::now()->year + 1; $y++) {
            $fyOptions[] = $y . '-' . ($y + 1);
        }

        return view('tenant.kingo-bingo.index', compact(
            'salespersons', 'months', 'targets', 'kbTargets', 'autoAchieved',
            'selectedFy', 'defaultFy', 'fyOptions', 'currentMonthKey',
            'isAdmin', 'isCcare', 'isNewBiz', 'allUsers', 'canEditTargets',
            'isManager', 'teamView', 'hasTeam'
        ));
    }

    public function fillTargets(Request $request)
    {
        // Target setting is Admin/HR only — see SalesTargetController::update.
        if (!$this->canSetTargets()) {
            abort(403, 'Only Admin/HR can set targets.');
        }

        return view('tenant.kingo-bingo.fill-targets', $this->buildTargetsGrid($request));
    }

    /**
     * The same grid as a spreadsheet -- same six rows per person, same
     * colours. Carries the page's own fy/rep/dept/desig query string so what
     * downloads is what the person is looking at, not the unfiltered book.
     */
    public function exportTargetsExcel(Request $request)
    {
        if (!$this->canSetTargets()) {
            abort(403, 'Only Admin/HR can set targets.');
        }

        $grid = $this->buildTargetsGrid($request);
        $file = 'kingo-bingo-targets-FY' . $grid['selectedFy'] . '-' . Carbon::now()->format('Ymd-Hi') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\KingoBingoTargetsExport($grid), $file
        );
    }

    /** The same grid as a PDF. A3 landscape -- 19 columns will not fit on A4. */
    public function exportTargetsPdf(Request $request)
    {
        if (!$this->canSetTargets()) {
            abort(403, 'Only Admin/HR can set targets.');
        }

        $grid = $this->buildTargetsGrid($request);
        $file = 'kingo-bingo-targets-FY' . $grid['selectedFy'] . '-' . Carbon::now()->format('Ymd-Hi') . '.pdf';

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('tenant.kingo-bingo.fill-targets-pdf', $grid)
            ->setPaper('a3', 'landscape')
            ->download($file);
    }

    /**
     * Everything the Fill Targets grid is built from. Shared by the page and
     * both exports so a downloaded file can never drift from the screen.
     */
    private function buildTargetsGrid(Request $request): array
    {
        $now         = Carbon::now();
        $fyStartYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $selectedFy  = $request->input('fy', $fyStartYear . '-' . ($fyStartYear + 1));
        $parts       = explode('-', $selectedFy);
        if (count($parts) === 2) $fyStartYear = (int)$parts[0];

        $fyStart = Carbon::create($fyStartYear, 4, 1);
        $fyEnd   = Carbon::create($fyStartYear + 1, 3, 31);
        $months  = [];
        $temp    = $fyStart->copy();
        while ($temp <= $fyEnd) {
            $months[] = ['key' => $temp->format('Y-m'), 'label' => strtoupper($temp->format('M'))];
            $temp->addMonth();
        }
        $monthKeys      = array_column($months, 'key');
        $currentMonthKey = $now->format('Y-m');

        // This page is only ever about Sales / New Biz / Customer Care people --
        // match on EITHER department or designation, and never list anyone else.
        $salesScope = function ($q) {
            $like = ['%Sales%', '%New Biz%', '%New Business%', '%Customer Care%', '%CCARE%'];
            $q->whereHas('department', function ($d) use ($like) {
                $d->where(function ($w) use ($like) {
                    foreach ($like as $l) $w->orWhere('name', 'like', $l);
                });
            })->orWhereHas('designation', function ($d) use ($like) {
                $d->where(function ($w) use ($like) {
                    foreach ($like as $l) $w->orWhere('name', 'like', $l);
                });
            });
        };

        $spQuery = User::with(['department', 'designation'])
            ->where('status', 'active')->notTerminated()
            ->where($salesScope);

        // Only Admin/HR reach this page now, and they set targets for everyone —
        // the old "restrict to my mapped salespersons" branch no longer applies.
        $everyone = $spQuery->orderBy('first_name')->get();

        // Filter dropdown options, built from who is actually on the page --
        // always the full set, so narrowing to one department does not empty
        // the dropdown you would use to get back out of it.
        $filterDepartments = $everyone->pluck('department.name')->filter()->unique()->sort()->values();
        $filterDesignations = $everyone->pluck('designation.name')->filter()->unique()->sort()->values();

        // On screen the three filters are pure JavaScript. The export re-reads
        // them from the query string and applies the identical test, so the
        // downloaded file matches what is on the screen rather than the whole book.
        $fRep   = strtolower(trim((string) $request->input('rep', '')));
        $fDept  = strtolower(trim((string) $request->input('dept', '')));
        $fDesig = strtolower(trim((string) $request->input('desig', '')));

        $salespersons = $everyone->filter(function ($sp) use ($fRep, $fDept, $fDesig) {
            if ($fRep !== '' && !str_contains(strtolower($sp->name), $fRep)) return false;
            if ($fDept !== '' && strtolower($sp->department->name ?? '') !== $fDept) return false;
            if ($fDesig !== '' && strtolower($sp->designation->name ?? '') !== $fDesig) return false;
            return true;
        })->values();

        // Human-readable "what you are looking at", printed on the exports.
        $scopeLabel = implode(' · ', array_filter([
            $fDept  !== '' ? ucwords($fDept) : null,
            $fDesig !== '' ? ucwords($fDesig) : null,
            $fRep   !== '' ? 'search "' . $fRep . '"' : null,
        ]));

        $annualKey    = $fyStartYear . '-ANN';
        $quarterKeys  = [
            $fyStartYear . '-Q1',
            $fyStartYear . '-Q2',
            $fyStartYear . '-Q3',
            $fyStartYear . '-Q4',
        ];
        $allKeys = array_merge([$annualKey], $quarterKeys, $monthKeys);

        $targets = SalespersonTarget::whereIn('salesperson_id', $salespersons->pluck('id'))
            ->whereIn('month_year', $allKeys)
            ->get()
            ->groupBy('salesperson_id');

        $fyOptions = [];
        for ($y = 2021; $y <= Carbon::now()->year + 1; $y++) {
            $fyOptions[] = $y . '-' . ($y + 1);
        }

        // Actuals shown beside the targets, so whoever is filling them in can
        // see what the person is forecasting and what they have already booked
        // rather than setting a number blind.
        //
        // Same split as the scorecard: a service second-representative tag puts
        // that customer's forecast in Service Fcst, every other tagging in the
        // person's own. A customer counts once, so the service set is removed
        // from the main one instead of sitting in both.
        // Read the whole book once and bucket it in memory. This used to be a
        // query per salesperson, which at ~100 people meant ~200 round trips
        // over a five-way OR and made the page -- and the exports built from
        // it -- take tens of seconds.
        $spIds   = $salespersons->pluck('id')->all();
        $spIdSet = array_flip($spIds);

        $extraReps = \App\Models\SalesPipelineExtraRep::whereIn('user_id', $spIds)
            ->get(['sales_pipeline_id', 'user_id', 'type']);

        $repsByPipeline = [];
        foreach ($extraReps as $er) {
            $repsByPipeline[$er->sales_pipeline_id][] = $er;
        }

        $pipelines = SalesPipeline::with(['months' => function ($q) use ($monthKeys) {
                $q->whereIn('month_year', $monthKeys);
            }])
            ->where(function ($q) use ($spIds, $repsByPipeline) {
                $q->whereIn('salesperson_id', $spIds)
                  ->orWhereIn('ccare_id', $spIds)
                  ->orWhereIn('new_biz_id', $spIds)
                  ->orWhereIn('new_biz_2_id', $spIds)
                  ->orWhereIn('former_new_biz_id', $spIds);
                if ($repsByPipeline !== []) {
                    $q->orWhereIn('id', array_keys($repsByPipeline));
                }
                // Customers a leaver held before a handover still count for their earlier months.
                $heldBefore = \App\Models\SalesPipelineHandover::pipelineIdsHeldBefore($spIds);
                if ($heldBefore->isNotEmpty()) {
                    $q->orWhereIn('id', $heldBefore->all());
                }
            })->get();

        $liveByCsp = [];
        foreach ($spIds as $id) {
            foreach ($monthKeys as $mk) {
                $liveByCsp[$id][$mk] = ['forecast' => 0.0, 'forecast_service' => 0.0, 'actual' => 0.0];
            }
        }

        foreach ($pipelines as $p) {
            // Who is tagged on this customer, and in what capacity. A service
            // second-representative tag routes the customer into that person's
            // Service Fcst; every other tagging goes to their own. A customer
            // counts once per person, never in both.
            $tagged = [];
            foreach ([$p->salesperson_id, $p->ccare_id, $p->new_biz_id, $p->new_biz_2_id, $p->former_new_biz_id] as $uid) {
                if ($uid && isset($spIdSet[$uid])) {
                    $tagged[$uid] = $tagged[$uid] ?? false;
                }
            }
            foreach ($repsByPipeline[$p->id] ?? [] as $er) {
                if (!isset($spIdSet[$er->user_id])) {
                    continue;
                }
                // A service tag wins over any other tagging on the same row.
                $tagged[$er->user_id] = ($tagged[$er->user_id] ?? false) || $er->type === 'service';
            }
            // People who held a role before a leaver handover: creditsMonth() decides which months are theirs.
            foreach (\App\Models\SalesPipelineHandover::eventsFor([$p->id])[$p->id] ?? [] as $ev) {
                foreach ([$ev->from_user_id, $ev->to_user_id] as $uid) {
                    if ($uid && isset($spIdSet[$uid])) {
                        $tagged[$uid] = $tagged[$uid] ?? false;
                    }
                }
            }

            if ($tagged === []) {
                continue;
            }

            foreach ($p->months as $m) {
                $mk = $m->month_year;
                $fc = (float) $m->forecast_amount;
                $sa = (float) $m->sale_amount;

                foreach ($tagged as $uid => $isService) {
                    // A customer that moved New Biz -> CCare: each side keeps its own months.
                    if (!isset($liveByCsp[$uid][$mk]) || !$p->creditsMonth((int) $uid, $mk)) {
                        continue;
                    }
                    $liveByCsp[$uid][$mk][$isService ? 'forecast_service' : 'forecast'] += $fc;
                    // Booked business is booked business whoever tagged it.
                    $liveByCsp[$uid][$mk]['actual'] += $sa;
                }
            }
        }

        foreach ($liveByCsp as &$byMonth) {
            foreach ($byMonth as &$cell) {
                $cell['forecast']         = round($cell['forecast'], 2);
                $cell['forecast_service'] = round($cell['forecast_service'], 2);
                $cell['actual']           = round($cell['actual'], 2);
            }
        }
        unset($byMonth, $cell);

        return compact(
            'salespersons', 'months', 'targets', 'monthKeys',
            'annualKey', 'quarterKeys', 'currentMonthKey', 'selectedFy', 'fyOptions',
            'filterDepartments', 'filterDesignations', 'liveByCsp', 'scopeLabel',
            'fRep', 'fDept', 'fDesig'
        );
    }

    // Update a manually-set target value (razor_blade_target, payment_target, etc.)
    /** Admin/HR, or anyone directly granted kingo_bingo.manage, may create or change targets. */
    private function canSetTargets(): bool
    {
        return auth()->user()->hasRole(['admin', 'hr']) || auth()->user()->can('kingo_bingo.manage');
    }

    public function updateTarget(Request $request)
    {
        if (!$this->canSetTargets()) {
            return response()->json(['success' => false, 'message' => 'Only Admin/HR can set targets.'], 403);
        }
        $request->validate([
            'salesperson_id' => 'required|exists:users,id',
            'month_year'     => 'required|string|size:7',
            'field'          => 'required|in:bingo_target,kingo_target,super_kingo_target',
            'value'          => 'nullable|numeric',
        ]);

        $target = SalespersonTarget::firstOrCreate([
            'salesperson_id' => $request->salesperson_id,
            'month_year'     => $request->month_year,
        ]);

        $field = $request->field;
        $target->$field = $request->value !== null ? (float)$request->value : 0;
        $target->save();

        return response()->json(['success' => true, 'value' => $target->$field]);
    }

    // Update a KPI target number (e.g. razor_blade_target = 3 per month)
    public function updateKpiTarget(Request $request)
    {
        if (!$this->canSetTargets()) {
            return response()->json(['success' => false, 'message' => 'Only Admin/HR can set targets.'], 403);
        }
        $allowed = [
            'razor_blade_target', 'new_customers_target', 'upsell_target',
            'payment_target', 'rate_target', 'nif_target', 'training_target', 'mom_target',
            'training_achieved', 'mom_achieved', // these two stay manual (no DB source)
        ];

        $request->validate([
            'salesperson_id' => 'required|exists:users,id',
            'month_year'     => 'required|string|size:7',
            'field'          => 'required|in:' . implode(',', $allowed),
            'value'          => 'nullable|numeric',
        ]);

        $score = KingoBingoScore::firstOrCreate(
            ['salesperson_id' => $request->salesperson_id, 'month_year' => $request->month_year],
            ['razor_blade_target'=>3,'new_customers_target'=>4,'upsell_target'=>2,'rate_target'=>2,'nif_target'=>3,'training_target'=>2,'mom_target'=>3]
        );

        $field = $request->field;
        $score->$field = $request->value !== null ? (float)$request->value : 0;
        $score->save();

        return response()->json(['success' => true, 'value' => $score->$field]);
    }
}
