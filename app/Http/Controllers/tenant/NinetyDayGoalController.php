<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\NinetyDayGoal;
use App\Models\AppraisalCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NinetyDayGoalController extends Controller
{
    public function index(Request $request)
    {
        $cycleId = $request->get('cycle_id');
        
        $query = NinetyDayGoal::with(['user', 'cycle'])
            ->where('tenant_id', Auth::user()->tenant_id);

        if ($cycleId) {
            $query->where('appraisal_cycle_id', $cycleId);
        }

        // If regular employee, only see their own goals
        if (!Auth::user()->hasRole(['admin', 'hr', 'manager'])) {
            $query->where('user_id', Auth::id());
        }

        $goals = $query->latest()->get();
        $cycles = AppraisalCycle::where('tenant_id', Auth::user()->tenant_id)->get();

        return view('tenant.performance.ninety-day-goals.index', compact('goals', 'cycles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_name' => 'required|string|max:255',
            'product' => 'nullable|string|max:255',
            'goal_type' => 'required|string|max:100',
            'target_value' => 'nullable|numeric|min:0',
            'appraisal_cycle_id' => 'nullable|exists:appraisal_cycles,id',
            'user_id' => 'nullable|exists:users,id'
        ]);

        $userId = $request->user_id ?? Auth::id();

        NinetyDayGoal::create([
            'user_id' => $userId,
            'appraisal_cycle_id' => $request->appraisal_cycle_id,
            'client_name' => $request->client_name,
            'product' => $request->product,
            'goal_type' => $request->goal_type,
            'target_value' => $request->target_value ?? 0,
            'created_by_id' => Auth::id(),
            'tenant_id' => Auth::user()->tenant_id,
            'w0' => 'On Track', // Default initial status
        ]);

        return redirect()->back()->with('success', '90-Day Goal created successfully.');
    }

    public function update(Request $request, $id)
    {
        $goal = NinetyDayGoal::findOrFail($id);

        // Security check: only owner or manager/admin can update
        if ($goal->user_id !== Auth::id() && !Auth::user()->hasRole(['admin', 'hr', 'manager'])) {
            abort(403);
        }

        // Validate any week field passed (w0 to w12)
        $rules = [];
        for ($i = 0; $i <= 12; $i++) {
            $rules["w$i"] = 'nullable|string|max:100';
        }
        $request->validate($rules);

        // Update the weekly statuses
        $updateData = [];
        for ($i = 0; $i <= 12; $i++) {
            $key = "w$i";
            if ($request->has($key)) {
                $updateData[$key] = $request->get($key);
            }
        }

        if (!empty($updateData)) {
            $goal->update($updateData);
        }

        return response()->json(['success' => true, 'message' => 'Progress updated']);
    }

    public function destroy($id)
    {
        $goal = NinetyDayGoal::findOrFail($id);
        
        if ($goal->user_id !== Auth::id() && !Auth::user()->hasRole(['admin', 'hr', 'manager'])) {
            abort(403);
        }

        $goal->delete();
        return redirect()->back()->with('success', 'Goal deleted successfully.');
    }
}
