<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SalesPipelineController extends Controller
{
    public function index(Request $request)
    {
        // Get current financial year as default (April to March)
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        
        if ($currentMonth >= 4) {
            $fyStart = Carbon::create($currentYear, 4, 1);
            $fyEnd = Carbon::create($currentYear + 1, 3, 31);
            $defaultFy = $currentYear . '-' . ($currentYear + 1);
        } else {
            $fyStart = Carbon::create($currentYear - 1, 4, 1);
            $fyEnd = Carbon::create($currentYear, 3, 31);
            $defaultFy = ($currentYear - 1) . '-' . $currentYear;
        }

        $selectedFy = $request->input('fy', $defaultFy);
        
        // Parse selected FY
        $parts = explode('-', $selectedFy);
        if (count($parts) == 2) {
            $fyStart = Carbon::create((int)$parts[0], 4, 1);
            $fyEnd = Carbon::create((int)$parts[1], 3, 31);
        }

        // Generate the months for the selected FY, stopping at the current month
        $months = [];
        $tempDate = $fyStart->copy();
        
        $currentMonthEnd = Carbon::now()->endOfMonth();

        while ($tempDate <= $fyEnd) {
            // Stop if the month is in the future relative to the current real date
            if ($tempDate->copy()->startOfMonth() > $currentMonthEnd) {
                break;
            }
            $months[] = [
                'key' => $tempDate->format('Y-m'),
                'display' => $tempDate->format('M y')
            ];
            $tempDate->addMonth();
        }
        // Most recent month first so users see the current month without scrolling right
        $months = array_reverse($months);

        $query = SalesPipeline::with(['salesperson', 'ccare', 'newBiz', 'months' => function($q) use ($months) {
            $monthKeys = array_column($months, 'key');
            $q->whereIn('month_year', $monthKeys);
        }])->where('is_locked', true);

        $user = auth()->user();
        $isCcare = $user->department && $user->department->name === 'Customer Care';
        $isNewBiz = $user->department && $user->department->name === 'New Biz';
        
        // Which salespersons may this user see? null = no restriction — only
        // ADMIN and HR see every salesperson's data. Managers (including sales
        // managers) are scoped like anyone else: a Sales-dept manager sees only
        // their own rows, a CCare/New Biz manager sees their mapped salespersons.
        $allowedSpIds = null;
        if (!$user->hasRole(['admin', 'Admin', 'hr', 'HR'])) {
            if ($isCcare || $isNewBiz) {
                // Salespersons this rep is connected to: those explicitly mapped
                // to them in cc_salesperson_map, PLUS any whose pipeline/report
                // rows already list them as the ccare/new_biz rep. The map is not
                // reliably maintained on import, so we derive from the data too —
                // otherwise a rep with no map row (e.g. Jasleen) sees nothing.
                $mapped = \App\Models\CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id');
                $asRep = SalesPipeline::whereNull('deleted_at')
                    ->where(function ($q) use ($user) {
                        $q->where('ccare_id', $user->id)->orWhere('new_biz_id', $user->id);
                    })->distinct()->pluck('salesperson_id');
                $allowedSpIds = $mapped->merge($asRep)->filter()->unique()->values()->all();
            } else {
                $allowedSpIds = [$user->id];
            }
        }
        if ($allowedSpIds !== null) {
            $query->whereIn('salesperson_id', $allowedSpIds);
        }

        // ── Server-side filters ──
        //   Sales Report filters by Salesperson and by CCare. The blade shows the
        //   dropdowns per role (admin/HR: both; salesperson: CCare; CCare: Salesperson).
        $fParty  = trim((string) $request->input('party', ''));
        $fType   = trim((string) $request->input('type', ''));
        $fSp     = $request->input('sp');      // salesperson_id
        $fCcare  = $request->input('ccare');   // ccare_id
        if ($fParty !== '') {
            $query->where('party_name', 'like', '%' . $fParty . '%');
        }
        if ($fType !== '') {
            // "Customer" and "Direct Customer" are treated as the same type.
            if ($fType === 'Customer') {
                $query->whereRaw('LOWER(type) IN (?, ?)', ['customer', 'direct customer']);
            } else {
                $query->where('type', $fType);
            }
        }
        if (!empty($fSp)) {
            $query->where('salesperson_id', $fSp);
        }
        if (!empty($fCcare)) {
            $query->where('ccare_id', $fCcare);
        }

        // ── Aggregates over the FULL filtered set (before pagination) so the
        //    stat cards and month-totals row stay correct across all pages ──
        $currentMonthKey = \Carbon\Carbon::now()->format('Y-m');
        $monthKeys = array_column($months, 'key');
        $totalNewClients = (clone $query)->count();
        $totalPotential  = (float) (clone $query)->sum('total_business_potential');
        $monthAgg = SalesPipelineMonth::whereIn('sales_pipeline_id', (clone $query)->select('sales_pipelines.id'))
            ->whereIn('month_year', $monthKeys)
            ->selectRaw('month_year, SUM(sale_amount) AS sale, SUM(pending_amount) AS pending, SUM(forecast_amount) AS forecast')
            ->groupBy('month_year')->get()->keyBy('month_year');

        $monthlyActuals = [];
        $monthlyForecast = [];
        $monthlyPending = [];
        foreach ($months as $m) {
            $mk = $m['key'];
            $monthlyActuals[$mk]  = (float) ($monthAgg[$mk]->sale ?? 0);
            $monthlyForecast[$mk] = (float) ($monthAgg[$mk]->forecast ?? 0);
            $monthlyPending[$mk]  = (float) ($monthAgg[$mk]->pending ?? 0);
        }
        $totalPendingCurrentMonth = (float) ($monthAgg[$currentMonthKey]->pending ?? 0);

        // ── Paginate (50 per page) — only these rows are rendered ──
        $pipelines = $query->orderBy('id', 'desc')->paginate(50)->withQueryString();

        $assignedUsers = ($allowedSpIds !== null)
            ? User::where('status', 'active')->whereIn('id', $allowedSpIds)->get()
            : User::where('status', 'active')->get();

        // Department-scoped lists for the assignment dropdowns: the Salesperson
        // dropdown must only show Sales-department people, New Biz only New Biz.
        $salespersons = User::where('status', 'active')->notTerminated()
            ->whereHas('department', fn($q) => $q->where('name', 'like', '%Sales%'))
            ->orderBy('first_name')->get();
        $newBizUsers = User::where('status', 'active')->notTerminated()
            ->whereHas('department', fn($q) => $q->where('name', 'like', '%New Biz%')->orWhere('name', 'like', '%New Business%'))
            ->orderBy('first_name')->get();

        // Filter dropdown option lists (Salesperson / CCare / New Biz), built from
        // the people who actually appear on the rows this user may see. The blade
        // shows the appropriate dropdown(s) per role and page.
        $optBase = SalesPipeline::whereNull('deleted_at');
        if ($allowedSpIds !== null) {
            $optBase->whereIn('salesperson_id', $allowedSpIds);
        }
        $salespersonFilterUsers = User::whereIn('id', (clone $optBase)->whereNotNull('salesperson_id')->distinct()->pluck('salesperson_id'))->orderBy('first_name')->get();
        $ccareFilterUsers       = User::whereIn('id', (clone $optBase)->whereNotNull('ccare_id')->distinct()->pluck('ccare_id'))->orderBy('first_name')->get();
        $newBizFilterUsers      = User::whereIn('id', (clone $optBase)->whereNotNull('new_biz_id')->distinct()->pluck('new_biz_id'))->orderBy('first_name')->get();

        $salespersonTargets = \App\Models\SalespersonTarget::all();

        // Per-salesperson annual (YoY) sales — scoped to the salespersons this
        // user may see, so CCare/New Biz cards reflect their own book, not the
        // whole company. Independent of pipeline rows.
        $annualMap = [];
        $annualGrand = ['22_23' => 0.0, '23_24' => 0.0, '24_25' => 0.0, '25_26' => 0.0];
        if (\Illuminate\Support\Facades\Schema::hasTable('salesperson_annual_sales')) {
            $annualQuery = \Illuminate\Support\Facades\DB::table('salesperson_annual_sales as a')
                ->join('users as u', 'u.id', '=', 'a.user_id')
                ->select('u.first_name', 'u.last_name', 'a.fy_22_23', 'a.fy_23_24', 'a.fy_24_25', 'a.fy_25_26');
            if ($allowedSpIds !== null) {
                $annualQuery->whereIn('a.user_id', $allowedSpIds);
            }
            foreach ($annualQuery->get() as $a) {
                $key = strtolower(trim($a->first_name . ' ' . $a->last_name));
                $annualMap[$key] = [(float)$a->fy_22_23, (float)$a->fy_23_24, (float)$a->fy_24_25, (float)$a->fy_25_26];
                $annualGrand['22_23'] += $a->fy_22_23;
                $annualGrand['23_24'] += $a->fy_23_24;
                $annualGrand['24_25'] += $a->fy_24_25;
                $annualGrand['25_26'] += $a->fy_25_26;
            }
        }
        // Cards default to the user's scoped annual total; client filter narrows further.
        $totals = $annualGrand;

        // Per-salesperson Kingo/Bingo for the current month, keyed by lowercased
        // name, so the client filter can update the target chip per salesperson.
        $kbByName = [];
        foreach ($assignedUsers as $au) {
            $t = $salespersonTargets->where('salesperson_id', $au->id)->where('month_year', $currentMonthKey);
            $kbByName[strtolower(trim($au->name))] = [
                'kingo' => (float) $t->sum('kingo_target'),
                'bingo' => (float) $t->sum('bingo_target'),
            ];
        }

        $monthlyTargets = [];
        foreach ($months as $m) {
            $targetsForMonth = $salespersonTargets->where('month_year', $m['key']);
            if ($allowedSpIds !== null) {
                $targetsForMonth = $targetsForMonth->whereIn('salesperson_id', $allowedSpIds);
            }
            $monthlyTargets[$m['key']] = [
                'kingo' => $targetsForMonth->sum('kingo_target'),
                'bingo' => $targetsForMonth->sum('bingo_target'),
            ];
        }

        // ── This-month target achievement (per salesperson) for the popup ──
        // Compares each salesperson's current-month actual sale (locked report
        // rows) against their Bingo / Kingo / Super-Kingo targets. All in lakhs.
        $targetAchievers = [];
        $kAchieved = 0; $bAchieved = 0; $skAchieved = 0;
        $cmTargets = $salespersonTargets->where('month_year', $currentMonthKey);
        if ($allowedSpIds !== null) {
            $cmTargets = $cmTargets->whereIn('salesperson_id', $allowedSpIds);
        }
        if ($cmTargets->isNotEmpty()) {
            $actualByS = SalesPipelineMonth::query()
                ->join('sales_pipelines', 'sales_pipelines.id', '=', 'sales_pipeline_months.sales_pipeline_id')
                ->where('sales_pipeline_months.month_year', $currentMonthKey)
                ->where('sales_pipelines.is_locked', true)
                ->whereNull('sales_pipelines.deleted_at')
                ->when($allowedSpIds !== null, fn($q) => $q->whereIn('sales_pipelines.salesperson_id', $allowedSpIds))
                ->groupBy('sales_pipelines.salesperson_id')
                ->selectRaw('sales_pipelines.salesperson_id AS sp, SUM(sales_pipeline_months.sale_amount) AS actual')
                ->pluck('actual', 'sp');
            $nameMap = User::whereIn('id', $cmTargets->pluck('salesperson_id')->unique()->all())
                ->get()->mapWithKeys(fn($u) => [$u->id => $u->name]);
            foreach ($cmTargets as $t) {
                $spId   = $t->salesperson_id;
                $actual = (float) ($actualByS[$spId] ?? 0);
                $bingo  = (float) $t->bingo_target;
                $kingo  = (float) $t->kingo_target;
                $super  = (float) $t->super_kingo_target;
                if ($bingo > 0 && $actual >= $bingo) $bAchieved++;
                if ($kingo > 0 && $actual >= $kingo) $kAchieved++;
                if ($super > 0 && $actual >= $super) $skAchieved++;
                $targetAchievers[] = [
                    'name'   => $nameMap[$spId] ?? ('#' . $spId),
                    'actual' => $actual,
                    'bingo'  => $bingo,
                    'kingo'  => $kingo,
                    'super'  => $super,
                ];
            }
            usort($targetAchievers, fn($a, $b) => $b['actual'] <=> $a['actual']);
        }

        return view('tenant.sales-pipeline.index', compact('pipelines', 'months', 'selectedFy', 'defaultFy', 'assignedUsers', 'salespersons', 'newBizUsers', 'isCcare', 'isNewBiz', 'totals', 'salespersonTargets', 'monthlyActuals', 'monthlyForecast', 'monthlyPending', 'monthlyTargets', 'currentMonthKey', 'totalNewClients', 'totalPendingCurrentMonth', 'totalPotential', 'annualMap', 'annualGrand', 'salespersonFilterUsers', 'ccareFilterUsers', 'kbByName', 'fParty', 'fType', 'fSp', 'fCcare', 'targetAchievers', 'kAchieved', 'bAchieved', 'skAchieved'));
    }

    public function actualPipelineIndex(Request $request)
    {
        // Get current financial year as default (April to March)
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        
        if ($currentMonth >= 4) {
            $fyStart = Carbon::create($currentYear, 4, 1);
            $fyEnd = Carbon::create($currentYear + 1, 3, 31);
            $defaultFy = $currentYear . '-' . ($currentYear + 1);
        } else {
            $fyStart = Carbon::create($currentYear - 1, 4, 1);
            $fyEnd = Carbon::create($currentYear, 3, 31);
            $defaultFy = ($currentYear - 1) . '-' . $currentYear;
        }

        $selectedFy = $request->input('fy', $defaultFy);
        
        // Parse selected FY
        $parts = explode('-', $selectedFy);
        if (count($parts) == 2) {
            $fyStart = Carbon::create((int)$parts[0], 4, 1);
            $fyEnd = Carbon::create((int)$parts[1], 3, 31);
        }

        // Generate the months for the selected FY, stopping at the current month
        $months = [];
        $tempDate = $fyStart->copy();
        
        $currentMonthEnd = Carbon::now()->endOfMonth();

        while ($tempDate <= $fyEnd) {
            // Stop if the month is in the future relative to the current real date
            if ($tempDate->copy()->startOfMonth() > $currentMonthEnd) {
                break;
            }
            $months[] = [
                'key' => $tempDate->format('Y-m'),
                'display' => $tempDate->format('M y')
            ];
            $tempDate->addMonth();
        }
        // Most recent month first so users see the current month without scrolling right
        $months = array_reverse($months);

        $query = SalesPipeline::with(['salesperson', 'ccare', 'newBiz', 'months' => function($q) use ($months) {
            $monthKeys = array_column($months, 'key');
            $q->whereIn('month_year', $monthKeys);
        }])->where('is_locked', false);

        $user = auth()->user();
        $isCcare = $user->department && $user->department->name === 'Customer Care';
        $isNewBiz = $user->department && $user->department->name === 'New Biz';
        
        // Which salespersons may this user see? null = no restriction — only
        // ADMIN and HR see every salesperson's data. Managers (including sales
        // managers) are scoped like anyone else: a Sales-dept manager sees only
        // their own rows, a CCare/New Biz manager sees their mapped salespersons.
        $allowedSpIds = null;
        if (!$user->hasRole(['admin', 'Admin', 'hr', 'HR'])) {
            if ($isCcare || $isNewBiz) {
                // Salespersons this rep is connected to: those explicitly mapped
                // to them in cc_salesperson_map, PLUS any whose pipeline/report
                // rows already list them as the ccare/new_biz rep. The map is not
                // reliably maintained on import, so we derive from the data too —
                // otherwise a rep with no map row (e.g. Jasleen) sees nothing.
                $mapped = \App\Models\CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id');
                $asRep = SalesPipeline::whereNull('deleted_at')
                    ->where(function ($q) use ($user) {
                        $q->where('ccare_id', $user->id)->orWhere('new_biz_id', $user->id);
                    })->distinct()->pluck('salesperson_id');
                $allowedSpIds = $mapped->merge($asRep)->filter()->unique()->values()->all();
            } else {
                $allowedSpIds = [$user->id];
            }
        }
        if ($allowedSpIds !== null) {
            $query->whereIn('salesperson_id', $allowedSpIds);
        }

        // ── Server-side filters ──
        //   Sales Pipeline filters by Salesperson and by New Biz. The blade shows
        //   the dropdowns per role (admin/HR: both; salesperson: New Biz; New Biz: Salesperson).
        $fParty  = trim((string) $request->input('party', ''));
        $fStage  = trim((string) $request->input('stage', ''));
        $fSp     = $request->input('sp');       // salesperson_id
        $fNewBiz = $request->input('newbiz');   // new_biz_id
        if ($fParty !== '') {
            $query->where('party_name', 'like', '%' . $fParty . '%');
        }
        if ($fStage !== '') {
            $query->where('status_stage', 'like', '%' . $fStage . '%');
        }
        if (!empty($fSp)) {
            $query->where('salesperson_id', $fSp);
        }
        if (!empty($fNewBiz)) {
            $query->where('new_biz_id', $fNewBiz);
        }

        // ── Aggregates over the FULL filtered set (before pagination) ──
        $currentMonthKey = \Carbon\Carbon::now()->format('Y-m');
        $monthKeys = array_column($months, 'key');
        $totalNewClients = (clone $query)->count();
        $totalPotential  = (float) (clone $query)->sum('total_business_potential');
        $monthAgg = SalesPipelineMonth::whereIn('sales_pipeline_id', (clone $query)->select('sales_pipelines.id'))
            ->whereIn('month_year', $monthKeys)
            ->selectRaw('month_year, SUM(sale_amount) AS sale, SUM(pending_amount) AS pending, SUM(forecast_amount) AS forecast')
            ->groupBy('month_year')->get()->keyBy('month_year');

        $monthlyActuals = [];
        $monthlyForecast = [];
        $monthlyPending = [];
        foreach ($months as $m) {
            $mk = $m['key'];
            $monthlyActuals[$mk]  = (float) ($monthAgg[$mk]->sale ?? 0);
            $monthlyForecast[$mk] = (float) ($monthAgg[$mk]->forecast ?? 0);
            $monthlyPending[$mk]  = (float) ($monthAgg[$mk]->pending ?? 0);
        }
        $totalPendingCurrentMonth = (float) ($monthAgg[$currentMonthKey]->pending ?? 0);

        // ── Paginate (50 per page) ──
        $pipelines = $query->orderBy('id', 'desc')->paginate(50)->withQueryString();

        $assignedUsers = ($allowedSpIds !== null)
            ? User::where('status', 'active')->whereIn('id', $allowedSpIds)->get()
            : User::where('status', 'active')->get();

        // Department-scoped lists for the assignment dropdowns: the Salesperson
        // dropdown must only show Sales-department people, New Biz only New Biz.
        $salespersons = User::where('status', 'active')->notTerminated()
            ->whereHas('department', fn($q) => $q->where('name', 'like', '%Sales%'))
            ->orderBy('first_name')->get();
        $newBizUsers = User::where('status', 'active')->notTerminated()
            ->whereHas('department', fn($q) => $q->where('name', 'like', '%New Biz%')->orWhere('name', 'like', '%New Business%'))
            ->orderBy('first_name')->get();

        // Filter dropdown option lists (Salesperson / CCare / New Biz), built from
        // the people who actually appear on the rows this user may see. The blade
        // shows the appropriate dropdown(s) per role and page.
        $optBase = SalesPipeline::whereNull('deleted_at');
        if ($allowedSpIds !== null) {
            $optBase->whereIn('salesperson_id', $allowedSpIds);
        }
        $salespersonFilterUsers = User::whereIn('id', (clone $optBase)->whereNotNull('salesperson_id')->distinct()->pluck('salesperson_id'))->orderBy('first_name')->get();
        $ccareFilterUsers       = User::whereIn('id', (clone $optBase)->whereNotNull('ccare_id')->distinct()->pluck('ccare_id'))->orderBy('first_name')->get();
        $newBizFilterUsers      = User::whereIn('id', (clone $optBase)->whereNotNull('new_biz_id')->distinct()->pluck('new_biz_id'))->orderBy('first_name')->get();

        $salespersonTargets = \App\Models\SalespersonTarget::all();

        // Per-salesperson annual (YoY) sales — scoped to the salespersons this
        // user may see, so CCare/New Biz cards reflect their own book, not the
        // whole company. Independent of pipeline rows.
        $annualMap = [];
        $annualGrand = ['22_23' => 0.0, '23_24' => 0.0, '24_25' => 0.0, '25_26' => 0.0];
        if (\Illuminate\Support\Facades\Schema::hasTable('salesperson_annual_sales')) {
            $annualQuery = \Illuminate\Support\Facades\DB::table('salesperson_annual_sales as a')
                ->join('users as u', 'u.id', '=', 'a.user_id')
                ->select('u.first_name', 'u.last_name', 'a.fy_22_23', 'a.fy_23_24', 'a.fy_24_25', 'a.fy_25_26');
            if ($allowedSpIds !== null) {
                $annualQuery->whereIn('a.user_id', $allowedSpIds);
            }
            foreach ($annualQuery->get() as $a) {
                $key = strtolower(trim($a->first_name . ' ' . $a->last_name));
                $annualMap[$key] = [(float)$a->fy_22_23, (float)$a->fy_23_24, (float)$a->fy_24_25, (float)$a->fy_25_26];
                $annualGrand['22_23'] += $a->fy_22_23;
                $annualGrand['23_24'] += $a->fy_23_24;
                $annualGrand['24_25'] += $a->fy_24_25;
                $annualGrand['25_26'] += $a->fy_25_26;
            }
        }
        // Cards default to the user's scoped annual total; client filter narrows further.
        $totals = $annualGrand;

        // Per-salesperson Kingo/Bingo for the current month, keyed by lowercased
        // name, so the client filter can update the target chip per salesperson.
        $kbByName = [];
        foreach ($assignedUsers as $au) {
            $t = $salespersonTargets->where('salesperson_id', $au->id)->where('month_year', $currentMonthKey);
            $kbByName[strtolower(trim($au->name))] = [
                'kingo' => (float) $t->sum('kingo_target'),
                'bingo' => (float) $t->sum('bingo_target'),
            ];
        }

        $monthlyTargets = [];
        foreach ($months as $m) {
            $targetsForMonth = $salespersonTargets->where('month_year', $m['key']);
            if ($allowedSpIds !== null) {
                $targetsForMonth = $targetsForMonth->whereIn('salesperson_id', $allowedSpIds);
            }
            $monthlyTargets[$m['key']] = [
                'kingo' => $targetsForMonth->sum('kingo_target'),
                'bingo' => $targetsForMonth->sum('bingo_target'),
            ];
        }

        return view('tenant.sales-pipeline.actual-pipeline', compact('pipelines', 'months', 'selectedFy', 'defaultFy', 'assignedUsers', 'salespersons', 'newBizUsers', 'isCcare', 'isNewBiz', 'totals', 'salespersonTargets', 'monthlyActuals', 'monthlyForecast', 'monthlyPending', 'monthlyTargets', 'currentMonthKey', 'totalNewClients', 'totalPendingCurrentMonth', 'totalPotential', 'annualMap', 'annualGrand', 'salespersonFilterUsers', 'newBizFilterUsers', 'kbByName', 'fParty', 'fStage', 'fSp', 'fNewBiz'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'party_name' => 'required|string|max:255',
        ]);

        $user = auth()->user();
        $isCcareUser = $user->department && $user->department->name === 'Customer Care';
        $isNewBizUser = $user->department && $user->department->name === 'New Biz';
        $isAdmin = $user->hasRole(['admin', 'Admin', 'hr', 'manager']);

        $salespersonId = $request->salesperson_id;
        $ccareId = $request->ccare_id;
        $newBizId = $request->new_biz_id;

        // Auto-assign the current user to their role column if not admin
        if (!$isAdmin) {
            if ($isCcareUser) {
                $ccareId = $ccareId ?: $user->id;
            } elseif ($isNewBizUser) {
                $newBizId = $newBizId ?: $user->id;
            } else {
                // Salesperson
                $salespersonId = $salespersonId ?: $user->id;
            }
        }

        $pipeline = SalesPipeline::create([
            'party_name'               => $request->party_name,
            'salesperson_id'           => $salespersonId,
            'ccare_id'                 => $ccareId,
            'new_biz_id'               => $newBizId,
            'type'                     => $request->type,
            'status_stage'             => $request->status_stage ?? 'Prospect',
            'status_remarks'           => $request->status_remarks,
            'total_business_potential' => $request->total_business_potential ?? 0,
            'product'                  => $request->product,
            'address'                  => $request->address,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'id' => $pipeline->id]);
        }

        return redirect()->back()->with('success', 'Pipeline added successfully.');
    }

    public function update(Request $request, $id)
    {
        $pipeline = SalesPipeline::findOrFail($id);
        $user = auth()->user();
        $isPrivileged = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']);
        $isCcare  = $user->department && $user->department->name === 'Customer Care';
        $isNewBiz = $user->department && $user->department->name === 'New Biz';
        if (!$isPrivileged && !$isCcare && !$isNewBiz && $pipeline->salesperson_id !== $user->id) {
            if ($request->ajax()) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $data = $request->only([
            'party_name',
            'salesperson_id',
            'ccare_id',
            'new_biz_id',
            'status_stage',
            'type',
            'status_remarks',
            'total_business_potential',
            'yoy_22_23',
            'yoy_23_24',
            'yoy_24_25',
            'yoy_25_26',
            'address',
            'product'
        ]);

        if (isset($data['status_stage']) && in_array($data['status_stage'], ['Order', 'Repeat Order'])) {
            $data['is_locked'] = true;
        }

        // ── Handle Category change (Admin/HR/Manager + CCare/New Biz) ───────
        if ($request->has('category') && ($isPrivileged || $isCcare || $isNewBiz)) {
            $newCategory = $request->input('category');

            if ($newCategory === 'CCare') {
                // Keep existing ccare_id if already set, otherwise find from CcSalespersonMap
                $ccareId = $pipeline->ccare_id;
                if (!$ccareId) {
                    $maps = \App\Models\CcSalespersonMap::where('sales_user_id', $pipeline->salesperson_id)
                        ->with(['ccUser.department'])->get();
                    foreach ($maps as $map) {
                        if ($map->ccUser && $map->ccUser->department) {
                            $dept = strtolower($map->ccUser->department->name);
                            if (str_contains($dept, 'customer care') || str_contains($dept, 'c-care') || str_contains($dept, 'ccare')) {
                                $ccareId = $map->ccUser->id;
                                break;
                            }
                        }
                    }
                }
                // Directly save — bypasses mass assignment issues with null
                $pipeline->ccare_id   = $ccareId;
                $pipeline->new_biz_id = null;
                $pipeline->save();

            } elseif ($newCategory === 'New Biz') {
                // Keep existing new_biz_id if already set, otherwise find from CcSalespersonMap
                $newBizId = $pipeline->new_biz_id;
                if (!$newBizId) {
                    $maps = \App\Models\CcSalespersonMap::where('sales_user_id', $pipeline->salesperson_id)
                        ->with(['ccUser.department'])->get();
                    foreach ($maps as $map) {
                        if ($map->ccUser && $map->ccUser->department) {
                            $dept = strtolower($map->ccUser->department->name);
                            if (str_contains($dept, 'new biz') || str_contains($dept, 'newbiz')) {
                                $newBizId = $map->ccUser->id;
                                break;
                            }
                        }
                    }
                }
                $pipeline->new_biz_id = $newBizId;
                $pipeline->ccare_id   = null;
                $pipeline->save();
            }

            // Return rep name immediately without running rest of $data update
            $pipeline->refresh()->load(['ccare', 'newBiz']);
            $repName = $pipeline->newBiz->name ?? $pipeline->ccare->name ?? null;
            return response()->json(['success' => true, 'rep_name' => $repName]);
        }

        $pipeline->update($data);


        if ($request->ajax()) {
            // If category changed, return the resolved rep name so JS can update cell live
            $repName = null;
            if ($request->has('category')) {
                $pipeline->refresh()->load(['ccare', 'newBiz']);
                if ($pipeline->newBiz) {
                    $repName = $pipeline->newBiz->name;
                } elseif ($pipeline->ccare) {
                    $repName = $pipeline->ccare->name;
                }
            }
            return response()->json(['success' => true, 'rep_name' => $repName]);
        }

        return redirect()->back()->with('success', 'Pipeline updated.');
    }


    public function monthUpdate(Request $request)
    {
        $request->validate([
            'sales_pipeline_id' => 'required|exists:sales_pipelines,id',
            'month_year' => 'required|string|size:7', // YYYY-MM
            'field' => 'required|string|in:forecast_amount,sale_amount,pending_amount,remarks',
            'value' => 'nullable'
        ]);

        $pipeline = SalesPipeline::findOrFail($request->sales_pipeline_id);
        $user = auth()->user();
        $isPrivileged = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']);
        $isCcare  = $user->department && $user->department->name === 'Customer Care';
        $isNewBiz = $user->department && $user->department->name === 'New Biz';
        if (!$isPrivileged && !$isCcare && !$isNewBiz && $pipeline->salesperson_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $monthData = SalesPipelineMonth::firstOrCreate(
            [
                'sales_pipeline_id' => $request->sales_pipeline_id,
                'month_year' => $request->month_year
            ]
        );

        $field = $request->field;
        $monthData->$field = $request->value;
        $monthData->save();

        if ($field === 'sale_amount') {
            if ($pipeline->type === 'New Biz') {
                $monthsWithSales = SalesPipelineMonth::where('sales_pipeline_id', $pipeline->id)
                    ->where('sale_amount', '>', 0)
                    ->count();
                if ($monthsWithSales >= 5) {
                    $pipeline->type = 'CCare';
                    $pipeline->converted_from_newbiz = true;
                    $pipeline->save();
                }
            }
        }

        return response()->json(['success' => true, 'data' => $monthData]);
    }
    
    public function destroy($id)
    {
        $pipeline = SalesPipeline::findOrFail($id);
        $user = auth()->user();
        $isPrivileged = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']);
        $isCcare  = $user->department && $user->department->name === 'Customer Care';
        $isNewBiz = $user->department && $user->department->name === 'New Biz';
        if (!$isPrivileged && !$isCcare && !$isNewBiz && $pipeline->salesperson_id !== $user->id) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }
        $pipeline->delete();
        return redirect()->back()->with('success', 'Deleted successfully.');
    }
}
