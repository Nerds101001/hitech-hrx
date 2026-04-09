<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use App\Models\LeavePolicyProfile;
use App\Models\LeavePolicyProfileRule;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeavePolicyTestSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure core leave types exist
        $leaveTypes = [
            ['name' => 'Monthly Paid Leave', 'code' => 'PL', 'is_paid' => true],
            ['name' => 'Sick Leave', 'code' => 'SL', 'is_paid' => true],
            ['name' => 'Compensatory Off', 'code' => 'COFF', 'is_paid' => true],
            ['name' => 'Short Leave', 'code' => 'SHORT', 'is_paid' => true, 'is_short_leave' => true],
        ];

        $typeIds = [];
        foreach ($leaveTypes as $data) {
            $type = LeaveType::updateOrCreate(['code' => $data['code']], $data);
            $typeIds[$data['code']] = $type->id;
        }

        // 2. Create "Alternate Saturday Off" Profile
        $altSat = LeavePolicyProfile::updateOrCreate(
            ['name' => 'Alternate Saturday Off'],
            [
                'description' => 'User gets alternate Saturday off. No paid leave, but has COFF and Short Leave.',
                'saturday_off_config' => ['2', '4'], // 2nd and 4th Saturday
            ]
        );

        // Rules for Alt Sat
        // COFF is applicable
        LeavePolicyProfileRule::updateOrCreate(
            ['profile_id' => $altSat->id, 'leave_type_id' => $typeIds['COFF']],
            ['is_applicable' => true, 'is_carry_forward' => true]
        );
        // Short Leave: 2 hours, once a month
        LeavePolicyProfileRule::updateOrCreate(
            ['profile_id' => $altSat->id, 'leave_type_id' => $typeIds['SHORT']],
            [
                'is_applicable' => true,
                'short_leave_hours' => 2,
                'short_leave_per_month' => 1
            ]
        );

        // 3. Create "Regular Leave" Profile
        $regular = LeavePolicyProfile::updateOrCreate(
            ['name' => 'Regular Leave'],
            [
                'description' => 'No Saturdays off. 1 PL per month. Carry forward. Tenure rule: 3 consecutive after 5 months.',
                'saturday_off_config' => [],
            ]
        );

        // Rules for Regular
        LeavePolicyProfileRule::updateOrCreate(
            ['profile_id' => $regular->id, 'leave_type_id' => $typeIds['PL']],
            [
                'is_applicable' => true,
                'max_per_month' => 1,
                'is_carry_forward' => true,
                'tenure_tiers' => [
                    ['months' => 5, 'consecutive' => 3]
                ]
            ]
        );

        $this->command->info('Leave Policy Test Seeder completed.');
    }
}
