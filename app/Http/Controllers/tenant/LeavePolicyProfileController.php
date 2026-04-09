<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\LeavePolicyProfile;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\LeaveBalance;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class LeavePolicyProfileController extends Controller
{
    public function index()
    {
        $profiles = LeavePolicyProfile::with('rules.leaveType')->get();
        $leaveTypes = LeaveType::where('status', 'active')->get();
        $users = User::where('status', 'active')->get();
        return view('tenant.leavePolicyProfiles.index', compact('profiles', 'leaveTypes', 'users'));
    }

    public function getProfileAjax($id)
    {
        $profile = LeavePolicyProfile::with('rules')->findOrFail($id);
        return response()->json($profile);
    }

    public function addOrUpdateAjax(Request $request)
    {
        $id = $request->id;
        $profile = LeavePolicyProfile::findOrNew($id);
        
        $profile->name = $request->name;
        $profile->description = $request->description;
        $profile->saturday_off_config = $request->saturday_off ?? [];
        $profile->deduction_config = $request->deduction_config ?? [];
        $profile->save();

        // Sync Rules
        $rules = $request->rules ?? [];
        
        // Remove old rules
        $profile->rules()->delete();

        foreach ($rules as $typeId => $ruleData) {
            // Only create rule if applicable
            if (isset($ruleData['is_applicable']) && $ruleData['is_applicable'] == 1) {
                
                $tenureTiers = [];
                if (!empty($ruleData['tenure_required_months'])) {
                    $tenureTiers[] = [
                        'months' => (int)$ruleData['tenure_required_months'],
                        'consecutive' => (int)($ruleData['tenure_consecutive_allowed'] ?? 1)
                    ];
                }

                $profile->rules()->create([
                    'leave_type_id' => $typeId,
                    'is_applicable' => true,
                    'is_married_only' => (isset($ruleData['is_married_only']) && $ruleData['is_married_only'] == 1),
                    'applicable_gender' => $ruleData['applicable_gender'] ?? 'all',
                    'applicable_marital_status' => $ruleData['applicable_marital_status'] ?? 'all',
                    'max_per_month' => !empty($ruleData['max_per_month']) ? $ruleData['max_per_month'] : null,
                    'max_per_year' => !empty($ruleData['max_per_year']) ? $ruleData['max_per_year'] : null,
                    'max_consecutive_days' => $ruleData['max_consecutive_days'] ?? 1,
                    'short_leave_hours' => $ruleData['short_leave_hours'] ?? null,
                    'short_leave_per_month' => $ruleData['short_leave_per_month'] ?? null,
                    'is_carry_forward' => (isset($ruleData['is_carry_forward']) && $ruleData['is_carry_forward'] == 1),
                    'carry_forward_max_days' => $ruleData['carry_forward_max_days'] ?? null,
                    'wfh_days_entitlement' => $ruleData['wfh_days_entitlement'] ?? null,
                    'off_days_entitlement' => $ruleData['off_days_entitlement'] ?? null,
                    'tenure_required_months' => $ruleData['tenure_required_months'] ?? null,
                    'tenure_consecutive_allowed' => $ruleData['tenure_consecutive_allowed'] ?? null,
                    'tenure_tiers' => $tenureTiers
                ]);
            }
        }

        return response()->json([
            'code' => 200,
            'message' => $id ? 'Profile updated successfully!' : 'Profile created successfully!'
        ]);
    }

    public function addManualCreditAjax(Request $request)
    {
        $request->validate([
            'user_id'       => 'required|exists:users,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'amount'        => 'required|numeric|min:0.5|max:365',
        ]);

        $userId      = $request->user_id;
        $leaveTypeId = $request->leave_type_id;
        $amount      = (float) $request->amount;
        $reason      = $request->adj_reason_text ?? 'Manual Credit';

        $user = User::findOrFail($userId);

        // Use firstOrNew (same pattern as LeaveAccrualService::grantCoff)
        // This correctly handles both new and existing balance records
        $balance = LeaveBalance::firstOrNew([
            'user_id'       => $userId,
            'leave_type_id' => $leaveTypeId,
            'tenant_id'     => $user->tenant_id,
        ]);

        // Ensure defaults on new records
        if (!$balance->exists) {
            $balance->used = 0;
        }

        $balance->balance = ($balance->balance ?? 0) + $amount;
        $balance->save();

        Log::info("Manual leave credit of {$amount} days for user {$userId} (leave_type={$leaveTypeId}). Reason: {$reason}");

        return response()->json([
            'code'    => 200,
            'message' => 'Credit of ' . $amount . ' day(s) allotted successfully!',
        ]);
    }

    public function getProfileListAjax()
    {
        $profiles = \App\Models\LeavePolicyProfile::select('id', 'name')->get();
        return \App\ApiClasses\Success::response($profiles);
    }
}
