<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\AppraisalScorecard;
use App\Models\AppraisalCycle;
use App\Models\NinetyDayGoal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalesAssessmentController extends Controller
{
    public function index(Request $request)
    {
        $cycleId = $request->get('cycle_id');
        $query = AppraisalScorecard::with(['user', 'cycle'])->where('tenant_id', Auth::user()->tenant_id);

        if ($cycleId) {
            $query->where('appraisal_cycle_id', $cycleId);
        }

        if (!Auth::user()->hasRole(['admin', 'hr', 'manager'])) {
            $query->where('user_id', Auth::id());
        }

        $scorecards = $query->latest()->get();
        $cycles = AppraisalCycle::where('tenant_id', Auth::user()->tenant_id)->get();

        return view('tenant.performance.scorecards.index', compact('scorecards', 'cycles'));
    }

    public function generate(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'appraisal_cycle_id' => 'required|exists:appraisal_cycles,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $cycle = AppraisalCycle::findOrFail($request->appraisal_cycle_id);

        // --- 1. Compute Raw Metrics (Stubs for now to be wired to actual DB queries) ---
        // For example, finding mind_speaks where category = 'Seminar'
        $seminarsConducted = \App\Models\MindSpeak::where('user_id', $user->id)
            ->where('category', 'Seminar')
            ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date])
            ->count();

        $competitorInsights = \App\Models\MindSpeak::where('user_id', $user->id)
            ->where('category', 'Competitor Insights')
            ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date])
            ->count();

        $productIdeas = \App\Models\MindSpeak::where('user_id', $user->id)
            ->where('category', 'Idea')
            ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date])
            ->count();
            
        // 90 Day Goals evaluation (Calculate % of goals reaching 'Regular Order' or 'On Track' at W12)
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

        // --- 2. Calculate Scored Points ---
        
        $seminarsScore = 0;
        if ($seminarsConducted >= 12) $seminarsScore = 5;
        elseif ($seminarsConducted >= 8) $seminarsScore = 2;

        $insightsScore = min($competitorInsights, 8); // Max 8 pts
        $ideasScore = min($productIdeas, 8); // Max 8 pts
        
        $ninetyDayScore = 0;
        if ($ninetyDayAchievedPct >= 70) $ninetyDayScore = 8;
        elseif ($ninetyDayAchievedPct >= 50) $ninetyDayScore = 5;
        elseif ($ninetyDayAchievedPct >= 40) $ninetyDayScore = 3;

        // --- 3. Save Scorecard ---
        $scorecard = AppraisalScorecard::updateOrCreate(
            [
                'user_id' => $user->id,
                'appraisal_cycle_id' => $cycle->id,
            ],
            [
                'seminars_conducted' => $seminarsConducted,
                'competitor_insights_count' => $competitorInsights,
                'product_ideas_count' => $productIdeas,
                'ninety_day_goal_achievement' => $ninetyDayAchievedPct,
                
                'seminars_score' => $seminarsScore,
                'competitor_insights_score' => $insightsScore,
                'product_ideas_score' => $ideasScore,
                'ninety_day_goal_score' => $ninetyDayScore,
                
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

        return redirect()->route('tenant.performance.scorecards.show', $scorecard->id)
            ->with('success', 'Scorecard generated successfully.');
    }

    public function show($id)
    {
        $scorecard = AppraisalScorecard::with(['user', 'cycle'])->findOrFail($id);
        
        if ($scorecard->user_id !== Auth::id() && !Auth::user()->hasRole(['admin', 'hr', 'manager'])) {
            abort(403);
        }

        return view('tenant.performance.scorecards.show', compact('scorecard'));
    }
}
