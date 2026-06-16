<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SalespersonTarget;

class SalesTargetController extends Controller
{
    public function update(Request $request)
    {
        $user = auth()->user();
        $isAdmin   = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']);
        $isCcare   = $user->department && $user->department->name === 'Customer Care';
        $isNewBiz  = $user->department && $user->department->name === 'New Biz';
        if (!$isAdmin && !$isCcare && !$isNewBiz) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'salesperson_id' => 'required|exists:users,id',
            'month_year' => 'required|string',
            'kingo_target' => 'nullable|numeric',
            'bingo_target' => 'nullable|numeric'
        ]);

        $target = SalespersonTarget::updateOrCreate(
            [
                'salesperson_id' => $request->salesperson_id,
                'month_year' => $request->month_year,
            ],
            [
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
