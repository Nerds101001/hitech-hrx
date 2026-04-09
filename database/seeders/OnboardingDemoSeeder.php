<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\User;
use App\Models\Team;
use App\Models\Designation;
use App\Models\BankAccount;
use App\Enums\UserAccountStatus;
use App\Enums\Gender;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class OnboardingDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::first() ?? Team::create(['name' => 'IT Department', 'code' => 'IT']);
        $designation = Designation::first() ?? Designation::create(['name' => 'Software Engineer']);

        // 1. User: Just Invited (Onboarding)
        $user1 = User::updateOrCreate(
            ['email' => 'rahul.onboarding@example.com'],
            [
                'first_name' => 'Rahul',
                'last_name' => 'Sharma',
                'code' => 'DEVOL-RAHUL',
                'phone' => '9876543210',
                'password' => Hash::make('password'),
                'status' => UserAccountStatus::ONBOARDING,
                'team_id' => $team->id,
                'designation_id' => $designation->id,
                'onboarding_at' => now(),
                'onboarding_deadline' => now()->addDays(3),
            ]
        );
        $user1->syncRoles(['field_employee']);

        // 2. User: Resubmission Requested
        $user2 = User::updateOrCreate(
            ['email' => 'priya.onboarding@example.com'],
            [
                'first_name' => 'Priya',
                'last_name' => 'Patel',
                'code' => 'DEVOL-PRIYA',
                'phone' => '9876543211',
                'password' => Hash::make('password'),
                'status' => UserAccountStatus::ONBOARDING,
                'team_id' => $team->id,
                'designation_id' => $designation->id,
                'onboarding_at' => now()->subDays(1),
                'onboarding_deadline' => now()->addDays(2),
                'onboarding_resubmission_notes' => 'Please upload a clearer copy of your PAN card.',
                'father_name' => 'Rajesh Patel',
                'mother_name' => 'Sunita Patel',
                'gender' => Gender::FEMALE->value,
                'marital_status' => 'single',
                'blood_group' => 'B+',
            ]
        );
        $user2->syncRoles(['field_employee']);

        // 3. User: Submitted (Under Review)
        $user3 = User::updateOrCreate(
            ['email' => 'amit.onboarding@example.com'],
            [
                'first_name' => 'Amit',
                'last_name' => 'Verma',
                'code' => 'DEVOL-AMIT',
                'phone' => '9876543212',
                'password' => Hash::make('password'),
                'status' => UserAccountStatus::ONBOARDING_SUBMITTED,
                'team_id' => $team->id,
                'designation_id' => $designation->id,
                'onboarding_at' => now()->subDays(2),
                'onboarding_deadline' => now()->addDays(1),
                'onboarding_completed_at' => now()->subHours(5),
                'father_name' => 'Suresh Verma',
                'mother_name' => 'Kavita Verma',
                'gender' => Gender::MALE->value,
                'marital_status' => 'married',
                'spouse_name' => 'Anjali Verma',
                'blood_group' => 'O+',
                'perm_street' => '123 Hitech Lane',
                'perm_city' => 'Bangalore',
                'perm_state' => 'Karnataka',
                'perm_zip' => '560001',
                'perm_country' => 'India',
                'aadhaar_no' => '123456789012',
                'pan_no' => 'ABCDE1234F',
                'consent_accepted_at' => now()->subHours(5),
            ]
        );
        $user3->syncRoles(['field_employee']);

        // Bank Account for User 3
        BankAccount::updateOrCreate(
            ['user_id' => $user3->id],
            [
                'bank_name' => 'HDFC Bank',
                'account_name' => 'Amit Verma',
                'account_number' => '50100012345678',
                'bank_code' => 'HDFC0000123',
                'branch_name' => 'Koramangala',
            ]
        );
    }
}
