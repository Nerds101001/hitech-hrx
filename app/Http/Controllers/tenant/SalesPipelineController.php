<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use App\Models\SetuSalespersonMapping;
use App\Models\User;
use App\ApiClasses\Success;
use App\ApiClasses\Error;
use App\Services\SetuReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class SalesPipelineController extends Controller
{
    /**
     * Sentinel value for the "— No X —" person filters: rows where the person
     * column is blank. $countsFor() groups by the column and so skips those
     * rows entirely, which is why the "All …" totals used to read lower than
     * the grid did.
     */
    private const FILTER_NONE = 'none';

    /**
     * Apply a person filter that also understands "unassigned".
     */
    private static function applyPersonFilter($query, string $column, $value): void
    {
        if ($value === self::FILTER_NONE) {
            $query->whereNull($column);
        } elseif (!empty($value)) {
            $query->where($column, $value);
        }
    }

    /**
     * May this user change anything on the Sales Report / Sales Pipeline book?
     *
     * Plain salespeople are READ-ONLY on both pages by decision of 2026-08-05:
     * the book is maintained by CCare / New Biz, and a salesperson editing
     * their own rows was producing conflicting figures on shared customers.
     * They keep full visibility — this gates writes only.
     *
     * Note this deliberately does NOT accept "is the salesperson on this row",
     * which is exactly the clause that used to let them through.
     */
    private static function canEditBook($user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR', 'manager', 'Manager'])
            || $user->isCcareAgent()
            || $user->isNewBizAgent()
            || $user->can('sales_report.manage');
    }

    /**
     * The real product brands and the spellings they appear under in the
     * free-text `product` column, used by the Brand filter.
     */
    private const BRANDS = [
        'Rust-X'     => ['rust-x', 'rustx', 'rust x'],
        'Dr. Bio'    => ['dr.bio', 'dr. bio', 'dr bio', 'drbio'],
        'Fillezy'    => ['fillezy', 'filezy'],
        'KIF'        => ['kif'],
        'Zorbit'     => ['zorbit'],
        'Tuffpaulin' => ['tuffpaulin', 'tuff paulin'],
        'Neutrasafe' => ['neutrasafe'],
        // 'HITECH' removed — it is the company, not a product brand. Existing
        // rows that still carry product='HITECH' keep their value and remain
        // visible; they simply no longer offer it as a standard choice.
    ];

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

        $query = SalesPipeline::with(['salesperson', 'ccare', 'newBiz', 'extraReps.user', 'serviceReps.user', 'months' => function($q) use ($months) {
            $monthKeys = array_column($months, 'key');
            $q->whereIn('month_year', $monthKeys);
        }])->where('is_locked', true);

        $user = auth()->user();
        if (!\App\Http\Middleware\SalesAccessGuard::isBroadSalesUser($user) && !$user->can('sales_report.view')) {
            abort(403);
        }
        $isCcare = $user->isCcareAgent();
        $isNewBiz = $user->isNewBizAgent();

        // Which salespersons may this user see? null = no restriction (admin/HR).
        // manager = self + direct-report team; ccare/newbiz = self + mapped
        // salespersons; salesperson = self. Single source of truth in SalesScope.
        // A sales manager defaults to their OWN rows; ?team=1 shows the whole
        // team and ?sp=<id> focuses on one teammate (mirrors Kingo Bingo).
        $teamMemberIds = \App\Support\SalesScope::allowedUserIds($user); // full team (for the dropdown)
        $isManager     = $user->hasRole(['manager', 'Manager']);
        $teamView      = $request->boolean('team');
        $hasTeam       = $isManager && $teamMemberIds !== null && count($teamMemberIds) > 1;
        $teamMembers   = ($teamMemberIds !== null)
            ? User::whereIn('id', $teamMemberIds)->orderBy('first_name')->get()
            : collect();
        // Salesperson-level list (self / team / explicitly-mapped) drives the
        // filter dropdowns and per-salesperson aggregates below.
        $allowedSpIds = \App\Support\SalesScope::viewIds($user, $request, 'sp');
        // Row visibility: that list OR — for a CCare/New Biz rep — the individual
        // customer rows they are personally assigned to (ccare_id / new_biz_id).
        \App\Support\SalesScope::applyScope($query, $user, $request, [
            'sp' => 'salesperson_id', 'rep' => ['ccare_id', 'new_biz_id', 'new_biz_2_id'], 'member' => 'sp', 'extra' => true,
        ]);

        // Snapshot of what this user may see, BEFORE the filters below. The filter
        // dropdowns are built from this so the option list stays stable while
        // filtering, and every option is guaranteed to return rows.
        $optionBase = clone $query;

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
        self::applyPersonFilter($query, 'salesperson_id', $fSp);
        self::applyPersonFilter($query, 'ccare_id', $fCcare);
        $fNb = $request->input('nb');      // new_biz_id
        self::applyPersonFilter($query, 'new_biz_id', $fNb);
        // Brand filter. The Brand column is the free-text `product` field, which
        // holds things like "dr. bio - fillezy - rustx", so filtering on exact
        // values would offer hundreds of one-off options. Match the real brands
        // by their spelling variants instead.
        $fBrand = trim((string) $request->input('brand', ''));
        if ($fBrand !== '' && isset(self::BRANDS[$fBrand])) {
            $query->where(function ($w) use ($fBrand) {
                foreach (self::BRANDS[$fBrand] as $alias) {
                    $w->orWhere('product', 'like', '%' . $alias . '%');
                }
            });
        }
        // Category filter — mirrors the Category column: "New Biz" when a New Biz
        // rep is set, "CCare" when only a CCare rep is set, "None" when neither.
        $fCat = trim((string) $request->input('cat', ''));
        if ($fCat === 'New Biz') {
            $query->whereNotNull('new_biz_id');
        } elseif ($fCat === 'CCare') {
            $query->whereNull('new_biz_id')->whereNotNull('ccare_id');
        } elseif ($fCat === 'None') {
            $query->whereNull('new_biz_id')->whereNull('ccare_id');
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

        // ── Column sorting (server-side, across ALL pages — the header carets).
        //    sort: party | type | category | rep | salesperson | brand |
        //          potential | forecast | sale | pending | month:YYYY-MM ──
        $sort = (string) $request->input('sort', '');
        $dir  = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $spNameSub  = "(SELECT TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) FROM users u WHERE u.id = sales_pipelines.salesperson_id)";
        $repNameSub = "COALESCE((SELECT TRIM(CONCAT(un.first_name,' ',COALESCE(un.last_name,''))) FROM users un WHERE un.id = sales_pipelines.new_biz_id),"
                    . "(SELECT TRIM(CONCAT(uc.first_name,' ',COALESCE(uc.last_name,''))) FROM users uc WHERE uc.id = sales_pipelines.ccare_id), '')";
        // Text sorts keep empty values at the BOTTOM in either direction, so an
        // A→Z click starts at the A's, not at the blank rows.
        $textSort = function ($expr) use ($query, $dir) {
            $query->orderByRaw("CASE WHEN COALESCE($expr,'') = '' THEN 1 ELSE 0 END, COALESCE($expr,'') $dir");
        };
        if ($sort === 'party') {
            $textSort('party_name');
        } elseif ($sort === 'type') {
            $textSort('type');
        } elseif ($sort === 'brand') {
            $textSort('product');
        } elseif ($sort === 'category') {
            $textSort("CASE WHEN new_biz_id IS NOT NULL THEN 'New Biz' WHEN ccare_id IS NOT NULL THEN 'CCare' ELSE '' END");
        } elseif ($sort === 'rep') {
            $textSort($repNameSub);
        } elseif ($sort === 'salesperson') {
            $textSort($spNameSub);
        } elseif ($sort === 'potential') {
            $query->orderBy('total_business_potential', $dir);
        } elseif (in_array($sort, ['forecast', 'sale', 'pending']) || str_starts_with($sort, 'month:')) {
            // Whitelisted field + validated month key, so the raw subquery is safe.
            $field = ['forecast' => 'forecast_amount', 'sale' => 'sale_amount', 'pending' => 'pending_amount'][$sort] ?? 'sale_amount';
            $mk = str_starts_with($sort, 'month:') ? substr($sort, 6) : $currentMonthKey;
            if (preg_match('/^\d{4}-\d{2}$/', $mk)) {
                $query->orderByRaw(
                    "COALESCE((SELECT m.$field FROM sales_pipeline_months m WHERE m.sales_pipeline_id = sales_pipelines.id AND m.month_year = ? LIMIT 1), 0) $dir",
                    [$mk]
                );
            }
        }

        // ── Paginate — selectable 50/100/200/500/1000/All (minimum 50) ──
        $perInput = $request->input('per', 50);
        if ($perInput === 'all') {
            $perPage = max(1, (clone $query)->count());
        } else {
            $perPage = (int) $perInput;
            if (!in_array($perPage, [50, 100, 200, 500, 1000])) $perPage = 50;
        }
        $pipelines = $query->orderBy('party_name')->paginate($perPage)->withQueryString();

        $assignedUsers = ($allowedSpIds !== null)
            ? User::where('status', 'active')->whereIn('id', $allowedSpIds)->get()
            : User::where('status', 'active')->get();

        // Department-scoped lists for the assignment dropdowns: the Salesperson
        // dropdown must only show Sales-department people, New Biz only New Biz.
        // Sales-department people, PLUS anyone who already owns a row. A saved
        // salesperson with no matching option makes the dropdown fall back to
        // its placeholder, so the row looks unassigned when it is not: staff
        // still on 'onboarding' status, or in a differently-named department,
        // were showing blank on ~380 pipeline rows.
        $salespersons = User::where(function ($q) {
                $q->where(function ($w) {
                    $w->whereIn('status', ['active', 'onboarding', 'onboarding_submitted'])
                      ->whereHas('department', fn($d) => $d->where('name', 'like', '%Sales%')
                          ->orWhere('name', 'like', '%Export%'));
                })->orWhereIn('id', SalesPipeline::whereNotNull('salesperson_id')
                    ->distinct()->pluck('salesperson_id'));
            })
            ->notTerminated()->orderBy('first_name')->get();
        // Same union as the salesperson pool: department membership OR already
        // tagged on a customer. Jasbir Kaur does New Biz work from the Customer
        // Care department, so a department-only list left her out and all 355 of
        // her rows rendered the "-NB-" placeholder instead of her name.
        $newBizUsers = User::where(function ($q) {
                $q->whereHas('department', fn($d) => $d->where('name', 'like', '%New Biz%')
                        ->orWhere('name', 'like', '%New Business%'))
                  ->orWhereIn('id', SalesPipeline::whereNotNull('new_biz_id')
                        ->distinct()->pluck('new_biz_id'));
            })
            ->notTerminated()->orderBy('first_name')->get();
        // CCare agent pool for the add-form dropdown (previously every active
        // employee was rendered there — ~190 options of dead page weight).
        $ccareUsers = User::where(function ($q) {
                $q->whereHas('department', fn($d) => $d->where('name', 'CCARE')
                        ->orWhere('name', 'like', '%Customer Care%'))
                  ->orWhereIn('id', SalesPipeline::whereNotNull('ccare_id')
                        ->distinct()->pluck('ccare_id'));
            })
            ->notTerminated()->orderBy('first_name')->get();

        // Filter dropdown option lists — the FULL directory of salespersons,
        // CCare and New Biz agents (not just people on visible rows): a rep can
        // hold one-off customers under a salesperson who isn't linked to them,
        // and filtering by that salesperson must be possible. Row visibility
        // itself stays scoped, so nothing leaks — an out-of-scope pick simply
        // returns only the rows the user may see.
        // Filter dropdowns list ONLY people who actually hold customers on this
        // page, each with their count. Building them from the department instead
        // was the cause of endless empty results: someone could sit in the CCare
        // department while all their customers are tagged New Biz (or live in the
        // Pipeline book, not this one), so picking them returned nothing with no
        // explanation. Counts come from $optionBase, so they respect this user's
        // own visibility — a rep sees their own numbers, admin/HR see everything.
        $countsFor = function (string $column) use ($optionBase) {
            return (clone $optionBase)->whereNotNull($column)
                ->selectRaw("$column as uid, COUNT(*) as c")
                ->groupBy($column)->pluck('c', 'uid');
        };
        $spFilterCounts    = $countsFor('salesperson_id');
        $ccareFilterCounts = $countsFor('ccare_id');
        $nbFilterCounts    = $countsFor('new_biz_id');
        // Rows where the column is blank. $countsFor() groups by the column and
        // so skips these entirely, which is why "All Salespersons" showed fewer
        // than the grid did. Each becomes a "No …" option and is added back into
        // the "All" total so the two agree again.
        $noneCountFor = function (string $column) use ($optionBase) {
            return (clone $optionBase)->whereNull($column)->count();
        };
        $spNoneCount    = $noneCountFor('salesperson_id');
        $ccareNoneCount = $noneCountFor('ccare_id');
        $nbNoneCount    = $noneCountFor('new_biz_id');
        // Brand options, built from the brands actually present in what this
        // user can see, each with its row count.
        // Bucket the free-text product values into the real brands. One grouped
        // query, then counted in PHP — a row naming two brands counts for both.
        $rawProducts = (clone $optionBase)->whereNotNull('product')->where('product', '<>', '')
            ->selectRaw('product, COUNT(*) as c')->groupBy('product')->pluck('c', 'product');
        $brandCounts = collect(self::BRANDS)->map(function ($aliases) use ($rawProducts) {
            $n = 0;
            foreach ($rawProducts as $name => $c) {
                $l = strtolower((string) $name);
                foreach ($aliases as $a) {
                    if (str_contains($l, $a)) { $n += $c; break; }
                }
            }
            return $n;
        })->filter();

        // Salesperson filter = the FULL salesperson directory, not just those on
        // this user's visible rows. A New Biz rep is independent — she coordinates
        // across the whole sales org and holds one-off customers under many
        // salespersons — so every salesperson must be filterable even when she has
        // no rows tagged to them yet (they show a (0) count). Row visibility stays
        // scoped by SalesScope, so an out-of-scope pick simply returns nothing new.
        // CCare / New Biz filters stay scoped to people who actually hold rows here.
        $salespersonFilterUsers = $salespersons;
        $ccareFilterUsers       = User::whereIn('id', $ccareFilterCounts->keys()->all())->orderBy('first_name')->get();
        // New Biz filter = the FULL New Biz directory (New Biz dept + the combined
        // "Customer Care + New Biz" dept + anyone already tagged on a new_biz row),
        // NOT just people who hold rows in the selected FY. A rep like Dipika Das
        // whose customers all live in an earlier FY was vanishing from the filter
        // and her pipeline read blank; she now shows with a (0) count and stays
        // selectable. Row visibility stays scoped by SalesScope, so nothing leaks.
        $newBizFilterUsers      = $newBizUsers;

        // Only the FY months on screen and only the fields the page uses — the
        // full table serialized into the page was ~150 KB of dead weight.
        $salespersonTargets = \App\Models\SalespersonTarget::whereIn('month_year', array_column($months, 'key'))
            ->get(['salesperson_id', 'month_year', 'kingo_target', 'bingo_target', 'super_kingo_target']);

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

            /* The YOY cards must answer for whoever is on screen. Filtering to
               one salesperson while the cards kept showing company-wide totals
               made the two halves of the page contradict each other: the
               customer count read 114 while YOY still read the whole company.
               Narrowed ONLY when a people filter is active, so the unfiltered
               view is exactly as before. */
            $annualSpIds = null;
            if (!empty($fSp)) {
                $annualSpIds = [(int) $fSp];
            } elseif (!empty($fCcare) || !empty($fNb)) {
                // ccare / new-biz pick customers, so ask which salespeople own them
                $annualSpIds = SalesPipeline::query()
                    ->where('is_locked', true)
                    ->when(!empty($fCcare), fn ($q) => $q->where('ccare_id', $fCcare))
                    ->when(!empty($fNb), fn ($q) => $q->where('new_biz_id', $fNb))
                    ->whereNotNull('salesperson_id')
                    ->distinct()->pluck('salesperson_id')->all();
            }
            if ($annualSpIds !== null) {
                // empty list must mean zero, never "no filter"
                $annualQuery->whereIn('a.user_id', $annualSpIds ?: [0]);
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

        // ── Target achievement (per salesperson) for the popup ──
        // Compares each salesperson's actual sale (locked report rows) against
        // their Bingo / Kingo / Super-Kingo targets. All in lakhs.
        //
        // Computed for EVERY elapsed month of the FY, not just the current one,
        // so the popup's month picker switches instantly instead of costing a
        // request per month. Future months are skipped — they have targets but
        // no actuals yet, so there is nothing to achieve against. One grouped
        // query covers all months; the per-month split happens in PHP.
        $achieveMonthKeys = array_values(array_filter(
            array_column($months, 'key'),
            fn ($k) => $k <= $currentMonthKey
        ));
        $targetAchieversByMonth = [];
        $allTargets = $salespersonTargets->whereIn('month_year', $achieveMonthKeys);
        if ($allowedSpIds !== null) {
            $allTargets = $allTargets->whereIn('salesperson_id', $allowedSpIds);
        }
        if ($allTargets->isNotEmpty() && $achieveMonthKeys) {
            $actualRows = SalesPipelineMonth::query()
                ->join('sales_pipelines', 'sales_pipelines.id', '=', 'sales_pipeline_months.sales_pipeline_id')
                ->whereIn('sales_pipeline_months.month_year', $achieveMonthKeys)
                ->where('sales_pipelines.is_locked', true)
                ->whereNull('sales_pipelines.deleted_at')
                ->when($allowedSpIds !== null, fn($q) => $q->whereIn('sales_pipelines.salesperson_id', $allowedSpIds))
                ->groupBy('sales_pipelines.salesperson_id', 'sales_pipeline_months.month_year')
                ->selectRaw('sales_pipelines.salesperson_id AS sp, sales_pipeline_months.month_year AS mk, SUM(sales_pipeline_months.sale_amount) AS actual, SUM(sales_pipeline_months.pending_amount) AS pending')
                ->get();
            $actualByKey = [];
            foreach ($actualRows as $a) {
                $actualByKey[$a->mk . '|' . $a->sp] = $a;
            }
            $nameMap = User::whereIn('id', $allTargets->pluck('salesperson_id')->unique()->all())
                ->get()->mapWithKeys(fn($u) => [$u->id => $u->name]);

            foreach ($achieveMonthKeys as $mk) {
                $list = [];
                foreach ($allTargets->where('month_year', $mk) as $t) {
                    $spId = $t->salesperson_id;
                    $row  = $actualByKey[$mk . '|' . $spId] ?? null;
                    $list[] = [
                        'name'    => $nameMap[$spId] ?? ('#' . $spId),
                        'actual'  => (float) ($row->actual ?? 0),
                        'pending' => (float) ($row->pending ?? 0),
                        'bingo'   => (float) $t->bingo_target,
                        'kingo'   => (float) $t->kingo_target,
                        'super'   => (float) $t->super_kingo_target,
                    ];
                }
                usort($list, fn($a, $b) => $b['actual'] <=> $a['actual']);
                $targetAchieversByMonth[$mk] = $list;
            }
        }
        // The stat chip still reports the current month.
        $targetAchievers = $targetAchieversByMonth[$currentMonthKey] ?? [];
        $kAchieved = 0; $bAchieved = 0; $skAchieved = 0;
        foreach ($targetAchievers as $r) {
            if ($r['bingo'] > 0 && $r['actual'] >= $r['bingo']) $bAchieved++;
            if ($r['kingo'] > 0 && $r['actual'] >= $r['kingo']) $kAchieved++;
            if ($r['super'] > 0 && $r['actual'] >= $r['super']) $skAchieved++;
        }

        // Deleting a customer wipes its whole sales history, so it is
        // admin-only (plus an explicit sales_report.manage grant).
        // Single source of truth for "may this user change the book at all".
        // The view uses it to render read-only; the write endpoints enforce it.
        $canEditBook = self::canEditBook($user);

        // The trash icon raises a delete REQUEST, it does not delete anything
        // directly — approval is a separate admin/HR step and every delete is
        // audited. CCare / New Biz may raise one so reps can clear their own
        // duplicates without waiting on an admin; plain salespeople are
        // read-only (2026-08-05) so they cannot.
        $canDeleteCustomer = $canEditBook;

        // Setu Sync button target: self, or — for privileged viewers — whoever
        // the sp= filter is currently focused on. Mirrors syncSetu()'s own
        // authorization check exactly, so the button is never shown for a
        // target the endpoint would then reject.
        $syncIsPrivileged = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']) || $user->can('sales_report.manage');
        $isSalespersonForSync = !$user->hasRole(['admin', 'Admin']) && !$isCcare && !$isNewBiz;
        $syncTargetId = null;
        if ($isSalespersonForSync) {
            $syncTargetId = $user->id;
        } elseif ($syncIsPrivileged && !empty($fSp)) {
            $syncTargetId = (int) $fSp;
        }
        $syncTargetLabel = null;
        $syncMapped = false;
        if ($syncTargetId) {
            if ($syncTargetId === $user->id) {
                $syncTargetLabel = 'My';
            } else {
                $targetUser = User::find($syncTargetId);
                $syncTargetLabel = $targetUser ? trim($targetUser->first_name . ' ' . $targetUser->last_name) . "'s" : 'Their';
            }
            $syncMapped = SetuSalespersonMapping::where('user_id', $syncTargetId)
                ->where('status', SetuSalespersonMapping::STATUS_CONFIRMED)->exists();
        }

        // People who are BOTH CCare and New Biz get their own optgroup so the
        // Other Reps picker can show them once instead of twice.
        $bothUsers   = $ccareUsers->whereIn('id', $newBizUsers->pluck('id'))->values();
        $ccareOnly   = $ccareUsers->whereNotIn('id', $bothUsers->pluck('id'))->values();
        $newBizOnly  = $newBizUsers->whereNotIn('id', $bothUsers->pluck('id'))->values();

        return view('tenant.sales-pipeline.index', compact('bothUsers', 'ccareOnly', 'newBizOnly', 'canDeleteCustomer', 'canEditBook', 'pipelines', 'months', 'selectedFy', 'defaultFy', 'assignedUsers', 'salespersons', 'newBizUsers', 'isCcare', 'isNewBiz', 'totals', 'salespersonTargets', 'monthlyActuals', 'monthlyForecast', 'monthlyPending', 'monthlyTargets', 'currentMonthKey', 'totalNewClients', 'totalPendingCurrentMonth', 'totalPotential', 'annualMap', 'annualGrand', 'salespersonFilterUsers', 'ccareFilterUsers', 'newBizFilterUsers', 'spFilterCounts', 'ccareFilterCounts', 'nbFilterCounts', 'spNoneCount', 'ccareNoneCount', 'nbNoneCount', 'brandCounts', 'fBrand', 'kbByName', 'fParty', 'fType', 'fSp', 'fCcare', 'fNb', 'targetAchievers', 'targetAchieversByMonth', 'achieveMonthKeys', 'kAchieved', 'bAchieved', 'skAchieved', 'isManager', 'teamView', 'hasTeam', 'teamMembers', 'ccareUsers', 'syncTargetId', 'syncTargetLabel', 'syncMapped'));
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
        if (!\App\Http\Middleware\SalesAccessGuard::isBroadSalesUser($user) && !$user->can('sales_pipeline.view')) {
            abort(403);
        }
        $isCcare = $user->isCcareAgent();
        $isNewBiz = $user->isNewBizAgent();
        
        // Which salespersons may this user see? null = no restriction (admin/HR).
        // manager = self + direct-report team; ccare/newbiz = self + mapped
        // salespersons; salesperson = self. Single source of truth in SalesScope.
        // A sales manager defaults to their OWN rows; ?team=1 shows the whole
        // team and ?sp=<id> focuses on one teammate (mirrors Kingo Bingo).
        $teamMemberIds = \App\Support\SalesScope::allowedUserIds($user); // full team (for the dropdown)
        $isManager     = $user->hasRole(['manager', 'Manager']);
        $teamView      = $request->boolean('team');
        $hasTeam       = $isManager && $teamMemberIds !== null && count($teamMemberIds) > 1;
        $teamMembers   = ($teamMemberIds !== null)
            ? User::whereIn('id', $teamMemberIds)->orderBy('first_name')->get()
            : collect();
        // Salesperson-level list (self / team / explicitly-mapped) drives the
        // filter dropdowns and per-salesperson aggregates below.
        $allowedSpIds = \App\Support\SalesScope::viewIds($user, $request, 'sp');
        // Row visibility: that list OR — for a CCare/New Biz rep — the individual
        // customer rows they are personally assigned to (ccare_id / new_biz_id).
        \App\Support\SalesScope::applyScope($query, $user, $request, [
            'sp' => 'salesperson_id', 'rep' => ['ccare_id', 'new_biz_id'], 'member' => 'sp', 'extra' => true,
        ]);

        // Snapshot for the filter dropdowns — see the note in index().
        $optionBase = clone $query;

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
        $pipelines = $query->orderBy('party_name')->paginate(50)->withQueryString();

        $assignedUsers = ($allowedSpIds !== null)
            ? User::where('status', 'active')->whereIn('id', $allowedSpIds)->get()
            : User::where('status', 'active')->get();

        // Department-scoped lists for the assignment dropdowns: the Salesperson
        // dropdown must only show Sales-department people, New Biz only New Biz.
        // Sales-department people, PLUS anyone who already owns a row. A saved
        // salesperson with no matching option makes the dropdown fall back to
        // its placeholder, so the row looks unassigned when it is not: staff
        // still on 'onboarding' status, or in a differently-named department,
        // were showing blank on ~380 pipeline rows.
        $salespersons = User::where(function ($q) {
                $q->where(function ($w) {
                    $w->whereIn('status', ['active', 'onboarding', 'onboarding_submitted'])
                      ->whereHas('department', fn($d) => $d->where('name', 'like', '%Sales%')
                          ->orWhere('name', 'like', '%Export%'));
                })->orWhereIn('id', SalesPipeline::whereNotNull('salesperson_id')
                    ->distinct()->pluck('salesperson_id'));
            })
            ->notTerminated()->orderBy('first_name')->get();
        // Same union as the salesperson pool: department membership OR already
        // tagged on a customer. Jasbir Kaur does New Biz work from the Customer
        // Care department, so a department-only list left her out and all 355 of
        // her rows rendered the "-NB-" placeholder instead of her name.
        $newBizUsers = User::where(function ($q) {
                $q->whereHas('department', fn($d) => $d->where('name', 'like', '%New Biz%')
                        ->orWhere('name', 'like', '%New Business%'))
                  ->orWhereIn('id', SalesPipeline::whereNotNull('new_biz_id')
                        ->distinct()->pluck('new_biz_id'));
            })
            ->notTerminated()->orderBy('first_name')->get();
        // CCare agent pool for the add-form dropdown (previously every active
        // employee was rendered there — ~190 options of dead page weight).
        $ccareUsers = User::where(function ($q) {
                $q->whereHas('department', fn($d) => $d->where('name', 'CCARE')
                        ->orWhere('name', 'like', '%Customer Care%'))
                  ->orWhereIn('id', SalesPipeline::whereNotNull('ccare_id')
                        ->distinct()->pluck('ccare_id'));
            })
            ->notTerminated()->orderBy('first_name')->get();

        // Filter dropdown option lists — the FULL directory of salespersons,
        // CCare and New Biz agents (not just people on visible rows): a rep can
        // hold one-off customers under a salesperson who isn't linked to them,
        // and filtering by that salesperson must be possible. Row visibility
        // itself stays scoped, so nothing leaks — an out-of-scope pick simply
        // returns only the rows the user may see.
        // Filter dropdowns list ONLY people who actually hold customers on this
        // page, each with their count. Building them from the department instead
        // was the cause of endless empty results: someone could sit in the CCare
        // department while all their customers are tagged New Biz (or live in the
        // Pipeline book, not this one), so picking them returned nothing with no
        // explanation. Counts come from $optionBase, so they respect this user's
        // own visibility — a rep sees their own numbers, admin/HR see everything.
        $countsFor = function (string $column) use ($optionBase) {
            return (clone $optionBase)->whereNotNull($column)
                ->selectRaw("$column as uid, COUNT(*) as c")
                ->groupBy($column)->pluck('c', 'uid');
        };
        $spFilterCounts    = $countsFor('salesperson_id');
        $ccareFilterCounts = $countsFor('ccare_id');
        $nbFilterCounts    = $countsFor('new_biz_id');

        // Salesperson filter = the FULL salesperson directory, not just those on
        // this user's visible rows. A New Biz rep is independent — she coordinates
        // across the whole sales org and holds one-off customers under many
        // salespersons — so every salesperson must be filterable even when she has
        // no rows tagged to them yet (they show a (0) count). Row visibility stays
        // scoped by SalesScope, so an out-of-scope pick simply returns nothing new.
        // CCare / New Biz filters stay scoped to people who actually hold rows here.
        $salespersonFilterUsers = $salespersons;
        $ccareFilterUsers       = User::whereIn('id', $ccareFilterCounts->keys()->all())->orderBy('first_name')->get();
        // New Biz filter = the FULL New Biz directory (New Biz dept + the combined
        // "Customer Care + New Biz" dept + anyone already tagged on a new_biz row),
        // NOT just people who hold rows in the selected FY. A rep like Dipika Das
        // whose customers all live in an earlier FY was vanishing from the filter
        // and her pipeline read blank; she now shows with a (0) count and stays
        // selectable. Row visibility stays scoped by SalesScope, so nothing leaks.
        $newBizFilterUsers      = $newBizUsers;

        // Only the FY months on screen and only the fields the page uses — the
        // full table serialized into the page was ~150 KB of dead weight.
        $salespersonTargets = \App\Models\SalespersonTarget::whereIn('month_year', array_column($months, 'key'))
            ->get(['salesperson_id', 'month_year', 'kingo_target', 'bingo_target', 'super_kingo_target']);

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

        // Deleting a customer wipes its whole sales history, so it is
        // admin-only (plus an explicit sales_report.manage grant).
        // Single source of truth for "may this user change the book at all".
        // The view uses it to render read-only; the write endpoints enforce it.
        $canEditBook = self::canEditBook($user);

        // The trash icon raises a delete REQUEST, it does not delete anything
        // directly — approval is a separate admin/HR step and every delete is
        // audited. CCare / New Biz may raise one so reps can clear their own
        // duplicates without waiting on an admin; plain salespeople are
        // read-only (2026-08-05) so they cannot.
        $canDeleteCustomer = $canEditBook;

        return view('tenant.sales-pipeline.actual-pipeline', compact('canDeleteCustomer', 'canEditBook', 'pipelines', 'months', 'selectedFy', 'defaultFy', 'assignedUsers', 'salespersons', 'newBizUsers', 'isCcare', 'isNewBiz', 'totals', 'salespersonTargets', 'monthlyActuals', 'monthlyForecast', 'monthlyPending', 'monthlyTargets', 'currentMonthKey', 'totalNewClients', 'totalPendingCurrentMonth', 'totalPotential', 'annualMap', 'annualGrand', 'salespersonFilterUsers', 'newBizFilterUsers', 'spFilterCounts', 'nbFilterCounts', 'kbByName', 'fParty', 'fStage', 'fSp', 'fNewBiz', 'isManager', 'teamView', 'hasTeam', 'teamMembers', 'ccareUsers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'party_name' => 'required|string|max:255',
        ]);

        $user = auth()->user();
        // Adding a customer is book editing — read-only roles (plain
        // salespeople) must not create rows either. This endpoint previously
        // had no authorization check at all.
        if (!self::canEditBook($user)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            return redirect()->back()->with('error', 'You do not have permission to add a customer.');
        }
        $isCcareUser = $user->isCcareAgent();
        $isNewBizUser = $user->isNewBizAgent();
        $isAdmin = $user->hasRole(['admin', 'Admin', 'hr', 'manager']);

        $salespersonId = $request->salesperson_id ?: null;
        $ccareId = $request->ccare_id ?: null;
        $newBizId = $request->new_biz_id ?: null;

        // Lock the current user into their own role column so they cannot spoof
        // another person's data; admin/hr/manager may assign any of the three.
        if (!$isAdmin) {
            if ($isCcareUser) {
                $ccareId = $user->id;
            } elseif ($isNewBizUser) {
                $newBizId = $user->id;
            } else {
                $salespersonId = $user->id;
            }
        }

        // A customer belongs to ONE category at a time — CCare or New Biz, never
        // both. Anyone else genuinely involved is recorded under Other Reps.
        // 27 rows had drifted into holding both, which made the Category and
        // Representative columns disagree with the filter that found them.
        if ($ccareId && $newBizId) {
            if ($ccareId === $newBizId) {
                $newBizId = null;                 // same person in both slots
            } else {
                $extraRepFromConflict = $newBizId; // demoted to Other Reps below
                $newBizId = null;
            }
        }

        // Sales Report rows are "locked" (is_locked = true) — that is exactly
        // what the Sales Report page lists. Pipeline rows pass is_locked = 0.
        $isLocked = filter_var($request->input('is_locked', true), FILTER_VALIDATE_BOOLEAN);

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
            'is_locked'                => $isLocked,
        ]);

        // The agent displaced by the one-category rule stays visible on the
        // customer as an additional representative rather than being dropped.
        if (!empty($extraRepFromConflict)) {
            \App\Models\SalesPipelineExtraRep::firstOrCreate([
                'sales_pipeline_id' => $pipeline->id,
                'user_id'           => $extraRepFromConflict,
            ]);
        }

        // Persist any month figures entered on the add form.
        foreach ((array) $request->input('months', []) as $mk => $fields) {
            if (!preg_match('/^\d{4}-\d{2}$/', (string) $mk)) continue;
            \App\Models\SalesPipelineMonth::create([
                'sales_pipeline_id' => $pipeline->id,
                'month_year'        => $mk,
                'sale_amount'       => $fields['sale_amount'] ?? 0,
                'forecast_amount'   => $fields['forecast_amount'] ?? 0,
                'pending_amount'    => $fields['pending_amount'] ?? 0,
                'tenant_id'         => null,
            ]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'id' => $pipeline->id]);
        }

        return redirect()->back()->with('success', 'Pipeline added successfully.');
    }

    // ── Additional representatives (multi-rep customers) — audited ─────────
    public function addExtraRep(Request $request, $id)
    {
        $pipeline = SalesPipeline::findOrFail($id);
        $user = auth()->user();
        // Two different callers land here. The grid's "Other Reps / Service
        // Sales" cell is book editing, so it follows canEditBook(). The customer
        // search "Add me" claim is a separate, deliberately broader feature —
        // but only ever for the caller adding THEMSELVES, never tagging someone
        // else, so a read-only salesperson still cannot edit another person in.
        $isSelfClaim = (int) $request->input('user_id') === (int) $user->id
            && \App\Support\SalesScope::canClaimCustomer($user);
        if (!self::canEditBook($user) && !$isSelfClaim) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'type'    => 'nullable|in:other,service',
        ]);
        // `type` splits the two tag columns: other = Other Reps (CCare/New Biz),
        // service = Service Sales (salespersons). Defaults to other so any older
        // caller keeps its existing behaviour.
        \App\Models\SalesPipelineExtraRep::firstOrCreate([
            'sales_pipeline_id' => $pipeline->id,
            'user_id' => (int) $request->user_id,
            'type'    => $request->input('type', \App\Models\SalesPipelineExtraRep::TYPE_OTHER),
        ]);
        return response()->json(['success' => true]);
    }

    public function removeExtraRep($id, $userId)
    {
        $pipeline = SalesPipeline::findOrFail($id);
        $user = auth()->user();
        if (!self::canEditBook($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        // Delete via the model instance so the removal is audited.
        $q = \App\Models\SalesPipelineExtraRep::where('sales_pipeline_id', $pipeline->id)
            ->where('user_id', (int) $userId);
        // Same person may be tagged in both columns — only clear the one clicked.
        if (request()->filled('type')) {
            $q->where('type', request()->input('type'));
        }
        $q->get()->each->delete();
        return response()->json(['success' => true]);
    }

    // ── Search any customer, then claim a role on it ────────────────────────
    // Deliberately a SEPARATE query from index()'s — that one is scoped by
    // SalesScope::applyScope() on purpose; this one is not, because the whole
    // point is to find customers outside the caller's normal view. Column
    // allowlists on both methods keep the response to identity + role-holder
    // data only — never total_business_potential/yoy_*/monthly figures, so
    // this can't be used as a backdoor to browse another rep's revenue.

    public function searchCustomer(Request $request)
    {
        $user = auth()->user();
        if (!\App\Support\SalesScope::canClaimCustomer($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $request->validate(['q' => 'required|string|min:3|max:255']);
        $q = trim($request->input('q'));

        $rows = SalesPipeline::query()
            ->where('is_locked', true)
            ->where('party_name', 'like', '%' . $q . '%')
            ->with([
                'salesperson:id,first_name,last_name',
                'ccare:id,first_name,last_name',
                'newBiz:id,first_name,last_name',
                'newBiz2:id,first_name,last_name',
                'serviceReps.user:id,first_name,last_name',
            ])
            ->orderBy('party_name')
            ->limit(26)
            ->get(['id', 'party_name', 'type', 'address', 'product', 'salesperson_id', 'ccare_id', 'new_biz_id', 'new_biz_2_id']);

        $truncated = $rows->count() > 25;
        $rows = $rows->take(25);

        $person = fn ($u) => $u ? ['id' => $u->id, 'name' => trim($u->first_name . ' ' . $u->last_name)] : null;

        $results = $rows->map(function ($p) use ($person) {
            return [
                'id'          => $p->id,
                'party_name'  => $p->party_name,
                'type'        => $p->type,
                'address'     => $p->address,
                'product'     => $p->product,
                'salesperson' => $person($p->salesperson),
                'ccare'       => $person($p->ccare),
                'new_biz'     => $person($p->newBiz),
                'new_biz_2'   => $person($p->newBiz2),
                'service_reps' => $p->serviceReps->map(fn ($er) => $person($er->user))->filter()->values(),
            ];
        });

        return response()->json([
            'success'   => true,
            'query'     => $q,
            'count'     => $results->count(),
            'truncated' => $truncated,
            'results'   => $results,
        ]);
    }

    public function claim(Request $request, $id)
    {
        $user = auth()->user();
        if (!\App\Support\SalesScope::canClaimCustomer($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $request->validate(['role' => 'required|in:salesperson,ccare,new_biz,new_biz_2']);

        $pipeline = SalesPipeline::findOrFail($id);
        if (!$pipeline->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'This customer is in the Sales Pipeline book, not Sales Report. Claiming is only available for Sales Report customers.',
            ], 422);
        }

        $role = $request->input('role');
        $column = ['salesperson' => 'salesperson_id', 'ccare' => 'ccare_id', 'new_biz' => 'new_biz_id', 'new_biz_2' => 'new_biz_2_id'][$role];
        $roleLabel = ['salesperson' => 'Salesperson', 'ccare' => 'CCare', 'new_biz' => 'New Biz', 'new_biz_2' => 'New Biz 2'][$role];
        // Displaced-holder bucket: CCare/New Biz/New Biz 2 land in "Other Reps"
        // (extraReps holds CCare/New Biz people); a displaced Salesperson lands
        // in "Service Sales" (serviceReps is documented as holding salespersons).
        $displacedType = $role === 'salesperson'
            ? \App\Models\SalesPipelineExtraRep::TYPE_SERVICE
            : \App\Models\SalesPipelineExtraRep::TYPE_OTHER;

        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($pipeline, $user, $column, $role, $roleLabel, $displacedType) {
            $displaced = [];
            $preserve = function ($userId, $type) use ($pipeline, $user, &$displaced) {
                if (!$userId || $userId == $user->id) {
                    return;
                }
                \App\Models\SalesPipelineExtraRep::firstOrCreate([
                    'sales_pipeline_id' => $pipeline->id,
                    'user_id'           => $userId,
                    'type'              => $type,
                ]);
                $u = User::find($userId);
                if ($u) {
                    $displaced[] = ['id' => $u->id, 'name' => trim($u->first_name . ' ' . $u->last_name)];
                }
            };

            // Same-slot displacement (new logic — not present in update()'s
            // rep-cell picker, which overwrites a same-column holder outright).
            $preserve($pipeline->{$column}, $displacedType);

            // Cross-column exclusivity, unchanged from update()'s existing
            // "one category at a time" rule — CCare/New Biz only. Salesperson
            // and New Biz 2 are independent of this and of each other.
            if ($role === 'ccare' && $pipeline->new_biz_id) {
                $preserve($pipeline->new_biz_id, \App\Models\SalesPipelineExtraRep::TYPE_OTHER);
                $pipeline->new_biz_id = null;
            } elseif ($role === 'new_biz' && $pipeline->ccare_id) {
                $preserve($pipeline->ccare_id, \App\Models\SalesPipelineExtraRep::TYPE_OTHER);
                $pipeline->ccare_id = null;
            }

            $pipeline->{$column} = $user->id;
            SalesPipeline::$auditReason = 'Claimed via customer search — ' . $roleLabel
                . ' (was: ' . ($displaced[0]['name'] ?? 'unassigned') . ')';
            $pipeline->save();

            return $displaced;
        });

        return response()->json([
            'success'      => true,
            'message'      => "You are now {$roleLabel} for {$pipeline->party_name}.",
            'pipeline_id'  => $pipeline->id,
            'party_name'   => $pipeline->party_name,
            'role'         => $role,
            'new_holder'   => ['id' => $user->id, 'name' => trim($user->first_name . ' ' . $user->last_name)],
            'displaced'    => $result,
        ]);
    }

    public function transferHistory($id)
    {
        $user = auth()->user();
        if (!\App\Support\SalesScope::canClaimCustomer($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $pipeline = SalesPipeline::findOrFail($id);

        $ownershipCols = ['salesperson_id', 'ccare_id', 'new_biz_id', 'new_biz_2_id'];
        $roleLabels = ['salesperson_id' => 'Salesperson', 'ccare_id' => 'CCare', 'new_biz_id' => 'New Biz', 'new_biz_2_id' => 'New Biz 2'];

        $rows = \Illuminate\Support\Facades\DB::table('audits')
            ->where('auditable_type', SalesPipeline::class)
            ->where('auditable_id', $pipeline->id)
            ->where('event', 'updated')
            ->orderByDesc('created_at')
            ->get(['old_values', 'new_values', 'user_id', 'user_agent', 'created_at']);

        $userIds = collect();
        $entries = [];
        foreach ($rows as $row) {
            $old = json_decode($row->old_values ?? '{}', true) ?: [];
            $new = json_decode($row->new_values ?? '{}', true) ?: [];
            foreach ($ownershipCols as $col) {
                if (!array_key_exists($col, $new)) {
                    continue;
                }
                $fromId = $old[$col] ?? null;
                $toId = $new[$col] ?? null;
                if ($fromId == $toId) {
                    continue; // both null, or unchanged — nothing actually moved
                }
                if ($fromId) $userIds->push($fromId);
                if ($toId) $userIds->push($toId);
                if ($row->user_id) $userIds->push($row->user_id);
                $entries[] = [
                    'role' => $roleLabels[$col], 'from_id' => $fromId, 'to_id' => $toId,
                    'changed_by_id' => $row->user_id, 'reason' => $row->user_agent, 'changed_at' => $row->created_at,
                ];
            }
        }

        $names = User::whereIn('id', $userIds->unique()->filter())->get()->keyBy('id')
            ->map(fn ($u) => trim($u->first_name . ' ' . $u->last_name));

        $history = collect($entries)->map(fn ($e) => [
            'role'       => $e['role'],
            'from'       => $e['from_id'] ? ($names[$e['from_id']] ?? '#' . $e['from_id']) : 'Unassigned',
            'to'         => $e['to_id'] ? ($names[$e['to_id']] ?? '#' . $e['to_id']) : 'Unassigned',
            'changed_by' => $e['changed_by_id'] ? ($names[$e['changed_by_id']] ?? '#' . $e['changed_by_id']) : 'System',
            // Every OTHER row's user_agent is a raw browser UA string (or CLI
            // string for import/console changes) courtesy of OwenIt's default
            // resolver — not a useful "why". Only surface our own custom tag.
            'reason'     => str_starts_with((string) $e['reason'], 'Claimed via') ? $e['reason'] : null,
            'changed_at' => $e['changed_at'],
        ])->values();

        return response()->json(['success' => true, 'party_name' => $pipeline->party_name, 'history' => $history]);
    }

    /**
     * Resolve the common context for the Setu sync/resolve endpoints:
     * authorization, the confirmed mapping, and the validated sync period.
     * Returns ['response' => JsonResponse] on any failure (caller returns it
     * directly) or the resolved [target, mapping, fyLabel, fromMonth, toMonth]
     * on success. `$request->validate()` throwing propagates as Laravel's
     * standard 422 JSON, same as the masters pages.
     */
    private function setuSyncContext(Request $request, $salespersonId, SetuReportService $setu): array
    {
        $user = auth()->user();
        $isPrivileged = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']) || $user->can('sales_report.manage');
        if (!$isPrivileged && (int) $salespersonId !== $user->id) {
            return ['response' => Error::response('You can only sync your own Sales Report.', 403)];
        }

        $target = User::find($salespersonId);
        if (!$target) {
            return ['response' => Error::response('That salesperson was not found.', 404)];
        }

        $mapping = SetuSalespersonMapping::where('user_id', $target->id)
            ->where('status', SetuSalespersonMapping::STATUS_CONFIRMED)->first();
        if (!$mapping) {
            return ['response' => Error::response("{$target->first_name} isn't linked to Setu yet — an admin needs to confirm their mapping first.", 422)];
        }

        $request->validate([
            'fy' => ['nullable', Rule::in($setu->availableFiscalYears())],
            'period' => ['nullable', 'in:whole,Q1,Q2,Q3,Q4,custom'],
            'from' => ['nullable', 'date_format:Y-m'],
            'to' => ['nullable', 'date_format:Y-m', 'after_or_equal:from'],
        ]);
        $fy = $request->input('fy');
        $period = $request->input('period', 'whole');
        $from = $request->input('from');
        $to = $request->input('to');
        if ($period === 'custom') {
            $fyForBounds = $fy ?? $setu->currentFiscalYearLabel();
            [$fyStart] = $setu->quarterMonthRange($fyForBounds, 'Q1');
            [, $fyEnd] = $setu->quarterMonthRange($fyForBounds, 'Q4');
            if (!$from || !$to || $from < $fyStart || $to > $fyEnd) {
                return ['response' => Error::response("Custom range must fall within {$fyForBounds} ({$fyStart} to {$fyEnd}).", 422)];
            }
        }
        try {
            [$fyLabel, $fromMonth, $toMonth] = $setu->resolvePeriod($fy, $period, $from, $to);
        } catch (\InvalidArgumentException $e) {
            return ['response' => Error::response($e->getMessage(), 422)];
        }

        return ['response' => null, 'target' => $target, 'mapping' => $mapping,
                'fyLabel' => $fyLabel, 'fromMonth' => $fromMonth, 'toMonth' => $toMonth];
    }

    /**
     * Self-service / admin "Sync" button on the Sales Report page and the Setu
     * Sales Sync centre. Two phases via the same endpoint:
     *   - preview (default): reconciles dry (writes nothing), returns every
     *     bucket + counts so the UI can show "N figures will change" before
     *     the user commits.
     *   - apply (`apply=1`): commits blankFills + overwrites + renames through
     *     the models (so every change is audited). `reviewItems` are NEVER
     *     written here — they need per-item human decisions (see resolveSetu).
     * `overwrite: true` — Setu is the source of truth, so an EXACT-name match
     * has its differing figures corrected and its name standardised to Setu's
     * spelling. Only certainties auto-act; anything fuzzy is a review item.
     */
    public function syncSetu(Request $request, $salespersonId, SetuReportService $setu)
    {
        $ctx = $this->setuSyncContext($request, $salespersonId, $setu);
        if ($ctx['response']) {
            return $ctx['response'];
        }
        ['target' => $target, 'mapping' => $mapping, 'fyLabel' => $fyLabel, 'fromMonth' => $fromMonth, 'toMonth' => $toMonth] = $ctx;

        $apply = $request->boolean('apply');
        $result = $setu->reconcileSalesperson($target, $mapping->tally_name, live: $apply, fyLabel: $fyLabel, fromMonth: $fromMonth, toMonth: $toMonth, overwrite: true);
        if ($result['error']) {
            return Error::response($result['error']);
        }

        // Full customer roster for the target — powers the review picker's
        // "search all rows" so a sibling the fuzzy matcher missed can still be
        // chosen (a company may exist under several spellings). Only sent when
        // there is something to review, to keep the common payload small.
        $roster = [];
        if ($result['reviewItems']) {
            $roster = SalesPipeline::withTrashed()
                ->where('salesperson_id', $target->id)->where('is_locked', true)
                ->withSum('months as months_total', 'sale_amount')
                ->orderBy('party_name')
                ->get(['id', 'party_name'])
                ->map(fn($p) => ['pipeline_id' => $p->id, 'party_name' => $p->party_name, 'total' => round((float) $p->months_total, 3)])
                ->values();
        }

        return Success::response([
            'salesperson' => trim($target->first_name . ' ' . $target->last_name),
            'salespersonId' => $target->id,
            'applied' => $apply,
            'fyLabel' => $result['fyLabel'],
            'period' => $result['period'],
            'months' => $setu->fiscalYearMonths($result['fyLabel'], $fromMonth, $toMonth),
            'blankFills' => $result['blankFills'],
            'overwrites' => $result['overwrites'],
            'renames' => $result['renames'],
            'newCustomers' => $result['newCustomers'],
            'reviewItems' => $result['reviewItems'],
            'allCustomers' => $result['allCustomers'],
            'roster' => $roster,
            'counts' => [
                'blankFills' => count($result['blankFills']),
                'overwrites' => count($result['overwrites']),
                'renames' => count($result['renames']),
                'newCustomers' => count($result['newCustomers']),
                'reviewItems' => count($result['reviewItems']),
            ],
        ]);
    }

    /**
     * Apply per-item human decisions for the fuzzy/unmatched review queue
     * (the "similar match, you decide" case). Re-fetches Setu server-side for
     * the period — never trusts client-sent money — and, per resolution:
     *   - update: adopt Setu's exact name onto the chosen existing row and
     *     write its Setu figures (Setu is source of truth);
     *   - create: make a fresh is_locked customer row under Setu's exact name;
     *   - skip: nothing.
     * All writes go through the models (audited) inside one transaction.
     */
    public function resolveSetu(Request $request, $salespersonId, SetuReportService $setu)
    {
        $ctx = $this->setuSyncContext($request, $salespersonId, $setu);
        if ($ctx['response']) {
            return $ctx['response'];
        }
        ['target' => $target, 'mapping' => $mapping, 'fyLabel' => $fyLabel, 'fromMonth' => $fromMonth, 'toMonth' => $toMonth] = $ctx;

        $request->validate([
            'resolutions' => ['required', 'array', 'min:1'],
            'resolutions.*.setuCustomer' => ['required', 'string'],
            'resolutions.*.action' => ['required', 'in:update,create,skip'],
            'resolutions.*.targetPipelineId' => ['nullable', 'integer'],
        ]);
        $resolutions = $request->input('resolutions');

        $setuByCustomer = $setu->periodMonthlySales($mapping->tally_name, $fyLabel, $fromMonth, $toMonth);
        if ($setuByCustomer === null) {
            return Error::response('Setu fetch failed — see the application log.');
        }

        // tenant_id to stamp on new rows/months — match the salesperson's
        // existing book so a created customer is scoped like its siblings.
        $tenantId = SalesPipeline::withTrashed()
            ->where('salesperson_id', $target->id)->where('is_locked', true)->value('tenant_id');

        $updated = 0; $created = 0; $skipped = 0; $missing = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($resolutions, $setuByCustomer, $target, $tenantId, &$updated, &$created, &$skipped, &$missing) {
            foreach ($resolutions as $r) {
                if (($r['action'] ?? '') === 'skip') { $skipped++; continue; }

                $setuName = $r['setuCustomer'];
                $months = array_filter($setuByCustomer[$setuName] ?? []); // non-zero months for this period
                if (!$months) { $missing++; continue; } // not in Setu for this period any more — skip safely

                if ($r['action'] === 'update') {
                    $pipeline = SalesPipeline::withTrashed()
                        ->where('salesperson_id', $target->id)->where('is_locked', true)
                        ->find((int) ($r['targetPipelineId'] ?? 0));
                    if (!$pipeline) { $missing++; continue; } // invalid/foreign target — skip
                    $pipeline->party_name = $setuName; // adopt Setu's exact name
                    $pipeline->save();
                } else { // create
                    $pipeline = SalesPipeline::withTrashed()->updateOrCreate(
                        ['party_name' => $setuName, 'salesperson_id' => $target->id],
                        ['is_locked' => true, 'type' => 'Customer', 'tenant_id' => $tenantId]
                    );
                    $created++;
                }

                foreach ($months as $monthYear => $amount) {
                    SalesPipelineMonth::updateOrCreate(
                        ['sales_pipeline_id' => $pipeline->id, 'month_year' => $monthYear],
                        ['sale_amount' => $amount, 'tenant_id' => $pipeline->tenant_id]
                    );
                }
                if ($r['action'] === 'update') { $updated++; }
            }
        });

        return Success::response([
            'salesperson' => trim($target->first_name . ' ' . $target->last_name),
            'updated' => $updated,
            'created' => $created,
            'skipped' => $skipped,
            'missing' => $missing,
        ]);
    }

    public function update(Request $request, $id)
    {
        $pipeline = SalesPipeline::findOrFail($id);
        $user = auth()->user();
        $isPrivileged = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']);
        $isCcare  = $user->isCcareAgent();
        $isNewBiz = $user->isNewBizAgent();
        if (!self::canEditBook($user)) {
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

        // One category at a time. Setting a CCare agent clears the New Biz slot
        // and the reverse, so the Category and Representative columns can never
        // disagree with the filter that found the row. The person being replaced
        // keeps visibility as an additional representative.
        $displacedRep = null;
        if (array_key_exists('ccare_id', $data) && !empty($data['ccare_id'])) {
            if ($pipeline->new_biz_id && $pipeline->new_biz_id != $data['ccare_id']) {
                $displacedRep = $pipeline->new_biz_id;
            }
            $data['new_biz_id'] = null;
        } elseif (array_key_exists('new_biz_id', $data) && !empty($data['new_biz_id'])) {
            if ($pipeline->ccare_id && $pipeline->ccare_id != $data['new_biz_id']) {
                $displacedRep = $pipeline->ccare_id;
            }
            $data['ccare_id'] = null;
        }
        if ($displacedRep) {
            \App\Models\SalesPipelineExtraRep::firstOrCreate([
                'sales_pipeline_id' => $pipeline->id,
                'user_id'           => $displacedRep,
            ]);
        }

        // ── Handle Category change (Admin/HR/Manager + CCare/New Biz) ───────
        if ($request->has('category') && self::canEditBook($user)) {
            // Apply any other fields sent along with the category first — this
            // branch returns early, and they must not be silently dropped.
            if (!empty($data)) {
                $pipeline->update($data);
                $pipeline->refresh();
            }
            $newCategory = $request->input('category');

            if ($newCategory === 'CCare') {
                // Keep existing ccare_id if already set, otherwise find from CcSalespersonMap
                $ccareId = $pipeline->ccare_id;
                if (!$ccareId) {
                    $maps = \App\Models\CcSalespersonMap::where('sales_user_id', $pipeline->salesperson_id)
                        ->with(['ccUser.department'])->get();
                    foreach ($maps as $map) {
                        if (!$map->ccUser) continue;
                        if ($map->role === 'ccare') { $ccareId = $map->ccUser->id; break; }
                        if ($map->role) continue;
                        if ($map->ccUser->department) {
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
                        if (!$map->ccUser) continue;
                        if ($map->role === 'newbiz') { $newBizId = $map->ccUser->id; break; }
                        if ($map->role) continue;
                        if ($map->ccUser->department) {
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
        if (!self::canEditBook($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $monthData = SalesPipelineMonth::firstOrCreate(
            [
                'sales_pipeline_id' => $request->sales_pipeline_id,
                'month_year' => $request->month_year
            ]
        );

        $field = $request->field;
        $value = $request->value;
        // forecast/sale/pending are NOT NULL columns: clearing a cell sends an
        // empty string, and writing that as NULL threw an integrity-constraint
        // violation (a 500 on every cleared cell). An empty amount means zero.
        if ($field !== 'remarks' && ($value === null || $value === '')) {
            $value = 0;
        }
        $monthData->$field = $value;
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

        // Removing a customer destroys every monthly figure attached to it and
        // changes reported totals. It was admin-only for a while; reopened on
        // 2026-08-01 at the client's request so the sales floor can clear the
        // duplicate customers themselves. Anyone in a CCare / New Biz / sales
        // role may delete any row — every deletion is audited (party name and
        // the acting user), which is the safety net for that choice.
        $isSalesFloor = $user->isCcareAgent()
            || $user->isNewBizAgent()
            || SalesPipeline::withTrashed()->where('salesperson_id', $user->id)->exists();
        if (!$user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR', 'manager', 'Manager'])
            && !$user->can('sales_report.manage')
            && !$isSalesFloor) {
            return redirect()->back()->with('error', 'You do not have permission to delete a customer.');
        }

        $name = $pipeline->party_name;
        \Illuminate\Support\Facades\Log::warning(
            "Sales customer deleted: id={$pipeline->id} party='{$name}' by user {$user->id} ({$user->email})"
        );
        $pipeline->delete();

        return redirect()->back()->with('success', "Customer '{$name}' deleted.");
    }
}
