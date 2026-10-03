<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\AppraisalScorecard;
use App\Models\AppraisalCycle;
use App\Models\Department;
use App\Models\NinetyDayGoal;
use App\Models\User;
use App\Services\KpiScorecardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalesAssessmentController extends Controller
{
    /** Fixed grade labels a scorecard's total_score is bucketed into - see generate()/updateReview(). */
    public const GRADES = [
        'A - Outstanding',
        'B - Exceeds Expectation',
        'C - Meets Expectation',
        'D - Needs Improvement',
    ];

    /**
     * Lists every employee (not "every scorecard someone happened to
     * generate") with their live, continuously-tracked KPI/KRA score - the
     * same figures KpiController::matrixData() computes for "My Scorecard".
     * There is no manual generation step here: unlike the old
     * AppraisalScorecard flow (generate()/updateReview(), still intact but
     * no longer linked from this page), a KPI score simply exists the moment
     * someone has assignments and checks, so a department filter shows
     * everyone in it rather than only the handful someone had previously
     * clicked "Generate" for.
     */
    public function index(Request $request)
    {
        if ($request->filled('pdf')) {
            return $this->monthlyPdf($request);
        }

        $query = User::where('status', \App\Enums\UserAccountStatus::ACTIVE)->with('department')
            ->whereNotIn('id', config('kpi.excluded_user_ids', []));   // office accounts, not people

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%"));
        }

        if (!Auth::user()->hasRole(['admin', 'hr', 'manager'])) {
            $query->where('id', Auth::id());
        }

        $employees = $query->orderBy('first_name')->get();

        // Unsynced read (no KpiPeriodService::syncUser() per row) - this is
        // a listing of many people, not the one live dashboard my() renders,
        // so it reads whatever checks already exist rather than writing N
        // employees' worth of current-period rows on every page load.
        $scorecards = $employees->map(function ($user) {
            $data = KpiController::matrixData($user, sync: false);
            $possible = (float) $data['possible'];
            $pct = $possible > 0 ? round(((float) $data['earned'] / $possible) * 100) : null;
            $grade = match (true) {
                $pct === null => null,
                $pct >= 80 => 'A - Outstanding',
                $pct >= 70 => 'B - Exceeds Expectation',
                $pct >= 60 => 'C - Meets Expectation',
                default => 'D - Needs Improvement',
            };

            return (object) [
                'user' => $user,
                'earned' => $data['earned'],
                'possible' => $possible,
                'pct' => $pct,
                'grade' => $grade,
                'has_assignments' => $data['assignments']->isNotEmpty(),
            ];
        });

        if ($request->filled('grade')) {
            $scorecards = $scorecards->where('grade', $request->grade)->values();
        }

        $departments = Department::cachedActive();
        $pdfMonths = KpiScorecardService::months();

        return view('tenant.performance.scorecards.index', compact('scorecards', 'departments', 'pdfMonths'));
    }

    /**
     * ?pdf=YYYY-MM on the Scorecards page: one employee's month (user_id), or
     * everyone the page's own filters would list (department_id / search) as a
     * single multi-page file. Reaches exactly the people the listing does.
     */
    private function monthlyPdf(Request $request)
    {
        $month = KpiScorecardService::parseMonth($request->query('pdf'));
        if (!$month) {
            return back()->with('error', 'Pick a month that has already started.');
        }

        $query = User::where('status', \App\Enums\UserAccountStatus::ACTIVE)
            ->whereIn('id', \App\Models\KpiAssignment::query()->select('user_id'))
            ->whereNotIn('id', config('kpi.excluded_user_ids', []));   // office accounts, not people
        if (!Auth::user()->hasRole(['admin', 'hr', 'manager'])) {
            $query->where('id', Auth::id());
        }

        if ($request->filled('user_id')) {
            $query->where('id', (int) $request->query('user_id'));
        } else {
            if ($request->filled('department_id')) {
                $query->where('department_id', (int) $request->query('department_id'));
            }
            if ($request->filled('search')) {
                $term = $request->query('search');
                $query->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%"));
            }
        }

        $users = $query->orderBy('first_name')->get();
        if ($users->count() > KpiScorecardService::BULK_LIMIT) {
            return back()->with('error', 'That is ' . $users->count() . ' people; pick a department so it is at most ' . KpiScorecardService::BULK_LIMIT . '.');
        }

        $cards = KpiScorecardService::cardsFor($users, $month);
        if ($cards->isEmpty()) {
            return back()->with('error', 'No KPIs to show for ' . $month->format('F Y') . '.');
        }

        $label = $request->filled('user_id')
            ? \Illuminate\Support\Str::slug($users->first()->first_name . ' ' . $users->first()->last_name)
            : 'all-' . ($request->filled('department_id')
                ? \Illuminate\Support\Str::slug(optional(Department::find($request->query('department_id')))->name ?: 'department')
                : 'departments');

        return KpiScorecardService::download($cards, $month, 'KPI-Scorecard-' . $label . '-' . $month->format('Y-m') . '.pdf');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'appraisal_cycle_id' => 'required|exists:appraisal_cycles,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $cycle = AppraisalCycle::findOrFail($request->appraisal_cycle_id);

        $startMonth = $cycle->start_date->format('Y-m');
        $endMonth = $cycle->end_date->format('Y-m');

        // 1. Revenue Target (Bingo Target) from SalespersonTarget
        $revenueTarget = \App\Models\SalespersonTarget::where('salesperson_id', $user->id)
            ->whereBetween('month_year', [$startMonth, $endMonth])
            ->sum('bingo_target') ?? 0;

        // 2. Revenue Achieved (Bingo Achieved) from SalesPipelineMonth (sale_amount)
        $revenueAchieved = \App\Models\SalesPipelineMonth::whereHas('pipeline', function ($q) use ($user) {
                $q->where('salesperson_id', $user->id);
            })
            ->whereBetween('month_year', [$startMonth, $endMonth])
            ->sum('sale_amount') ?? 0;

        $bingoPercentage = $revenueTarget > 0 ? ($revenueAchieved / $revenueTarget) * 100 : 0;

        // 3. Pipeline Value from SalesPipeline total_business_potential
        $pipelineValue = \App\Models\SalesPipeline::where('salesperson_id', $user->id)
            ->whereNull('deleted_at')
            ->sum('total_business_potential') ?? 0;

        // 4. YoY Revenue Growth
        $prevStart = $cycle->start_date->copy()->subYear();
        $prevEnd = $cycle->end_date->copy()->subYear();
        
        $prevSales = \App\Models\SalesPipelineMonth::whereHas('pipeline', function ($q) use ($user) {
                $q->where('salesperson_id', $user->id);
            })
            ->whereBetween('month_year', [
                $prevStart->format('Y-m'),
                $prevEnd->format('Y-m')
            ])
            ->sum('sale_amount') ?? 0;
            
        $yoyGrowthPercentage = $prevSales > 0 ? (($revenueAchieved - $prevSales) / $prevSales) * 100 : 0;

        // 5. Demos Conducted (trial visits)
        $demosConducted = \App\Models\SalesVisit::where('salesperson_id', $user->id)
            ->where('visit_type', 'product_trial')
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$cycle->start_date, $cycle->end_date])
            ->count();

        // 6. New Customer Revenue Percentage
        // New Biz months: a New Biz customer's, and a customer's months before it
        // moved to CCare (converted_from_newbiz was meant for that but never got set).
        $newCustomerSalesQuery = \App\Models\SalesPipelineMonth::whereHas('pipeline', function ($q) use ($user) {
                $q->where('salesperson_id', $user->id);
            })
            ->whereBetween('month_year', [$startMonth, $endMonth]);
        \App\Models\SalesPipeline::whereMonthInCategory($newCustomerSalesQuery, 'New Biz');
        $newCustomerSales = $newCustomerSalesQuery->sum('sale_amount') ?? 0;

        $newCustomerRevenuePercentage = $revenueAchieved > 0 ? ($newCustomerSales / $revenueAchieved) * 100 : 0;

        // 7. 90-Day Goals evaluation
        $goals = NinetyDayGoal::where('user_id', $user->id)
            ->where('appraisal_cycle_id', $cycle->id)
            ->get();
            
        $successfulGoals = 0;
        foreach($goals as $g) {
            if (in_array($g->w12, ['Regular Order', 'On Track'])) {
                $successfulGoals++;
            }
        }
        $ninetyDayAchievedPct = count($goals) > 0 ? ($successfulGoals / count($goals)) * 100 : 0;

        // 8. Learn@HiTech programs
        $learnHitechPrograms = \App\Models\LearnHitechLog::where('user_id', $user->id)
            ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date])
            ->count();

        // 9. Seminars Conducted
        $seminarsConducted = \App\Models\MindSpeak::where('user_id', $user->id)
            ->where('category', 'Seminar')
            ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date])
            ->count();

        // 10. Competitor Insights
        $competitorInsights = \App\Models\MindSpeak::where('user_id', $user->id)
            ->where('category', 'Competitor Insights')
            ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date])
            ->count();

        // 11. New Product Ideas
        $productIdeas = \App\Models\MindSpeak::where('user_id', $user->id)
            ->where('category', 'Idea')
            ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date])
            ->count();

        // 12. Cost of Selling (COS%)
        $travelExpense = \App\Models\TravelClaim::where('user_id', $user->id)
            ->whereIn('status', ['verified', 'paid'])
            ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date])
            ->sum('total_amount') ?? 0;

        $otherExpense = \App\Models\ExpenseRequest::where('user_id', $user->id)
            ->whereIn('status', ['approved', 'processed'])
            ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date])
            ->sum('approved_amount') ?? 0;

        $totalExpenses = $travelExpense + $otherExpense;
        $costOfSellingPercentage = $revenueAchieved > 0 ? ($totalExpenses / $revenueAchieved) * 100 : 0;

        // 13. Cross-Sell / Basket Mix (dominant product percentage)
        $orderIds = \App\Models\ProductOrder::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date])
            ->pluck('id')
            ->toArray();

        $dominantProductPercentage = 0;
        if (!empty($orderIds)) {
            $productTotals = \App\Models\OrderLine::whereIn('order_id', $orderIds)
                ->select('product_id', \Illuminate\Support\Facades\DB::raw('SUM(total) as product_total'))
                ->groupBy('product_id')
                ->orderBy('product_total', 'desc')
                ->get();

            $totalSalesVal = $productTotals->sum('product_total');
            if ($totalSalesVal > 0) {
                $dominantProductTotal = $productTotals->first()->product_total;
                $dominantProductPercentage = ($dominantProductTotal / $totalSalesVal) * 100;
            }
        }

        // 14. Customer Attrition Rate
        $prevYearPipelines = \App\Models\SalesPipelineMonth::whereHas('pipeline', function ($q) use ($user) {
                $q->where('salesperson_id', $user->id);
            })
            ->whereBetween('month_year', [
                $prevStart->format('Y-m'),
                $prevEnd->format('Y-m')
            ])
            ->where('sale_amount', '>', 0)
            ->pluck('sales_pipeline_id')
            ->unique()
            ->toArray();

        $baseCustomerCount = count($prevYearPipelines);
        $lostCustomerCount = 0;

        if ($baseCustomerCount > 0) {
            foreach ($prevYearPipelines as $pipelineId) {
                $currentSales = \App\Models\SalesPipelineMonth::where('sales_pipeline_id', $pipelineId)
                    ->whereBetween('month_year', [$startMonth, $endMonth])
                    ->sum('sale_amount') ?? 0;
                if ($currentSales <= 0) {
                    $lostCustomerCount++;
                }
            }
            $attritionRate = ($lostCustomerCount / $baseCustomerCount) * 100;
        } else {
            $attritionRate = 0;
        }

        // --- Calculate Scores ---
        // 1. Bingo Score
        $revenueScore = 0;
        if ($bingoPercentage >= 125) $revenueScore = 25;
        elseif ($bingoPercentage >= 110) $revenueScore = 20;
        elseif ($bingoPercentage >= 97) $revenueScore = 15;
        elseif ($bingoPercentage >= 90) $revenueScore = 12;
        elseif ($bingoPercentage >= 80) $revenueScore = 8;
        elseif ($bingoPercentage >= 70) $revenueScore = 4;

        // 2. Pipeline Score
        $pipelineScore = 0;
        $pipelineRatio = $revenueTarget > 0 ? ($pipelineValue / $revenueTarget) * 100 : 0;
        if ($pipelineRatio >= 90) $pipelineScore = 10;
        elseif ($pipelineRatio >= 80) $pipelineScore = 8;
        elseif ($pipelineRatio >= 70) $pipelineScore = 5;
        elseif ($pipelineRatio >= 60) $pipelineScore = 3;

        // 3. YoY Growth Score
        $yoyGrowthScore = 0;
        if ($yoyGrowthPercentage >= 35) $yoyGrowthScore = 30;
        elseif ($yoyGrowthPercentage >= 25) $yoyGrowthScore = 20;
        elseif ($yoyGrowthPercentage >= 15) $yoyGrowthScore = 10;

        // 4. Demos Score
        $demosScore = 0;
        if ($demosConducted > 30) $demosScore = 8;
        elseif ($demosConducted >= 20) $demosScore = 5;
        elseif ($demosConducted >= 12) $demosScore = 3;
        elseif ($demosConducted >= 8) $demosScore = 2;

        // 5. New Customer Score
        $newCustomerScore = 0;
        if ($newCustomerRevenuePercentage > 30) $newCustomerScore = 15;
        elseif ($newCustomerRevenuePercentage >= 25) $newCustomerScore = 13;
        elseif ($newCustomerRevenuePercentage >= 20) $newCustomerScore = 8;
        elseif ($newCustomerRevenuePercentage >= 15) $newCustomerScore = 5;

        // 6. 90-Day Goals Score
        $ninetyDayScore = 0;
        if ($ninetyDayAchievedPct >= 70) $ninetyDayScore = 8;
        elseif ($ninetyDayAchievedPct >= 50) $ninetyDayScore = 5;
        elseif ($ninetyDayAchievedPct >= 40) $ninetyDayScore = 3;

        // 7. Seminars Score
        $seminarsScore = 0;
        if ($seminarsConducted >= 12) $seminarsScore = 5;
        elseif ($seminarsConducted >= 8) $seminarsScore = 2;

        // 8. Competitor Insights Score
        $insightsScore = min($competitorInsights, 8); // Max 8 pts

        // 9. Product Ideas Score
        $ideasScore = min($productIdeas, 8); // Max 8 pts

        // 10. Learn@HiTech Score
        $learnHitechScore = 0;
        if ($learnHitechPrograms >= 2) $learnHitechScore = 5;
        elseif ($learnHitechPrograms == 1) $learnHitechScore = 2;

        // 11. Cost of Selling Score
        $tenureMonths = 0;
        if ($user->date_of_joining) {
            $tenureMonths = \Carbon\Carbon::parse($user->date_of_joining)->diffInMonths(now());
        }
        
        $costOfSellingScore = 0;
        if ($tenureMonths < 6) {
            $costOfSellingScore = 3;
        } else {
            if ($costOfSellingPercentage < 2) $costOfSellingScore = 5;
            elseif ($costOfSellingPercentage <= 4) $costOfSellingScore = 3;
            elseif ($costOfSellingPercentage <= 6) $costOfSellingScore = 1;
            elseif ($costOfSellingPercentage > 10) $costOfSellingScore = -5;
        }

        // 12. Cross-Sell Score
        $crossSellScore = 0;
        if ($dominantProductPercentage <= 75) $crossSellScore = 5;
        elseif ($dominantProductPercentage <= 90) $crossSellScore = 3;

        // 13. Attrition Penalty
        $attritionPenalty = 0;
        if ($attritionRate >= 10) $attritionPenalty = -5;
        elseif ($attritionRate >= 5) $attritionPenalty = -3;

        // Save Scorecard
        $scorecard = AppraisalScorecard::updateOrCreate(
            [
                'user_id' => $user->id,
                'appraisal_cycle_id' => $cycle->id,
            ],
            [
                'revenue_achieved' => $revenueAchieved,
                'revenue_target' => $revenueTarget,
                'pipeline_value' => $pipelineValue,
                'yoy_growth_percentage' => $yoyGrowthPercentage,
                'demos_conducted' => $demosConducted,
                'new_customer_revenue_percentage' => $newCustomerRevenuePercentage,
                'ninety_day_goal_achievement' => $ninetyDayAchievedPct,
                'learn_hitech_programs' => $learnHitechPrograms,
                'seminars_conducted' => $seminarsConducted,
                'competitor_insights_count' => $competitorInsights,
                'product_ideas_count' => $productIdeas,
                'cost_of_selling_percentage' => $costOfSellingPercentage,
                'cross_sell_dominant_percentage' => $dominantProductPercentage,
                'attrition_rate' => $attritionRate,

                'revenue_score' => $revenueScore,
                'pipeline_score' => $pipelineScore,
                'yoy_growth_score' => $yoyGrowthScore,
                'demos_score' => $demosScore,
                'new_customer_score' => $newCustomerScore,
                'ninety_day_goal_score' => $ninetyDayScore,
                'learn_hitech_score' => $learnHitechScore,
                'seminars_score' => $seminarsScore,
                'competitor_insights_score' => $insightsScore,
                'product_ideas_score' => $ideasScore,
                'cost_of_selling_score' => $costOfSellingScore,
                'cross_sell_score' => $crossSellScore,
                'attrition_penalty' => $attritionPenalty,

                'tenant_id' => Auth::user()->tenant_id,
            ]
        );

        // Recalculate Total Score
        $scorecard->total_score = $scorecard->revenue_score + $scorecard->pipeline_score + 
            $scorecard->yoy_growth_score + $scorecard->demos_score + $scorecard->new_customer_score + 
            $scorecard->ninety_day_goal_score + $scorecard->sales_pitch_score + $scorecard->learn_hitech_score + 
            $scorecard->seminars_score + $scorecard->competitor_insights_score + $scorecard->product_ideas_score + 
            $scorecard->cost_of_selling_score + $scorecard->cross_sell_score + $scorecard->market_share_score + 
            $scorecard->attrition_penalty;
            
        // Calculate Grade
        if ($scorecard->total_score >= 80) $scorecard->grade = "A - Outstanding";
        elseif ($scorecard->total_score >= 70) $scorecard->grade = "B - Exceeds Expectation";
        elseif ($scorecard->total_score >= 60) $scorecard->grade = "C - Meets Expectation";
        else $scorecard->grade = "D - Needs Improvement";
        
        $scorecard->save();

        return redirect()->route('performance.scorecards.show', $scorecard->id)
            ->with('success', 'Scorecard generated successfully.');
    }

    /**
     * The employee's KPI/KRA matrix - identical table to "My Scorecard"
     * (KpiController::my()), read-only, for an arbitrary employee. $id here
     * is a user id, not an AppraisalScorecard id - see index()'s docblock
     * for why this page moved off that model.
     */
    public function show($id)
    {
        $user = User::with('department')->findOrFail($id);

        if ((int) $user->id !== Auth::id() && !Auth::user()->hasRole(['admin', 'hr', 'manager'])) {
            abort(403);
        }

        $data = KpiController::matrixData($user);

        return view('tenant.performance.scorecards.show', array_merge($data, ['employee' => $user]));
    }

    public function updateReview(Request $request, $id)
    {
        $request->validate([
            'manager_comments' => 'nullable|string',
            'sales_pitch_score_raw' => 'nullable|integer|min:0|max:20',
            'market_share_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $scorecard = AppraisalScorecard::findOrFail($id);

        if (!Auth::user()->hasRole(['admin', 'hr', 'manager'])) {
            abort(403);
        }

        $scorecard->manager_comments = $request->manager_comments;
        if ($request->has('sales_pitch_score_raw')) {
            $scorecard->sales_pitch_score_raw = $request->sales_pitch_score_raw;
            // Calculate sales pitch score based on rules: =20 -> 5pts, 15-19 -> 2pts, <15 -> 0pts
            $pitchScore = 0;
            if ($request->sales_pitch_score_raw == 20) $pitchScore = 5;
            elseif ($request->sales_pitch_score_raw >= 15) $pitchScore = 2;
            $scorecard->sales_pitch_score = $pitchScore;
        }

        if ($request->has('market_share_percentage')) {
            $scorecard->market_share_percentage = $request->market_share_percentage;
            // Calculate market share score based on rules: >=60% -> 15, 40-60% -> 10, 30-40% -> 8, 20-30% -> 5, <20% -> 0
            $msScore = 0;
            if ($request->market_share_percentage >= 60) $msScore = 15;
            elseif ($request->market_share_percentage >= 40) $msScore = 10;
            elseif ($request->market_share_percentage >= 30) $msScore = 8;
            elseif ($request->market_share_percentage >= 20) $msScore = 5;
            $scorecard->market_share_score = $msScore;
        }

        // Recalculate Total Score
        $scorecard->total_score = $scorecard->revenue_score + $scorecard->pipeline_score + 
            $scorecard->yoy_growth_score + $scorecard->demos_score + $scorecard->new_customer_score + 
            $scorecard->ninety_day_goal_score + $scorecard->sales_pitch_score + $scorecard->learn_hitech_score + 
            $scorecard->seminars_score + $scorecard->competitor_insights_score + $scorecard->product_ideas_score + 
            $scorecard->cost_of_selling_score + $scorecard->cross_sell_score + $scorecard->market_share_score + 
            $scorecard->attrition_penalty;
            
        // Calculate Grade
        if ($scorecard->total_score >= 80) $scorecard->grade = "A - Outstanding";
        elseif ($scorecard->total_score >= 70) $scorecard->grade = "B - Exceeds Expectation";
        elseif ($scorecard->total_score >= 60) $scorecard->grade = "C - Meets Expectation";
        else $scorecard->grade = "D - Needs Improvement";

        $scorecard->save();

        return redirect()->back()->with('success', 'Manager review saved successfully.');
    }
}
