<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Team;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ExpenseRequest;
use App\Models\ExpenseType;
use App\Models\SosRecord;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DemoManagerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. Ensure a "Manager" Role exists (assuming spatie/laravel-permission or similar)
        // For Payr, roles are usually assigned. Let's create the manager.
        $managerEmail = 'manager@hitechgroup.in';
        $manager = User::where('email', $managerEmail)->first();
        if (!$manager) {
            $manager = User::create([
                'email' => $managerEmail,
                'code' => 'MGR-' . rand(1000, 9999),
                'first_name' => 'Demo',
                'last_name' => 'Manager',
                'password' => Hash::make('lalos'), // User requested password
                'status' => \App\Enums\UserAccountStatus::ACTIVE,
                'phone' => '999' . rand(1000000, 9999999),
                'gender' => 'Male',
            ]);
        } else {
            $manager->update(['password' => Hash::make('lalos')]);
        }

        // Assign Role (assuming standard Spatie implementation)
        if ($manager && ! $manager->hasRole('manager')) {
             $manager->assignRole('manager');
        }

        // 2. Create a Team for this Manager
        $team = Team::firstOrCreate(
            ['name' => 'Demo Manager Team'],
            [
                'description' => 'A team created for demoing the manager dashboard.',
                'manager_id' => $manager->id,
            ]
        );

        // 3. Create 3-4 Employee Users and assign them to the Manager's Team
        $employees = [];
        for ($i = 1; $i <= 4; $i++) {
            $empEmail = "team_member{$i}@hitechgroup.in";
            $employee = User::where('email', $empEmail)->first();
            if (!$employee) {
                $employee = User::create([
                    'email' => $empEmail,
                    'code' => "TM-" . rand(1000, 9999) . "-{$i}",
                    'first_name' => 'Team',
                    'last_name' => "Member {$i}",
                    'password' => Hash::make('12345678'),
                    'status' => \App\Enums\UserAccountStatus::ACTIVE,
                    'team_id' => $team->id,
                    'phone' => '888' . rand(100000, 999999) . $i,
                ]);
            }
            // Assign roles
            if ($employee && ! $employee->hasRole('employee')) {
                $employee->assignRole('employee');
            }
            $employees[] = $employee;
        }

        // 4. Create Leave Types (if they don't exist)
        $sickLeave = LeaveType::firstOrCreate(['name' => 'Sick Leave'], ['status' => 1]);
        $casualLeave = LeaveType::firstOrCreate(['name' => 'Casual Leave'], ['status' => 1]);

        // 5. Seed Leave Requests
        // 5a. Pending Requests representing action needed by manager
        foreach (array_slice($employees, 0, 2) as $employee) {
            LeaveRequest::create([
                'user_id' => $employee->id,
                'leave_type_id' => $casualLeave->id,
                'from_date' => Carbon::now()->addDays(2)->format('Y-m-d'),
                'to_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'reason' => 'Family event',
                'status' => 'Pending',
                'days' => 2,
            ]);
        }

        // 5b. Approved Requests representing "Team Availability (Out Today)"
        LeaveRequest::create([
            'user_id' => $employees[2]->id,
            'leave_type_id' => $sickLeave->id,
            'from_date' => Carbon::now()->format('Y-m-d'), // Today
            'to_date' => Carbon::now()->format('Y-m-d'),   // Today
            'reason' => 'Not feeling well today',
            'status' => 'Approved',
            'days' => 1,
        ]);

        LeaveRequest::create([
            'user_id' => $employees[3]->id,
            'leave_type_id' => $casualLeave->id,
            'from_date' => Carbon::now()->subDays(1)->format('Y-m-d'),
            'to_date' => Carbon::now()->addDays(1)->format('Y-m-d'), // Spans today
            'reason' => 'Pre-planned off',
            'status' => 'Approved',
            'days' => 3,
        ]);

        // 6. Seed Expense Requests
        $expenseType = ExpenseType::firstOrCreate(['name' => 'Travel'], ['status' => 1]);
        
        foreach (array_slice($employees, 1, 2) as $employee) {
            ExpenseRequest::create([
                'user_id' => $employee->id,
                'expense_type_id' => $expenseType->id,
                'amount' => rand(500, 2000),
                'date' => Carbon::now()->subDays(1)->format('Y-m-d'),
                'description' => 'Client meeting travel',
                'status' => 'Pending',
            ]);
        }

        // 7. Seed Manager's "Personal Stats"
        // Manager's own leave
        LeaveRequest::create([
            'user_id' => $manager->id,
            'leave_type_id' => $sickLeave->id,
            'from_date' => Carbon::now()->subMonths(1)->format('Y-m-d'),
            'to_date' => Carbon::now()->subMonths(1)->format('Y-m-d'),
            'reason' => 'Fever',
            'status' => 'Approved',
            'days' => 1,
        ]);

        // Manager's own expenses
        ExpenseRequest::create([
            'user_id' => $manager->id,
            'expense_type_id' => $expenseType->id,
            'amount' => 5000,
            'date' => Carbon::now()->subDays(10)->format('Y-m-d'),
            'description' => 'Quarterly meetup',
            'status' => 'Approved',
        ]);

        // Manager's own SOS alerts
        SosRecord::create([
            'user_id' => $manager->id,
            'latitude' => '28.7041',
            'longitude' => '77.1025',
            'message' => 'Test emergency alert',
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subDays(2),
        ]);

        $this->command->info("Manager Data Seeded Successfully! Login: manager@hitechgroup.in / 12345678");
    }
}
