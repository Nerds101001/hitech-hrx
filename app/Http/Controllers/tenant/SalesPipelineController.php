<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\CcSalespersonMap;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use App\Models\SetuSalespersonMapping;
use App\Models\SalesGroupMapping;
use App\Models\User;
use App\ApiClasses\Success;
use App\ApiClasses\Error;
use App\Services\SetuReportService;
use App\Services\TallyReportService;
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
     * For an unrestricted viewer (admin/hr/sales_ops.view_all) who has picked
     * one specific person via any of the role filters, widen the query to
     * every row that person touches across ALL of $roleCols — not just the
     * one column the dropdown they used implies. Picking "Ishan" as
     * Salesperson previously showed only rows where he is literally the
     * salesperson_id, missing rows where he is the CCare or New Biz rep.
     *
     * When $teamView is also on and that person has their own direct
     * reports, the same widened filter covers their whole team too — the
     * "Show Team Sales" control, letting admin see exactly what that person
     * would see themselves as a manager, without leaving their own account.
     *
     * Regular managers/CCare/New Biz picking a teammate keep their existing,
     * separately-handled behaviour ($memberPicked in the callers) — this only
     * fires for unrestricted viewers, so nothing changes for anyone else.
     *
     * @param array<int, string|int|null> $candidates raw filter values, checked in order; first valid one wins
     * @param string[] $roleCols every column a person can be tagged on for this table (e.g. salesperson_id, ccare_id)
     * @return array{isUnrestricted: bool, selectedPersonId: ?int, selectedPersonHasTeam: bool, showTeamActive: bool}
     */
    private static function applyUnrestrictedPersonOrTeamFilter($query, ?array $allowedSpIds, bool $teamView, array $candidates, array $roleCols): array
    {
        $isUnrestricted = $allowedSpIds === null;

        $selectedPersonId = null;
        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '' && $candidate !== self::FILTER_NONE && is_numeric($candidate)) {
                $selectedPersonId = (int) $candidate;
                break;
            }
        }

        $teamIds = collect();
        if ($isUnrestricted && $selectedPersonId) {
            $teamIds = User::where('reporting_to_id', $selectedPersonId)->pluck('id');
        }
        $selectedPersonHasTeam = $teamIds->isNotEmpty();
        $showTeamActive = $selectedPersonHasTeam && $teamView;

        if ($isUnrestricted && $selectedPersonId) {
            $expandedIds = $showTeamActive ? $teamIds->push($selectedPersonId)->all() : [$selectedPersonId];
            $query->where(function ($q) use ($expandedIds, $roleCols) {
                foreach ($roleCols as $i => $col) {
                    $i === 0 ? $q->whereIn($col, $expandedIds) : $q->orWhereIn($col, $expandedIds);
                }
                $q->orWhereExists(function ($s) use ($expandedIds) {
                    $s->selectRaw('1')->from('sales_pipeline_extra_reps as er')
                      ->whereColumn('er.sales_pipeline_id', 'sales_pipelines.id')
                      ->whereIn('er.user_id', $expandedIds);
                });
            });
        }

        return compact('isUnrestricted', 'selectedPersonId', 'selectedPersonHasTeam', 'showTeamActive');
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
     *
     * See canEditOwnPipelineRow() below for the narrower carve-out added on
     * top of this rule for the still-open Sales Pipeline specifically.
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
     * May this user add/edit THIS Sales Pipeline row, even where
     * canEditBook() alone would say no?
     *
     * A narrow carve-out on top of the 2026-08-05 read-only rule: a plain
     * salesperson may add a new prospect, or edit one they are personally
     * the salesperson on, as long as it is still open (not_locked). Once a
     * row is locked (status Order / Repeat Order) it has become the booked
     * business CCare / New Biz reconcile against, and canEditBook() alone
     * governs it from that point — exactly the "shared customer" scenario
     * the original decision was written to stop. $pipeline null means a
     * brand-new row (store()), which is always unlocked at creation.
     */
    private static function canEditOwnPipelineRow($user, $pipeline = null): bool
    {
        if (self::canEditBook($user)) {
            return true;
        }

        if (!\App\Http\Middleware\SalesAccessGuard::isBroadSalesUser($user)) {
            return false;
        }

        if ($pipeline === null) {
            return true;
        }

        return !$pipeline->is_locked && (int) $pipeline->salesperson_id === (int) $user->id;
    }

    /**
     * One-off reopen of closed months for the whole sales team, not only
     * Admin/HR, at the client's request. April to August 2026 had passed their
     * Sept-5 grace windows; they are open again through the end of Monday
     * 21 Sept 2026 (IST) and then lock themselves with no follow-up. The months
     * and the moment live here once: the lock check below and the Sales Report
     * view both ask this method, so they cannot disagree. Once the moment has
     * passed the constants (and this method) are dead code and safe to delete.
     */
    public const REOPEN_MONTHS = ['2026-04', '2026-05', '2026-06', '2026-07', '2026-08'];
    public const REOPEN_UNTIL  = '2026-09-21 23:59:59';   // Asia/Kolkata

    public static function isReopened(string $monthYear): bool
    {
        return in_array($monthYear, self::REOPEN_MONTHS, true)
            && \Carbon\Carbon::now()->lte(\Carbon\Carbon::parse(self::REOPEN_UNTIL, 'Asia/Kolkata'));
    }

    /**
     * One-off reopen of August and September 2026 for Nidhi Sharma (CCare,
     * user id 255) only, at the client's request on 2026-10-15 -- both had
     * already passed their grace windows (August's own team-wide reopen
     * through isReopened() above also expired 21 Sept). Self-expiring
     * through the end of Sunday 18 Oct 2026 (IST), same mechanism as
     * isReopened() but scoped to one user instead of the whole team. Dead
     * code and safe to delete once the moment has passed.
     */
    public const REOPEN_FOR_USER_ID   = 255;
    public const REOPEN_FOR_USER_MONTHS = ['2026-08', '2026-09'];
    public const REOPEN_FOR_USER_UNTIL = '2026-10-18 23:59:59';   // Asia/Kolkata

    public static function isReopenedForUser($user, string $monthYear): bool
    {
        return $user
            && (int) $user->id === self::REOPEN_FOR_USER_ID
            && in_array($monthYear, self::REOPEN_FOR_USER_MONTHS, true)
            && \Carbon\Carbon::now()->lte(\Carbon\Carbon::parse(self::REOPEN_FOR_USER_UNTIL, 'Asia/Kolkata'));
    }

    /**
     * A closed month's Sale / Pending / Forecast / Remarks figures are
     * locked to everyone except Admin/HR. "Closed" means: not the current
     * month, and not last month before the 5th of this month either (e.g.
     * August stays open through Sept 5 so month-end reconciliation has a
     * few days' grace, then only Admin/HR can touch it).
     */
    public static function isMonthLockedForUser($user, string $monthYear): bool
    {
        if ($user && $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR'])) {
            return false;
        }

        if (self::isReopened($monthYear)) {
            return false;
        }

        if (self::isReopenedForUser($user, $monthYear)) {
            return false;
        }

        $now = \Carbon\Carbon::now();

        if ($monthYear === $now->format('Y-m')) {
            return false;
        }

        $prevMonthKey = $now->copy()->subMonth()->format('Y-m');
        if ($monthYear === $prevMonthKey && $now->day <= 5) {
            return false;
        }

        return true;
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

        $query = SalesPipeline::with(['salesperson', 'ccare', 'newBiz', 'formerNewBiz', 'customer', 'extraReps.user', 'serviceReps.user', 'months' => function($q) use ($months) {
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
        // former_new_biz_id: a customer that moved to CCare stays visible to its
        // New Biz person, for the months that are still theirs.
        \App\Support\SalesScope::applyScope($query, $user, $request, [
            'sp' => 'salesperson_id', 'rep' => ['ccare_id', 'new_biz_id', 'new_biz_2_id', 'former_new_biz_id'], 'member' => 'sp', 'extra' => true,
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
        $fNb     = $request->input('nb');      // new_biz_id
        // One person, either role. Somebody can hold customers as CCare on some
        // rows and as New Biz on others - Poonam has 226 and 75 - and the two
        // dropdowns above AND together, so picking her in both returns only the
        // rows where she is somehow both, which is nearly none. Admin/HR already
        // got a widened view through applyUnrestrictedPersonOrTeamFilter(), but
        // it is invisible (the dropdown still says "CCare") and a CCare or New
        // Biz rep never got it at all. This filter says what it does and works
        // for everyone.
        $fBoth   = $request->input('both');    // ccare_id OR new_biz_id OR new_biz_2_id
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
        // Same two meanings for `sp` as on the Sales Pipeline: the filter bar
        // means "rows where this person is the SALESPERSON", the Team member
        // picker means "show me this teammate's book". The scope above has
        // already narrowed to that teammate across every role column, so
        // re-filtering on salesperson_id here emptied the page — 16 of
        // Shallu's 17 reports with rows showed zero, because they hold those
        // rows as CCare/New Biz and almost never as salesperson.
        $memberPicked = $isManager
            && !empty($fSp)
            && is_array($teamMemberIds)
            && in_array((int) $fSp, $teamMemberIds, true);

        // "Show Team Sales" — see applyUnrestrictedPersonOrTeamFilter()'s docblock.
        $personOrTeam = self::applyUnrestrictedPersonOrTeamFilter(
            $query, $allowedSpIds, $teamView,
            [$fSp, $fCcare, $fNb],
            ['salesperson_id', 'ccare_id', 'new_biz_id', 'new_biz_2_id', 'former_new_biz_id']
        );
        $isUnrestricted        = $personOrTeam['isUnrestricted'];
        $selectedPersonId      = $personOrTeam['selectedPersonId'];
        $selectedPersonHasTeam = $personOrTeam['selectedPersonHasTeam'];
        $showTeamActive        = $personOrTeam['showTeamActive'];

        if (!($isUnrestricted && $selectedPersonId)) {
            if (!$memberPicked) {
                self::applyPersonFilter($query, 'salesperson_id', $fSp);
            }
            self::applyPersonFilter($query, 'ccare_id', $fCcare);
            if (!empty($fNb) && is_numeric($fNb)) {
                // ...including customers that have since moved to CCare: their
                // months before the move are still this New Biz person's.
                $query->where(fn ($q) => $q->where('new_biz_id', $fNb)->orWhere('former_new_biz_id', $fNb));
            } else {
                self::applyPersonFilter($query, 'new_biz_id', $fNb);
            }
        }

        // Applied on its own terms, outside the block above: this one has to
        // work for a restricted rep too, which is the case the widening in
        // applyUnrestrictedPersonOrTeamFilter() never covered.
        if (!empty($fBoth) && is_numeric($fBoth)) {
            $bothId = (int) $fBoth;
            $query->where(function ($q) use ($bothId) {
                $q->where('ccare_id', $bothId)
                  ->orWhere('new_biz_id', $bothId)
                  ->orWhere('new_biz_2_id', $bothId)
                  ->orWhere('former_new_biz_id', $bothId)
                  // Extra reps count too. Without them this filter returned FEWER
                  // rows than picking the same person under "CCare" - Poonam holds
                  // 29 extra-rep rows, 13 of them on customers she is not the
                  // named CCare or New Biz rep for - and a combined filter showing
                  // less than a single-role one reads as broken.
                  ->orWhereExists(function ($s) use ($bothId) {
                      $s->selectRaw('1')->from('sales_pipeline_extra_reps as er')
                        ->whereColumn('er.sales_pipeline_id', 'sales_pipelines.id')
                        ->where('er.user_id', $bothId);
                  });
            });
        }
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
        // A customer that moved New Biz -> CCare is in both: New Biz for the
        // months before ccare_from, CCare from it (the month totals split alike).
        $fCat = trim((string) $request->input('cat', ''));
        if ($fCat === 'New Biz') {
            $firstMonthKey = min(array_column($months, 'key'));
            $query->where(fn ($q) => $q->whereNotNull('new_biz_id')->orWhere('ccare_from', '>', $firstMonthKey));
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

        // Whose months the totals count: the category picked, and each person
        // picked (or a CCare / New Biz rep's own book). Only a customer that
        // moved New Biz -> CCare has months that fall outside them.
        $monthCategory = in_array($fCat, ['New Biz', 'CCare', 'None'], true) ? $fCat : null;
        // Credit the totals to whoever is actually being viewed. $teamView is
        // just the raw `?team=1` flag on the URL — it stays true even when a
        // single person with no reports is selected (e.g. `?team=1&sp=228` for
        // a salesperson), so using it here dropped the per-person credit filter
        // and summed every role's forecast/sale/pending on their rows, not just
        // their own share. $showTeamActive is only true when that person's team
        // was actually expanded into the row scope, which is the one case where
        // no single person should be credited.
        $creditPeople = array_values(array_unique(array_map('intval', array_filter(
            $showTeamActive ? [] : [$fSp, $fCcare, $fNb, $fBoth],
            fn ($v) => is_numeric($v)
        ))));
        if (!$creditPeople && ($isCcare || $isNewBiz) && !$isManager && $allowedSpIds !== null) {
            $creditPeople = [(int) $user->id];
        }
        $monthAggQuery = SalesPipelineMonth::whereIn('sales_pipeline_id', (clone $query)->select('sales_pipelines.id'))
            ->whereIn('month_year', $monthKeys);
        if ($monthCategory) {
            SalesPipeline::whereMonthInCategory($monthAggQuery, $monthCategory);
        }
        foreach ($creditPeople as $personId) {
            SalesPipeline::whereMonthCredited($monthAggQuery, $personId);
        }
        $monthAgg = $monthAggQuery
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
        } elseif ($sort === 'order_interval') {
            $textSort('order_interval');
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

        // Counts for the combined filter. Counted per person over the rows where
        // they hold ANY of the three rep columns, and de-duplicated per row, so a
        // row where the same person is both CCare and New Biz counts once - the
        // number has to match what the grid shows after picking them.
        $bothFilterCounts = (clone $optionBase)
            ->where(function ($q) {
                $q->whereNotNull('ccare_id')->orWhereNotNull('new_biz_id')->orWhereNotNull('new_biz_2_id');
            })
            ->get(['sales_pipelines.id', 'ccare_id', 'new_biz_id', 'new_biz_2_id'])
            ->flatMap(fn ($r) => collect([$r->ccare_id, $r->new_biz_id, $r->new_biz_2_id])
                ->filter()->unique()->values()->map(fn ($uid) => (int) $uid))
            ->countBy();

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

        // Combined filter list = everyone holding rows in either role, plus the whole
        // New Biz directory so someone whose rows all sit in an earlier FY stays
        // selectable at (0) - same reasoning as the New Biz filter above.
        $bothFilterUsers = User::whereIn(
            'id',
            $bothFilterCounts->keys()->merge($newBizUsers->pluck('id'))
                ->merge($ccareFilterCounts->keys())->unique()->values()->all()
        )->orderBy('first_name')->get();

        // Only the FY months on screen and only the fields the page uses — the
        // full table serialized into the page was ~150 KB of dead weight.
        $salespersonTargets = \App\Models\SalespersonTarget::whereIn('month_year', array_column($months, 'key'))
            ->get(['id', 'salesperson_id', 'month_year', 'kingo_target', 'bingo_target', 'super_kingo_target', 'tally_sale']);

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

        // A terminated employee must never appear here, even if a target row
        // was set for them before they left and nobody cleaned it up.
        // $salespersons/$newBizUsers/$ccareUsers already exclude terminated
        // staff (they end in ->notTerminated()); this covers everyone else
        // (anyone whose target predates their departure, or whose
        // cc_salesperson_map row was never removed).
        $activeIds = $salespersons->pluck('id')
            ->merge($newBizUsers->pluck('id'))
            ->merge($ccareUsers->pluck('id'))
            ->merge(User::notTerminated()->pluck('id'))
            ->unique();

        $allTargets = $salespersonTargets->whereIn('month_year', $achieveMonthKeys)
            ->whereIn('salesperson_id', $activeIds);
        if ($allowedSpIds !== null) {
            $allTargets = $allTargets->whereIn('salesperson_id', $allowedSpIds);
        }

        // Everyone in Sales/Export/CCare/New Biz belongs in this popup, even
        // for a month nobody set a Bingo/Kingo target for, and even before
        // they have been mapped to a salesperson at all — otherwise they
        // simply never appear, rather than showing up with a real actual
        // sale (or a legitimate 0) against a blank target. Reuses the same
        // $salespersons/$newBizUsers/$ccareUsers pools the rest of this page
        // already uses, so "who counts as CCare/New Biz" stays one
        // definition instead of forking a second, narrower one here.
        $allCoordinatorIds = $salespersons->pluck('id')
            ->merge($newBizUsers->pluck('id'))
            ->merge($ccareUsers->pluck('id'))
            ->merge(
                User::whereIn('id', CcSalespersonMap::distinct()->pluck('cc_user_id'))
                    ->notTerminated()->pluck('id')
            )
            ->unique()->values();
        if ($allowedSpIds !== null) {
            $allCoordinatorIds = $allCoordinatorIds->intersect($allowedSpIds);
        }
        $existingTargetKeys = $allTargets->map(fn ($t) => $t->month_year . '|' . $t->salesperson_id)->flip();
        foreach ($achieveMonthKeys as $mk) {
            foreach ($allCoordinatorIds as $cid) {
                if (!isset($existingTargetKeys[$mk . '|' . $cid])) {
                    $allTargets->push(new \App\Models\SalespersonTarget([
                        'salesperson_id'     => $cid,
                        'month_year'         => $mk,
                        'bingo_target'       => 0,
                        'kingo_target'       => 0,
                        'super_kingo_target' => 0,
                    ]));
                }
            }
        }

        if ($allTargets->isNotEmpty() && $achieveMonthKeys) {
            // A CCare/New Biz agent never owns sales_pipelines rows under
            // their own id, so whose actual sale counts toward their target
            // is NOT "every salesperson cc_salesperson_map ever mapped them
            // to" (that over-credits — a coordinator can be mapped to a
            // salesperson's whole ACCESS scope without owning every one of
            // that salesperson's customers). It is exactly the same rule
            // KingoBingoController::index() already uses for its ScoreCard,
            // and the same rule the Sales Report's own per-salesperson totals
            // use: every sales_pipelines row where this person is tagged as
            // salesperson_id, ccare_id, new_biz_id, new_biz_2_id, or an extra
            // rep. One person can hold several of those tags on the same
            // row (common — see KingoBingoController's own comment), so this
            // has to be a single WHERE/OR + EXISTS query per person, not
            // several GROUP BYs merged together, or a row gets double-counted
            // for someone tagged on it twice.
            $targetHolderIds = $allTargets->pluck('salesperson_id')->unique()->all();
            $actualByKey = [];
            foreach ($targetHolderIds as $spId) {
                // Named distinctly from the page's own $pipelines (the main
                // customer table's paginator, set far above in this same
                // method) -- PHP has no block scope, so reusing that name
                // here silently overwrote the paginator with this plain
                // Collection, and every visit to this page fatal-errored the
                // instant the view called $pipelines->firstItem().
                $achieverPipelines = SalesPipeline::with(['months' => function ($q) use ($achieveMonthKeys) {
                        $q->whereIn('month_year', $achieveMonthKeys);
                    }])
                    ->where('is_locked', true)
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($spId) {
                        $q->where('salesperson_id', $spId)
                          ->orWhere('ccare_id', $spId)
                          ->orWhere('new_biz_id', $spId)
                          ->orWhere('new_biz_2_id', $spId)
                          ->orWhereExists(function ($s) use ($spId) {
                              $s->selectRaw('1')->from('sales_pipeline_extra_reps as er')
                                ->whereColumn('er.sales_pipeline_id', 'sales_pipelines.id')
                                ->where('er.user_id', $spId);
                          })
                          // Customers held before a leaver handover still count for the earlier months.
                          ->orWhereIn('id', \App\Models\SalesPipelineHandover::pipelineIdsHeldBefore([(int) $spId])->all());
                    })
                    ->get();
                foreach ($achieveMonthKeys as $mk) {
                    $actual = 0.0;
                    $pending = 0.0;
                    $forecast = 0.0;
                    foreach ($achieverPipelines as $p) {
                        $m = $p->months->firstWhere('month_year', $mk);
                        if ($m && $p->heldInMonth((int) $spId, $mk)) {
                            $actual   += (float) $m->sale_amount;
                            $pending  += (float) $m->pending_amount;
                            // Same population as Actual and Pending above, so the
                            // three columns describe one set of customers.
                            $forecast += (float) $m->forecast_amount;
                        }
                    }
                    $actualByKey[$mk . '|' . $spId] = (object) ['actual' => $actual, 'pending' => $pending, 'forecast' => $forecast];
                }
            }
            $nameMap = User::whereIn('id', $targetHolderIds)
                ->get()->mapWithKeys(fn($u) => [$u->id => $u->name]);

            // Category for the popup's department filter — a person's actual
            // cc_salesperson_map capacity wins over department name (that is
            // what "combined" really means: one row per capacity, same
            // department), department name is the fallback for anyone not
            // mapped at all yet.
            $deptNameMap = User::whereIn('id', $targetHolderIds)
                ->with('department')->get()
                ->mapWithKeys(fn($u) => [$u->id => strtolower(optional($u->department)->name ?? '')]);
            $roleMap = CcSalespersonMap::whereIn('cc_user_id', $targetHolderIds)
                ->get(['cc_user_id', 'role'])
                ->groupBy('cc_user_id')
                ->map(fn($rows) => $rows->pluck('role')->unique()->all());
            $categoryOf = function ($spId) use ($roleMap, $deptNameMap) {
                $roles = $roleMap[$spId] ?? [];
                $hasCcare = in_array('ccare', $roles, true);
                $hasNewBiz = in_array('newbiz', $roles, true);
                $dept = $deptNameMap[$spId] ?? '';
                if ($hasCcare && $hasNewBiz) return 'CCare & New Biz';
                if ($hasCcare || str_contains($dept, 'customer care') || $dept === 'ccare') return 'CCare';
                if ($hasNewBiz || str_contains($dept, 'new biz') || str_contains($dept, 'new business')) return 'New Biz';
                if (str_contains($dept, 'export')) return 'Export';
                return 'Salesperson';
            };

            foreach ($achieveMonthKeys as $mk) {
                $list = [];
                foreach ($allTargets->where('month_year', $mk) as $t) {
                    $spId = $t->salesperson_id;
                    $row = $actualByKey[$mk . '|' . $spId] ?? null;
                    $list[] = [
                        // Carried so callers can match a row to a person without
                        // going through the name, which is not unique here — two
                        // MANISHA KUMARIs are active, in different departments.
                        'id'       => $spId,
                        'name'     => $nameMap[$spId] ?? ('#' . $spId),
                        'category' => $categoryOf($spId),
                        'forecast' => (float) ($row->forecast ?? 0),
                        'actual'   => (float) ($row->actual ?? 0),
                        'pending'  => (float) ($row->pending ?? 0),
                        'bingo'    => (float) $t->bingo_target,
                        'kingo'    => (float) $t->kingo_target,
                        'super'    => (float) $t->super_kingo_target,
                        // Null, not 0: "nobody has entered this yet" and "entered
                        // as zero" are different things and the cell shows them
                        // differently. A synthesised row (no saved target) has no
                        // id, so the cell renders read-only until one exists.
                        'tally'       => $t->tally_sale === null ? null : (float) $t->tally_sale,
                        'target_id'   => $t->id,
                        'month_key'   => $mk,
                    ];
                }
                usort($list, fn($a, $b) => $b['actual'] <=> $a['actual']);
                $targetAchieversByMonth[$mk] = $list;
            }
        }
        // The stat chip still reports the current month.
        $targetAchievers = $targetAchieversByMonth[$currentMonthKey] ?? [];

        // The chip counts only the person being filtered on, when there is one.
        // It used to count every salesperson regardless, so filtering the page
        // down to one rep still read "B 6  K 3  SK 1" — the whole company's
        // figures sitting on top of that one person's rows, which reads as this
        // rep having achieved all of it. The modal behind the chip still lists
        // everyone: that is a company-wide league table by design.
        $chipRows = $targetAchievers;
        $chipPersonId = null;
        foreach ([$fSp, $fCcare, $fNb, $fBoth ?? null] as $candidate) {
            if ($candidate !== null && $candidate !== '' && $candidate !== self::FILTER_NONE && is_numeric($candidate)) {
                $chipPersonId = (int) $candidate;
                break;
            }
        }
        if ($chipPersonId) {
            $chipRows = array_values(array_filter(
                $targetAchievers,
                fn ($r) => (int) ($r['id'] ?? 0) === $chipPersonId
            ));
        }

        $kAchieved = 0; $bAchieved = 0; $skAchieved = 0;
        foreach ($chipRows as $r) {
            if ($r['bingo'] > 0 && $r['actual'] >= $r['bingo']) $bAchieved++;
            if ($r['kingo'] > 0 && $r['actual'] >= $r['kingo']) $kAchieved++;
            if ($r['super'] > 0 && $r['actual'] >= $r['super']) $skAchieved++;
        }

        // Deleting a customer wipes its whole sales history, so it is
        // admin-only (plus an explicit sales_report.manage grant).
        // Single source of truth for "may this user change the book at all".
        // The view uses it to render read-only; the write endpoints enforce it.
        $canEditBook = self::canEditBook($user);

        // A plain salesperson (broad sales user, not canEditBook) may still
        // fill in the Forecast figure on their own open row -- mirrors
        // canEditOwnPipelineRow()'s carve-out so the view can unlock just
        // that one cell instead of the whole grid.
        $canEditOwnRow = $canEditBook || \App\Http\Middleware\SalesAccessGuard::isBroadSalesUser($user);

        // Only Admin/HR may correct a closed month -- see isMonthLockedForUser().
        $isAdminOrHr = $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR']);

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

        return view('tenant.sales-pipeline.index', compact('bothUsers', 'ccareOnly', 'newBizOnly', 'canDeleteCustomer', 'canEditBook', 'canEditOwnRow', 'isAdminOrHr', 'pipelines', 'months', 'selectedFy', 'defaultFy', 'assignedUsers', 'salespersons', 'newBizUsers', 'isCcare', 'isNewBiz', 'totals', 'salespersonTargets', 'monthlyActuals', 'monthlyForecast', 'monthlyPending', 'monthlyTargets', 'currentMonthKey', 'totalNewClients', 'totalPendingCurrentMonth', 'totalPotential', 'annualMap', 'annualGrand', 'salespersonFilterUsers', 'ccareFilterUsers', 'newBizFilterUsers', 'spFilterCounts', 'ccareFilterCounts', 'nbFilterCounts', 'spNoneCount', 'ccareNoneCount', 'nbNoneCount', 'brandCounts', 'fBrand', 'kbByName', 'fParty', 'fType', 'fSp', 'fCcare', 'fNb', 'fBoth', 'bothFilterUsers', 'bothFilterCounts', 'targetAchievers', 'targetAchieversByMonth', 'achieveMonthKeys', 'kAchieved', 'bAchieved', 'skAchieved', 'isManager', 'teamView', 'hasTeam', 'teamMembers', 'ccareUsers', 'syncTargetId', 'syncTargetLabel', 'syncMapped', 'isUnrestricted', 'selectedPersonId', 'selectedPersonHasTeam', 'showTeamActive', 'monthCategory', 'creditPeople'));
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
        // Pipeline visibility now matches the Sales Report by explicit product
        // decision (2026-09-18): a CCare/New Biz rep sees the COMPLETE book of
        // any salesperson mapped to them in cc_salesperson_map, and for every
        // other salesperson only the individual rows personally tagged to them
        // (ccare_id / new_biz_id = them). This used to be 'personal' => true
        // (tagged rows only, even for a mapped salesperson) because an
        // unrestricted view mixed a rep's own prospects together with every
        // other rep's prospects across ALL of their several mapped
        // salespeople at once. Reusing the Report's own scope call avoids
        // that: allowedUserIds() only ever merges in salespeople explicitly
        // mapped to this rep, so a mapped salesperson's book is exactly the
        // one they coordinate, and an unmapped salesperson still falls back
        // to the ccare_id/new_biz_id row-level match.
        \App\Support\SalesScope::applyScope($query, $user, $request, [
            'sp' => 'salesperson_id', 'rep' => ['ccare_id', 'new_biz_id'],
            'member' => 'sp', 'extra' => true,
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
        // `sp` carries two different meanings on this page: the filter bar uses
        // it as "rows where this person is the SALESPERSON", while the Team
        // member picker means "show me this teammate's pipeline". For a manager
        // picking a teammate the scope above has already narrowed to that person
        // across all three role columns, so re-filtering on salesperson_id here
        // wiped the result: every one of Shallu's 20 reports is CCare/New Biz
        // and owns zero rows AS salesperson, so choosing any of them returned 0.
        $memberPicked = $isManager
            && !empty($fSp)
            && is_array($teamMemberIds)
            && in_array((int) $fSp, $teamMemberIds, true);

        // "Show Team Sales" — see applyUnrestrictedPersonOrTeamFilter()'s docblock.
        // No CCare filter dropdown on this page, but CCare stays a valid role
        // column since a rep can still be tagged ccare_id on a pipeline row.
        $personOrTeam = self::applyUnrestrictedPersonOrTeamFilter(
            $query, $allowedSpIds, $teamView,
            [$fSp, $fNewBiz],
            ['salesperson_id', 'ccare_id', 'new_biz_id']
        );
        $isUnrestricted        = $personOrTeam['isUnrestricted'];
        $selectedPersonId      = $personOrTeam['selectedPersonId'];
        $selectedPersonHasTeam = $personOrTeam['selectedPersonHasTeam'];
        $showTeamActive        = $personOrTeam['showTeamActive'];

        if (!($isUnrestricted && $selectedPersonId)) {
            if (!empty($fSp) && !$memberPicked) {
                $query->where('salesperson_id', $fSp);
            }
            if (!empty($fNewBiz)) {
                $query->where('new_biz_id', $fNewBiz);
            }
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

        // This page only ever lists rows that are both unlocked (query
        // above filters is_locked=false) and within this user's personal
        // visibility scope -- so unlike the Sales Report page, every row a
        // plain salesperson can see here is one canEditOwnPipelineRow()
        // would let them write to. Broadening the flag the view uses to
        // decide whether to render edit controls at all keeps that in sync
        // with the write endpoints instead of showing controls that 403.
        $canEditBook = self::canEditBook($user)
            || \App\Http\Middleware\SalesAccessGuard::isBroadSalesUser($user);

        // Deletion is NOT part of this carve-out (add/edit only) -- the
        // trash icon stays gated by the strict rule regardless of this
        // page's broader edit flag above.
        $canDeleteCustomer = self::canEditBook($user);

        return view('tenant.sales-pipeline.actual-pipeline', compact('canDeleteCustomer', 'canEditBook', 'pipelines', 'months', 'selectedFy', 'defaultFy', 'assignedUsers', 'salespersons', 'newBizUsers', 'isCcare', 'isNewBiz', 'totals', 'salespersonTargets', 'monthlyActuals', 'monthlyForecast', 'monthlyPending', 'monthlyTargets', 'currentMonthKey', 'totalNewClients', 'totalPendingCurrentMonth', 'totalPotential', 'annualMap', 'annualGrand', 'salespersonFilterUsers', 'newBizFilterUsers', 'spFilterCounts', 'nbFilterCounts', 'kbByName', 'fParty', 'fStage', 'fSp', 'fNewBiz', 'isManager', 'teamView', 'hasTeam', 'teamMembers', 'ccareUsers', 'isUnrestricted', 'selectedPersonId', 'selectedPersonHasTeam', 'showTeamActive'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'party_name'             => 'required|string|max:255',
            'crm_client_mapping_id'  => 'nullable|integer|exists:crm_client_mappings,id',
        ]);

        $user = auth()->user();
        // Adding a customer is book editing. Sales staff fill in their own
        // Forecast figure and nothing else on the Sales Report -- see
        // monthUpdate() for the one write they keep there. The Sales Pipeline
        // is the exception: canEditOwnPipelineRow($user, null) is the
        // documented carve-out that lets a plain salesperson add a new
        // (always unlocked) prospect, and the Pipeline page renders the Add
        // form for them on that basis. store() was still checking the strict
        // canEditBook() alone, so they got the form and then "Unauthorized".
        $viaCarveOutOnly = !self::canEditBook($user);
        if (!self::canEditOwnPipelineRow($user)) {
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
        } elseif (!$salespersonId && !$ccareId && !$newBizId) {
            // A manager can ALSO personally be a working field salesperson
            // (e.g. an Export Department manager who carries their own
            // book) -- the branch above only auto-assigns a plain
            // salesperson, so this exact person leaving the picker blank on
            // their own "add new" got a row with every rep column null:
            // saved successfully, but invisible from their own Sales
            // Report, since nothing there is scoped to them. Only fires
            // when nobody was explicitly picked, so a manager entering
            // someone else's prospect is unaffected.
            $dept = strtolower(optional($user->department)->name ?? '');
            if ($isCcareUser) {
                $ccareId = $user->id;
            } elseif ($isNewBizUser) {
                $newBizId = $user->id;
            } elseif (str_contains($dept, 'sales') || str_contains($dept, 'export')) {
                $salespersonId = $user->id;
            }
        }

        // A customer belongs to ONE category at a time — CCare or New Biz, never
        // both. Anyone else genuinely involved is recorded under Other Reps.
        // 27 rows had drifted into holding both, which made the Category and
        // Representative columns disagree with the filter that found them.
        $extraRepFromConflict = null;
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
        // The carve-out only ever covers a new, still-open Pipeline prospect.
        // Creating a LOCKED (Sales Report) row stays a book-editor action.
        if ($viaCarveOutOnly) {
            $isLocked = false;
        }

        // Linking to an existing CRM.API customer at creation time is the
        // preferred path over the reconciliation flow below (see
        // syncCrmMapping/resolveCrmMapping) -- take the CRM's own spelling
        // of the name so the two records agree from the start.
        $partyName = $request->party_name;
        $crmClientMappingId = $request->crm_client_mapping_id ?: null;
        if ($crmClientMappingId && ($crmMapping = \App\Models\CrmClientMapping::find($crmClientMappingId))) {
            $partyName = $crmMapping->crm_company_name;
        }

        $createRow = function () use ($partyName, $crmClientMappingId, $salespersonId, $ccareId, $newBizId, $request, $isLocked, $extraRepFromConflict) {
            $pipeline = SalesPipeline::create([
                'party_name'               => $partyName,
                'crm_client_mapping_id'    => $crmClientMappingId,
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

            return $pipeline;
        };

        // A brief MySQL connection drop (common on shared hosting under load)
        // used to surface only as a bare "Could not save" with the real cause
        // never logged — the global exception reportable() also hits the DB to
        // resolve auth()->id() for its own error email, so it failed too and
        // logged THAT failure instead of the original one. One retry after a
        // reconnect, and the real exception logged either way so a repeat
        // failure is diagnosable instead of a dead end.
        $pipeline = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $pipeline = $createRow();
                break;
            } catch (\Illuminate\Database\QueryException $e) {
                $transient = in_array((string) $e->getCode(), ['2002', '2006', 'HY000', '1205', '1213', '40001'], true);
                if ($attempt === 1 && $transient) {
                    try { \Illuminate\Support\Facades\DB::reconnect(); } catch (\Throwable $ignore) {}
                    usleep(250000);
                    continue;
                }
                \Illuminate\Support\Facades\Log::error('Sales Pipeline store() failed: ' . $e->getMessage(), ['party_name' => $partyName, 'user_id' => $user->id]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Could not save — a database connection hiccup. Please try again.'], 500);
                }
                return redirect()->back()->with('error', 'Could not save — a database connection hiccup. Please try again.');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Sales Pipeline store() failed: ' . $e->getMessage(), ['party_name' => $partyName, 'user_id' => $user->id]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Could not save: ' . $e->getMessage()], 500);
                }
                return redirect()->back()->with('error', 'Could not save: ' . $e->getMessage());
            }
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
        if (!self::canEditBook($user)) {
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

        // Leaver handovers are written straight to the table (not through this model), so
        // they carry no audit row of their own: list them from the handover ledger.
        if (\Illuminate\Support\Facades\Schema::hasTable('sales_pipeline_handovers')) {
            $ledger = \Illuminate\Support\Facades\DB::table('sales_pipeline_handovers as h')
                ->join('sales_pipeline_handover_batches as b', 'b.id', '=', 'h.batch_id')
                ->where('h.sales_pipeline_id', $pipeline->id)->whereNull('h.reverted_at')
                ->get(['h.role', 'h.from_user_id', 'h.to_user_id', 'h.effective_month', 'b.created_by', 'b.created_at']);
            foreach ($ledger as $h) {
                foreach ([$h->from_user_id, $h->to_user_id, $h->created_by] as $uid) {
                    if ($uid) $userIds->push($uid);
                }
                $entries[] = [
                    'role' => $h->role === 'extra' ? 'Additional rep' : ($roleLabels[$h->role] ?? $h->role),
                    'from_id' => $h->from_user_id, 'to_id' => $h->to_user_id, 'changed_by_id' => $h->created_by,
                    'reason' => 'Claimed via leaver handover — credited to the new person from ' . $h->effective_month,
                    'changed_at' => $h->created_at,
                ];
            }
            usort($entries, fn ($a, $b) => strcmp((string) $b['changed_at'], (string) $a['changed_at']));
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
     * The Target Achievement popup, as an .xlsx — for one month.
     *
     * Recomputes rather than trusting anything from the request, and applies the
     * caller's own SalesScope, so the sheet can never contain salespeople the
     * user cannot already see on the page.
     */
    public function exportTargetAchievement(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'fy'    => ['nullable', 'string', 'max:9'],
        ]);
        $month = $request->input('month');

        // Never let a future month be exported — there are no actuals to judge.
        if ($month > now()->format('Y-m')) {
            return back()->with('error', 'That month has not started yet.');
        }

        $allowedSpIds = \App\Support\SalesScope::viewIds($user, $request, 'sp');

        $targets = \App\Models\SalespersonTarget::where('month_year', $month)
            ->when($allowedSpIds !== null, fn ($q) => $q->whereIn('salesperson_id', $allowedSpIds))
            ->get(['salesperson_id', 'kingo_target', 'bingo_target', 'super_kingo_target']);

        // Same rule as index() and KingoBingoController::index() (which this
        // must match — its own comment says so: "Matches what the Sales
        // Report shows the person for themselves"): actual/pending is every
        // sales_pipelines row this person is tagged on as salesperson_id,
        // ccare_id, new_biz_id, new_biz_2_id, or an extra rep — not every
        // salesperson cc_salesperson_map ever mapped them to.
        $targetHolderIds = $targets->pluck('salesperson_id')->unique()->all();
        $actuals = [];
        foreach ($targetHolderIds as $spId) {
            $pipelines = SalesPipeline::with(['months' => function ($q) use ($month) {
                    $q->where('month_year', $month);
                }])
                ->where('is_locked', true)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($spId) {
                    $q->where('salesperson_id', $spId)
                      ->orWhere('ccare_id', $spId)
                      ->orWhere('new_biz_id', $spId)
                      ->orWhere('new_biz_2_id', $spId)
                      ->orWhereExists(function ($s) use ($spId) {
                          $s->selectRaw('1')->from('sales_pipeline_extra_reps as er')
                            ->whereColumn('er.sales_pipeline_id', 'sales_pipelines.id')
                            ->where('er.user_id', $spId);
                      });
                })
                ->get();
            $actual = 0.0;
            $pending = 0.0;
            foreach ($pipelines as $p) {
                $m = $p->months->first();
                if ($m) {
                    $actual  += (float) $m->sale_amount;
                    $pending += (float) $m->pending_amount;
                }
            }
            $actuals[$spId] = (object) ['actual' => $actual, 'pending' => $pending];
        }

        $names = User::whereIn('id', $targetHolderIds)
            ->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);

        $rows = [];
        foreach ($targets as $t) {
            $spId = $t->salesperson_id;
            $a = $actuals[$spId] ?? null;
            $rows[] = [
                'name'    => $names[$spId] ?? ('#' . $spId),
                'actual'  => (float) ($a->actual ?? 0),
                'pending' => (float) ($a->pending ?? 0),
                'bingo'   => (float) $t->bingo_target,
                'kingo'   => (float) $t->kingo_target,
                'super'   => (float) $t->super_kingo_target,
            ];
        }
        usort($rows, fn ($x, $y) => $y['actual'] <=> $x['actual']);

        $label = \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y');
        $scope = $allowedSpIds === null ? 'All salespeople' : 'Your team';
        $file  = 'target-achievement-' . $month . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\TargetAchievementExport($rows, $label, $scope), $file
        );
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

    /**
     * Resolve the common context for the Tally sync/resolve endpoints —
     * mirrors setuSyncContext() exactly, except the identity lookup is every
     * CONFIRMED SalesGroupMapping ledger_group for this user (a salesperson
     * can own more than one ledger group) instead of a single Setu name.
     */
    private function tallySyncContext(Request $request, $salespersonId, TallyReportService $tally): array
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

        $ledgerGroups = SalesGroupMapping::where('user_id', $target->id)
            ->where('status', SalesGroupMapping::STATUS_CONFIRMED)->pluck('tally_salesperson')->all();
        if (!$ledgerGroups) {
            return ['response' => Error::response("{$target->first_name} isn't linked to a Tally sales group yet — an admin needs to confirm their mapping first.", 422)];
        }

        $request->validate([
            'fy' => ['nullable', Rule::in($tally->availableFiscalYears())],
            'period' => ['nullable', 'in:whole,Q1,Q2,Q3,Q4,custom'],
            'from' => ['nullable', 'date_format:Y-m'],
            'to' => ['nullable', 'date_format:Y-m', 'after_or_equal:from'],
        ]);
        $fy = $request->input('fy');
        $period = $request->input('period', 'whole');
        $from = $request->input('from');
        $to = $request->input('to');
        if ($period === 'custom') {
            $fyForBounds = $fy ?? $tally->currentFiscalYearLabel();
            [$fyStart] = $tally->quarterMonthRange($fyForBounds, 'Q1');
            [, $fyEnd] = $tally->quarterMonthRange($fyForBounds, 'Q4');
            if (!$from || !$to || $from < $fyStart || $to > $fyEnd) {
                return ['response' => Error::response("Custom range must fall within {$fyForBounds} ({$fyStart} to {$fyEnd}).", 422)];
            }
        }
        try {
            [$fyLabel, $fromMonth, $toMonth] = $tally->resolvePeriod($fy, $period, $from, $to);
        } catch (\InvalidArgumentException $e) {
            return ['response' => Error::response($e->getMessage(), 422)];
        }

        return ['response' => null, 'target' => $target, 'ledgerGroups' => $ledgerGroups,
                'fyLabel' => $fyLabel, 'fromMonth' => $fromMonth, 'toMonth' => $toMonth];
    }

    /**
     * A quick "what does Tally say right now" sanity check for one salesperson — current month-by-month sales
     * total and distinct customer count straight from the already-synced local Tally tables, with no Sales
     * Pipeline book comparison involved (that's what syncTally()/resolveTally() are for) and no Tally gateway
     * call (periodMonthlySales() only ever reads tally_sale_lines/tally_ledgers/tally_credit_debit_note_lines).
     * Meant to be fast enough to click repeatedly while eyeballing numbers, not a reconciliation step.
     */
    public function recheckTally(Request $request, $salespersonId, TallyReportService $tally)
    {
        // Deliberately restricted to one named account rather than a role, per explicit request -- this is a
        // brand-new, not-yet-broadly-reviewed feature, not a permissions tier like the admin/hr/accounts roles
        // used elsewhere on this page.
        if (strcasecmp((string) auth()->user()?->email, 'ai@rustx.com') !== 0) {
            return Error::response('Not available.', 403);
        }

        $ctx = $this->tallySyncContext($request, $salespersonId, $tally);
        if ($ctx['response']) {
            return $ctx['response'];
        }
        ['target' => $target, 'ledgerGroups' => $ledgerGroups, 'fyLabel' => $fyLabel, 'fromMonth' => $fromMonth, 'toMonth' => $toMonth] = $ctx;

        $byCustomer = $tally->periodMonthlySales($ledgerGroups, $fyLabel, $fromMonth, $toMonth);

        $monthTotals = [];
        $grandTotal = 0.0;
        $customersBilled = 0;
        foreach ($byCustomer as $customer => $months) {
            $customerTotal = array_sum($months);
            if (abs($customerTotal) < 0.0005) {
                continue; // a customer with every month netting to ~zero (e.g. a credit note cancelling a sale) isn't "billed"
            }
            $customersBilled++;
            foreach ($months as $monthYear => $lakhs) {
                $monthTotals[$monthYear] = ($monthTotals[$monthYear] ?? 0.0) + $lakhs;
                $grandTotal += $lakhs;
            }
        }
        ksort($monthTotals);

        return Success::response([
            'salesperson' => trim($target->first_name . ' ' . $target->last_name),
            'fyLabel' => $fyLabel,
            'period' => ($fromMonth && $toMonth)
                ? Carbon::createFromFormat('Y-m-d', $fromMonth . '-01')->format('M Y') . ' – ' . Carbon::createFromFormat('Y-m-d', $toMonth . '-01')->format('M Y')
                : "Whole Year {$fyLabel}",
            'grandTotalLakhs' => round($grandTotal, 3),
            'customersBilled' => $customersBilled,
            'monthTotalsLakhs' => array_map(fn ($v) => round($v, 3), $monthTotals),
            'checkedAt' => now()->format('d-M-Y H:i'),
        ]);
    }

    /** Tally equivalent of syncSetu() — same preview/apply two-phase shape, see that method's docblock. */
    public function syncTally(Request $request, $salespersonId, TallyReportService $tally)
    {
        $ctx = $this->tallySyncContext($request, $salespersonId, $tally);
        if ($ctx['response']) {
            return $ctx['response'];
        }
        ['target' => $target, 'ledgerGroups' => $ledgerGroups, 'fyLabel' => $fyLabel, 'fromMonth' => $fromMonth, 'toMonth' => $toMonth] = $ctx;

        $apply = $request->boolean('apply');
        $result = $tally->reconcileSalesperson($target, $ledgerGroups, live: $apply, fyLabel: $fyLabel, fromMonth: $fromMonth, toMonth: $toMonth, overwrite: true);

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
            'months' => $tally->fiscalYearMonths($result['fyLabel'], $fromMonth, $toMonth),
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

    /** Tally equivalent of resolveSetu() — same per-item update/create/skip shape, see that method's docblock. */
    public function resolveTally(Request $request, $salespersonId, TallyReportService $tally)
    {
        $ctx = $this->tallySyncContext($request, $salespersonId, $tally);
        if ($ctx['response']) {
            return $ctx['response'];
        }
        ['target' => $target, 'ledgerGroups' => $ledgerGroups, 'fyLabel' => $fyLabel, 'fromMonth' => $fromMonth, 'toMonth' => $toMonth] = $ctx;

        $request->validate([
            'resolutions' => ['required', 'array', 'min:1'],
            'resolutions.*.setuCustomer' => ['required', 'string'],
            'resolutions.*.action' => ['required', 'in:update,create,skip'],
            'resolutions.*.targetPipelineId' => ['nullable', 'integer'],
        ]);
        $resolutions = $request->input('resolutions');

        $tallyByCustomer = $tally->periodMonthlySales($ledgerGroups, $fyLabel, $fromMonth, $toMonth);

        $tenantId = SalesPipeline::withTrashed()
            ->where('salesperson_id', $target->id)->where('is_locked', true)->value('tenant_id');

        $updated = 0; $created = 0; $skipped = 0; $missing = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($resolutions, $tallyByCustomer, $target, $tenantId, &$updated, &$created, &$skipped, &$missing) {
            foreach ($resolutions as $r) {
                if (($r['action'] ?? '') === 'skip') { $skipped++; continue; }

                $tallyName = $r['setuCustomer'];
                $months = array_filter($tallyByCustomer[$tallyName] ?? []);
                if (!$months) { $missing++; continue; }

                if ($r['action'] === 'update') {
                    $pipeline = SalesPipeline::withTrashed()
                        ->where('salesperson_id', $target->id)->where('is_locked', true)
                        ->find((int) ($r['targetPipelineId'] ?? 0));
                    if (!$pipeline) { $missing++; continue; }
                    $pipeline->party_name = $tallyName;
                    $pipeline->save();
                } else {
                    $pipeline = SalesPipeline::withTrashed()->updateOrCreate(
                        ['party_name' => $tallyName, 'salesperson_id' => $target->id],
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

    /** Shared ownership check for the CRM-mapping reconciliation pair below — same rule as setuSyncContext(). */
    private function crmMappingSyncContext(Request $request, $salespersonId): array
    {
        $user = auth()->user();
        $isPrivileged = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']) || $user->can('sales_report.manage');
        if (!$isPrivileged && (int) $salespersonId !== $user->id) {
            return ['response' => Error::response('You can only reconcile your own Sales Report.', 403)];
        }

        $target = User::find($salespersonId);
        if (!$target) {
            return ['response' => Error::response('That salesperson was not found.', 404)];
        }

        return ['response' => null, 'target' => $target];
    }

    /**
     * Reconcile one salesperson's Sales Report book (sales_pipelines,
     * is_locked=1) against the CRM.API-synced customer registry
     * (CrmClientMapping) they're already tied to via any of its 5 roles —
     * so the two customer lists (freehand party_name vs CRM-code-based)
     * stop drifting apart. Mirrors syncSetu()'s preview/apply shape, but
     * unlike Setu this never calls an external API: both sides already
     * live in HRX's own DB.
     *
     * A normalised-name match at 100% is offered for auto-link (apply=1
     * commits it); anything only similar is a review item — a human
     * decides, exactly like Setu's reviewItems, since a wrong auto-link
     * would misattribute a real account's history to the wrong company.
     */
    public function syncCrmMapping(Request $request, $salespersonId)
    {
        $ctx = $this->crmMappingSyncContext($request, $salespersonId);
        if ($ctx['response']) {
            return $ctx['response'];
        }
        $target = $ctx['target'];
        $apply = $request->boolean('apply');

        $unlinked = SalesPipeline::where('salesperson_id', $target->id)
            ->where('is_locked', true)
            ->whereNull('crm_client_mapping_id')
            ->get(['id', 'party_name']);

        $candidates = \App\Models\CrmClientMapping::forUser($target->id)
            ->get(['id', 'crm_company_name'])
            ->map(fn($m) => ['label' => $m->crm_company_name, 'mapping_id' => $m->id, 'crm_company_name' => $m->crm_company_name])
            ->all();

        $autoLinked = [];
        $reviewItems = [];

        foreach ($unlinked as $pipeline) {
            $matches = \App\Support\NameMatcher::bestMatches($pipeline->party_name, $candidates, 80.0, 5);
            if (empty($matches)) {
                continue; // no plausible CRM counterpart — genuinely not there yet, leave alone
            }

            if ($matches[0]['score'] >= 99.9) {
                $autoLinked[] = [
                    'pipeline_id' => $pipeline->id,
                    'party_name' => $pipeline->party_name,
                    'mapping_id' => $matches[0]['mapping_id'],
                    'crm_company_name' => $matches[0]['crm_company_name'],
                ];
            } else {
                $reviewItems[] = [
                    'pipeline_id' => $pipeline->id,
                    'party_name' => $pipeline->party_name,
                    'candidates' => $matches,
                ];
            }
        }

        if ($apply && $autoLinked) {
            foreach ($autoLinked as $row) {
                SalesPipeline::where('id', $row['pipeline_id'])->update(['crm_client_mapping_id' => $row['mapping_id']]);
            }
        }

        return Success::response([
            'salesperson' => trim($target->first_name . ' ' . $target->last_name),
            'salespersonId' => $target->id,
            'applied' => $apply,
            'autoLinked' => $autoLinked,
            'reviewItems' => $reviewItems,
            'counts' => [
                'autoLinked' => count($autoLinked),
                'reviewItems' => count($reviewItems),
                'unlinkedTotal' => $unlinked->count(),
            ],
        ]);
    }

    /** Apply per-item human decisions from syncCrmMapping()'s reviewItems queue. */
    public function resolveCrmMapping(Request $request, $salespersonId)
    {
        $ctx = $this->crmMappingSyncContext($request, $salespersonId);
        if ($ctx['response']) {
            return $ctx['response'];
        }
        $target = $ctx['target'];

        $request->validate([
            'resolutions' => ['required', 'array', 'min:1'],
            'resolutions.*.pipeline_id' => ['required', 'integer'],
            'resolutions.*.action' => ['required', 'in:link,skip'],
            'resolutions.*.mapping_id' => ['nullable', 'integer', 'exists:crm_client_mappings,id'],
        ]);

        $linked = 0;
        $skipped = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $target, &$linked, &$skipped) {
            foreach ($request->input('resolutions') as $r) {
                if (($r['action'] ?? '') === 'skip' || empty($r['mapping_id'])) {
                    $skipped++;
                    continue;
                }
                // Re-scoped to this salesperson server-side — never trust a
                // client-sent pipeline_id to belong to whoever it claims.
                $pipeline = SalesPipeline::where('id', $r['pipeline_id'])->where('salesperson_id', $target->id)->first();
                if (!$pipeline) {
                    $skipped++;
                    continue;
                }
                $pipeline->update(['crm_client_mapping_id' => $r['mapping_id']]);
                $linked++;
            }
        });

        return Success::response(['linked' => $linked, 'skipped' => $skipped]);
    }

    public function update(Request $request, $id)
    {
        $pipeline = SalesPipeline::findOrFail($id);
        $user = auth()->user();
        $isPrivileged = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']);
        $isCcare  = $user->isCcareAgent();
        $isNewBiz = $user->isNewBizAgent();
        // fetch() does NOT send X-Requested-With, so ajax() alone is false for
        // every inline edit on the grids and this returned a 302 redirect where
        // the caller expected JSON — r.json() then threw and the row never
        // showed its saved state. expectsJson() covers the Accept header the
        // grids now send.
        $wantsJson = $request->ajax() || $request->expectsJson();

        // A plain salesperson may reach here from either the Sales Report
        // grid or the Sales Pipeline grid -- same endpoint, same model.
        // canEditOwnPipelineRow() only extends past canEditBook() when
        // $pipeline is still open (not_locked) and they are its own
        // salesperson, i.e. only ever a Sales Pipeline row. This used to
        // gate on canEditBook() alone despite the comment above, so the
        // Pipeline grid -- which renders its edit controls for plain
        // salespeople -- answered every inline edit with "Unauthorized".
        $viaCarveOutOnly = !self::canEditBook($user);
        if (!self::canEditOwnPipelineRow($user, $pipeline)) {
            if ($wantsJson) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
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
            'product',
            'order_interval'
        ]);

        // Handing a customer to a different salesperson is a transfer, not an
        // edit of their own prospect -- that stays with the book editors.
        if ($viaCarveOutOnly && array_key_exists('salesperson_id', $data)
            && (int) $data['salesperson_id'] !== (int) $pipeline->salesperson_id) {
            $message = 'Only CCare / New Biz / managers can move a customer to a different salesperson.';
            if ($wantsJson) return response()->json(['success' => false, 'message' => $message], 403);
            return redirect()->back()->with('error', $message);
        }

        // party_name is NOT NULL and has no sensible default (unlike the
        // numeric fields below) -- a customer row can't have a blank name.
        // ConvertEmptyStringsToNull turns a cleared inline-edit field into
        // an explicit null before it ever reaches here, so this was
        // reaching $pipeline->update($data) and crashing with a raw 500
        // instead of a real validation message.
        if (array_key_exists('party_name', $data) && trim((string) $data['party_name']) === '') {
            $message = 'Party name cannot be blank.';
            if ($wantsJson) return response()->json(['success' => false, 'message' => $message], 422);
            return redirect()->back()->with('error', $message);
        }

        // These 5 columns are NOT NULL (with a DB default of 0 for inserts
        // only - an explicit null still violates the constraint on update).
        // An inline-edit save with the field cleared sent an empty string
        // straight through to $pipeline->update($data), which crashed with
        // a 500 and no visible message. store() already defaults these the
        // same way; update() did not.
        foreach (['total_business_potential', 'yoy_22_23', 'yoy_23_24', 'yoy_24_25', 'yoy_25_26'] as $numericField) {
            if (array_key_exists($numericField, $data) && ($data[$numericField] === '' || $data[$numericField] === null)) {
                $data[$numericField] = 0;
            }
        }

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
                $ccareId = $pipeline->ccare_id ?: SalesPipeline::mappedCcareFor($pipeline->salesperson_id);
                // Directly save — bypasses mass assignment issues with null.
                // Leaving New Biz keeps the months before this one under New
                // Biz (SalesPipeline's saving hook sets ccare_from).
                $pipeline->ccare_id   = $ccareId;
                $pipeline->new_biz_id = null;
                $pipeline->save();

            } elseif ($newCategory === 'New Biz') {
                // Keep existing new_biz_id if already set, else the New Biz person it
                // had before moving to CCare (undoing a switch), else CcSalespersonMap
                $newBizId = $pipeline->new_biz_id ?: $pipeline->former_new_biz_id;
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


        if ($wantsJson) {
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


    /**
     * Save the Tally sale a CCare / New Biz rep recorded against one salesperson
     * for one month, from the Target Achievement popup.
     *
     * Who may write it is the same question as who may write the rest of the
     * book -- canEditBook() -- and the month closes on exactly the same rule as
     * the Sale / Pending figures, isMonthLockedForUser(). Both are reused rather
     * than restated so this cannot drift away from the page around it.
     *
     * Visibility is already handled upstream: the popup is built from
     * $allTargets, which SalesScope has narrowed to the people this user may
     * see, so a rep is only ever offered their own salespeople to fill in. This
     * re-checks that rather than trusting the id that arrives in the request.
     */
    public function tallySaleUpdate(Request $request)
    {
        $data = $request->validate([
            'salesperson_id' => 'required|integer|exists:users,id',
            'month_year'     => 'required|string|size:7',
            'value'          => 'nullable|numeric|min:0|max:9999999',
        ]);

        $user = auth()->user();

        if (!self::canEditBook($user)) {
            return response()->json(['success' => false, 'message' => 'You cannot edit the sales book.'], 403);
        }

        if (self::isMonthLockedForUser($user, $data['month_year'])) {
            return response()->json([
                'success' => false,
                'message' => 'That month is closed. Only Admin/HR can change it now.',
            ], 403);
        }

        // The same scope the popup itself was built from. Null means unrestricted.
        $allowed = \App\Support\SalesScope::viewIds($user, $request, 'sp');
        if ($allowed !== null && !in_array((int) $data['salesperson_id'], $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'That salesperson is not yours to fill in.'], 403);
        }

        // A blank box clears the figure back to "not entered yet", which is why
        // this is nullable rather than being coerced to 0.
        // Kept as a string, not a float: the column is cast decimal:2, and
        // handing Brick\Math a float is deprecated and becomes an error in 0.15.
        $value = ($data['value'] === null || $data['value'] === '')
            ? null
            : number_format((float) $data['value'], 2, '.', '');

        $target = \App\Models\SalespersonTarget::firstOrNew([
            'salesperson_id' => (int) $data['salesperson_id'],
            'month_year'     => $data['month_year'],
        ]);
        if (!$target->exists) {
            $target->tenant_id          = $user->tenant_id;
            $target->bingo_target       = 0;
            $target->kingo_target       = 0;
            $target->super_kingo_target = 0;
        }
        $target->tally_sale       = $value;
        $target->tally_sale_by_id = $value === null ? null : $user->id;
        $target->tally_sale_at    = $value === null ? null : now();
        $target->save();

        return response()->json([
            'success' => true,
            'value'   => $value,
            'by'      => $value === null ? null : $user->name,
            'at'      => $value === null ? null : now()->format('d M Y, H:i'),
        ]);
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
        // Forecast is open to any sales user on any row they can see; every
        // other field still needs the row to be theirs and unlocked. The
        // Sales Report lists only LOCKED rows, so the old rule meant the
        // forecast permission could never actually apply there.
        $isForecast  = $request->field === 'forecast_amount';
        $mayForecast = self::canEditBook($user) || \App\Http\Middleware\SalesAccessGuard::isBroadSalesUser($user);
        if ($isForecast ? !$mayForecast : !self::canEditOwnPipelineRow($user, $pipeline)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // A plain salesperson (canEditOwnPipelineRow() passed only via the
        // broad-sales-user carve-out, not canEditBook()) may fill in ONLY
        // their own Forecast figure -- Sale / Pending / Remarks stay the
        // book editors' (CCare / New Biz / manager / admin) job.
        if (!self::canEditBook($user) && !$isForecast) {
            return response()->json(['success' => false, 'message' => 'You can only update the Forecast figure here.'], 403);
        }

        // Closed months are locked to everyone except Admin/HR, regardless
        // of role -- see isMonthLockedForUser().
        if (self::isMonthLockedForUser($user, $request->month_year)) {
            return response()->json(['success' => false, 'message' => 'This month is closed for editing. Only Admin/HR can update it now.'], 403);
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

        // The 5-month rule. This used to test `type`, which holds the customer
        // type (Customer / Dealer / ...), never 'New Biz' - so it never fired.
        $moved = $field === 'sale_amount' && $pipeline->graduateIfDue();
        if ($moved) {
            $pipeline->load('ccare');
            return response()->json([
                'success'        => true,
                'data'           => $monthData,
                'moved_to_ccare' => true,
                'ccare_id'       => $pipeline->ccare_id,
                'rep_name'       => $pipeline->ccare->name ?? null,
                'ccare_from'     => \Carbon\Carbon::parse($pipeline->ccare_from . '-01')->format('M Y'),
            ]);
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
