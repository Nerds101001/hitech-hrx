<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Models\UnitLeavePolicy;
use App\Enums\Status;
use Illuminate\Http\Request;

class UnitLeavePolicyController extends Controller
{
    /**
     * Return all active leave types, annotated with any existing policy for the given unit.
     */
    public function getPoliciesForUnit($siteId)
    {
        $leaveTypes = LeaveType::where('status', Status::ACTIVE)->get();

        $existing = UnitLeavePolicy::where('site_id', $siteId)
            ->get()
            ->keyBy('leave_type_id');

        $result = $leaveTypes->map(function ($lt) use ($siteId, $existing) {
            $policy = $existing->get($lt->id);
            return [
                'leave_type_id'          => $lt->id,
                'leave_type_name'        => $lt->name,
                'leave_type_code'        => $lt->code,
                'is_short_leave'         => (bool) $lt->is_short_leave,
                'is_applicable'          => $policy ? (bool) $policy->is_applicable : true,
                'max_per_month'          => $policy ? $policy->max_per_month : null,
                'max_per_year'           => $policy ? $policy->max_per_year : null,
                'max_consecutive_days'   => $policy ? $policy->max_consecutive_days : null,
                'short_leave_hours'      => $policy ? $policy->short_leave_hours : null,
                'short_leave_per_month'  => $policy ? $policy->short_leave_per_month : null,
                'tenure_required_months' => $policy ? $policy->tenure_required_months : null,
                'tenure_tiers'           => $policy ? $policy->tenure_tiers : [],
            ];
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Upsert a single leave policy row for a unit+leave_type combination.
     */
    public function savePolicy(Request $request)
    {
        $request->validate([
            'site_id'               => 'required|exists:sites,id',
            'leave_type_id'         => 'required|exists:leave_types,id',
            'is_applicable'         => 'boolean',
            'max_per_month'         => 'nullable|integer|min:0',
            'max_per_year'          => 'nullable|integer|min:0',
            'max_consecutive_days'  => 'nullable|integer|min:1',
            'short_leave_hours'     => 'nullable|numeric|min:0.5',
            'short_leave_per_month' => 'nullable|integer|min:0',
            'tenure_required_months'=> 'nullable|integer|min:0',
            'tenure_tiers'          => 'nullable|array',
        ]);

        UnitLeavePolicy::updateOrCreate(
            [
                'site_id'       => $request->site_id,
                'leave_type_id' => $request->leave_type_id,
            ],
            [
                'is_applicable'          => $request->boolean('is_applicable', true),
                'max_per_month'          => $request->max_per_month ?: null,
                'max_per_year'           => $request->max_per_year ?: null,
                'max_consecutive_days'   => $request->max_consecutive_days ?: null,
                'short_leave_hours'      => $request->short_leave_hours ?: null,
                'short_leave_per_month'  => $request->short_leave_per_month ?: null,
                'tenure_required_months' => $request->tenure_required_months ?: null,
                'tenure_tiers'           => $request->tenure_tiers ?: [],
            ]
        );

        return response()->json(['success' => true, 'message' => 'Policy saved successfully.']);
    }
}
