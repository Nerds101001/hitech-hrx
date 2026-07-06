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
        $isAdmin  = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']) || $user->can('kingo_bingo.manage');
        $isCcare  = $user->department && $user->department->name === 'Customer Care';
        $isNewBiz = $user->department && $user->department->name === 'New Biz';

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
        // Admin/HR/Manager → everyone
        // CCare            → only their mapped salespersons (cc_salesperson_map)
        // New Biz          → salespersons on pipelines where new_biz_id = user->id
        // Salesperson      → only themselves
        if ($isAdmin) {
            $allowedSpIds = null; // null = no restriction
        } elseif ($isCcare || $isNewBiz) {
            $allowedSpIds = CcSalespersonMap::where('cc_user_id', $user->id)
                ->pluck('sales_user_id')->unique()->values()->toArray();
        } else {
            $allowedSpIds = [$user->id];
        }

        // Honour an explicit salesperson filter only if the requester may see that person
        if ($request->filled('salesperson_id')) {
            $reqId = (int) $request->salesperson_id;
            if ($allowedSpIds === null || in_array($reqId, $allowedSpIds)) {
                $salespersons = User::where('id', $reqId)->get();
            } else {
                $salespersons = collect(); // silently deny
            }
        } elseif ($allowedSpIds === null) {
            $salespersons = User::where('status', 'active')->orderBy('name')->get();
        } else {
            $salespersons = User::whereIn('id', $allowedSpIds)->orderBy('name')->get();
        }

        $monthKeys      = array_column($months, 'key');
        $salespersonIds = $salespersons->pluck('id')->toArray();

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
            // Pipeline data for Bingo/Kingo actual and Payment collection
            $pipelines = SalesPipeline::with(['months' => function ($q) use ($monthKeys) {
                $q->whereIn('month_year', $monthKeys);
            }])->where('salesperson_id', $spId)->get();

            $lockedPipelines = $pipelines->where('is_locked', true);

            foreach ($monthKeys as $mk) {
                // Bingo/Kingo actual (locked pipelines only)
                $actualSale = $lockedPipelines->sum(function ($p) use ($mk) {
                    $m = $p->months->firstWhere('month_year', $mk);
                    return $m ? (float)$m->sale_amount : 0;
                });

                // Payment collection (in lacs): sale_amounts already stored in lakh units
                $paymentAchieved = round($pipelines->sum(function ($p) use ($mk) {
                    $m = $p->months->firstWhere('month_year', $mk);
                    return $m ? (float)$m->sale_amount : 0;
                }), 2);

                // KPI counts from completed visits this month
                $monthVisits = collect($visitsBySpMonth[$spId][$mk] ?? []);

                $autoAchieved[$spId][$mk] = [
                    'actual_sale'       => $actualSale,
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
                    'total_customers'   => $pipelines->count(),
                    'total_orders'      => $pipelines->filter(function ($p) use ($mk) {
                        $m = $p->months->firstWhere('month_year', $mk);
                        return $m && (float)$m->sale_amount > 0;
                    })->count(),
                ];
            }
        }

        $canEditTargets = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']) ||
            ($user->department && $user->department->name === 'Customer Care') ||
            $user->can('kingo_bingo.manage');

        // Dropdown for salesperson filter — respect the same access scope
        if ($allowedSpIds === null) {
            $allUsers = User::where('status', 'active')->orderBy('name')->get();
        } elseif (!empty($allowedSpIds)) {
            $allUsers = User::whereIn('id', $allowedSpIds)->orderBy('name')->get();
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
            'isAdmin', 'isCcare', 'isNewBiz', 'allUsers', 'canEditTargets'
        ));
    }

    public function fillTargets(Request $request)
    {
        $user    = auth()->user();
        $isAdmin = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']) || $user->can('kingo_bingo.manage');
        $isCcare = ($user->department && $user->department->name === 'Customer Care') || $user->can('kingo_bingo.manage');
        $isNewBiz= ($user->department && $user->department->name === 'New Biz') || $user->can('kingo_bingo.manage');

        if (!$isAdmin && !$isCcare && !$isNewBiz) {
            abort(403);
        }

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

        if ($isAdmin) {
            $salespersons = User::where('status', 'active')->orderBy('name')->get();
        } else {
            $spIds = CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id')->unique();
            $salespersons = User::whereIn('id', $spIds)->orderBy('name')->get();
        }

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

        return view('tenant.kingo-bingo.fill-targets', compact(
            'salespersons', 'months', 'targets', 'monthKeys',
            'annualKey', 'quarterKeys', 'currentMonthKey', 'selectedFy', 'fyOptions'
        ));
    }

    // Update a manually-set target value (razor_blade_target, payment_target, etc.)
    public function updateTarget(Request $request)
    {
        $request->validate([
            'salesperson_id' => 'required|exists:users,id',
            'month_year'     => 'required|string|size:7',
            'field'          => 'required|in:bingo_target,kingo_target',
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
