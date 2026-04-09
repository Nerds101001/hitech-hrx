<?php

namespace Database\Seeders;

use App\Models\LeavePolicyProfile;
use App\Models\LeavePolicyProfileRule;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveProfileSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure necessary Leave Types exist
        $cl    = LeaveType::firstOrCreate(['code' => 'CL'], ['name' => 'Casual Leave']);
        $pl    = LeaveType::firstOrCreate(['code' => 'PL'], ['name' => 'Paid Leave']);
        $coff  = LeaveType::firstOrCreate(['code' => 'COFF'], ['name' => 'Compensatory Off']);
        $shl   = LeaveType::firstOrCreate(['code' => 'SHL'], ['name' => 'Short Leave', 'is_short_leave' => true]);

        // 1. Alternate Saturday Off
        $altSat = LeavePolicyProfile::create([
            'name' => 'Alternate Saturday Off',
            'description' => '2nd and 4th Saturday off. Short leave allowed.',
            'saturday_off_config' => ['2', '4'],
        ]);

        LeavePolicyProfileRule::create([
            'profile_id' => $altSat->id,
            'leave_type_id' => $shl->id,
            'is_applicable' => true,
            'short_leave_hours' => 2.0,
            'short_leave_per_month' => 1,
        ]);

        // 2. All Saturday Off
        $allSat = LeavePolicyProfile::create([
            'name' => 'All Saturday Off',
            'description' => 'Every Saturday off. COFF must be redeemed in same month.',
            'saturday_off_config' => ['all'],
        ]);

        LeavePolicyProfileRule::create([
            'profile_id' => $allSat->id,
            'leave_type_id' => $coff->id,
            'is_applicable' => true,
            'redeem_in_same_month' => true,
        ]);

        // 3. Regular Leave
        $regular = LeavePolicyProfile::create([
            'name' => 'Regular Leave',
            'description' => 'Internal office staff. No Saturday off. Carry forward PL.',
            'saturday_off_config' => [],
        ]);

        LeavePolicyProfileRule::create([
            'profile_id' => $regular->id,
            'leave_type_id' => $pl->id,
            'is_applicable' => true,
            'max_per_month' => 1,
            'is_carry_forward' => true,
            'tenure_tiers' => [
                ['months' => 5, 'consecutive' => 1], // Default or assumed
                ['months' => 24, 'consecutive' => 3],
            ]
        ]);
    }
}
