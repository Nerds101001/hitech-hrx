<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SalespersonTarget;

class SalesTargetController extends Controller
{
    public function update(Request $request)
    {
        // Targets are set by Admin/HR only. Managers, CCare and New Biz agents
        // may read them everywhere but must never be able to change their own
        // or anyone else's numbers.
        if (!auth()->user()->hasRole(['admin', 'Admin', 'hr', 'HR']) && !auth()->user()->can('sales_targets.manage')) {
            return response()->json(['success' => false, 'message' => 'Only Admin/HR can set targets.'], 403);
        }

        $request->validate([
            'salesperson_id' => 'required|exists:users,id',
            'month_year' => 'required|string',
            'super_kingo_target' => 'nullable|numeric',
            'kingo_target' => 'nullable|numeric',
            'bingo_target' => 'nullable|numeric'
        ]);

        $target = SalespersonTarget::updateOrCreate(
            [
                'salesperson_id' => $request->salesperson_id,
                'month_year' => $request->month_year,
            ],
            [
                'super_kingo_target' => $request->super_kingo_target ?: 0,
                'kingo_target' => $request->kingo_target ?: 0,
                'bingo_target' => $request->bingo_target ?: 0,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Target updated successfully',
            'target' => $target
        ]);
    }
}
