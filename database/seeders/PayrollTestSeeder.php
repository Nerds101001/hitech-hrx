<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Payslip;
use App\Models\PayrollRecord;
use App\Models\PayrollCycle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PayrollTestSeeder extends Seeder
{
    public function run(): void
    {
        // Try to find the admin by email, then by code, then fallback to first user
        $admin = User::where('email', 'admin@demo.com')->first();
        if (!$admin) {
            $admin = User::where('code', 'DEMO-ADMIN')->first();
        }
        if (!$admin) {
            $admin = User::first();
        }

        if ($admin) {
            $this->command->info("Testing with user: {$admin->email}");

            // Update compliance fields
            $admin->update([
                'pf_no' => 'MH/BAN/0012345/000/0006789',
                'esi_no' => '31000654321001001',
                'uan_no' => '100987654321',
                'pan_no' => 'ABCDE1234F',
                'aadhaar_no' => '1234 5678 9012',
                'date_of_joining' => '2023-01-15',
            ]);

            // Create a Payroll Cycle if none exists
            $cycle = PayrollCycle::firstOrCreate(
                ['name' => 'Monthly Cycle'],
                ['frequency' => 'monthly', 'status' => 'active']
            );

            // Create a Payroll Record
            $record = PayrollRecord::create([
                'user_id' => $admin->id,
                'payroll_cycle_id' => $cycle->id,
                'period' => 'January 2026',
                'basic_salary' => 68900,
                'gross_salary' => 137800,
                'net_salary' => 137600,
                'status' => 'paid',
                'tenant_id' => $admin->tenant_id,
            ]);

            // Create a Payslip with a unique code
            Payslip::create([
                'user_id' => $admin->id,
                'payroll_record_id' => $record->id,
                'code' => 'TEST-' . strtoupper(Str::random(8)),
                'basic_salary' => 68900,
                'total_deductions' => 200,
                'total_benefits' => 68900,
                'net_salary' => 137600,
                'status' => 'delivered',
                'total_worked_days' => 26,
                'total_working_days' => 31,
                'total_weekends' => 4,
                'total_holidays' => 1,
                'tenant_id' => $admin->tenant_id,
                'created_by_id' => $admin->id,
            ]);

            $this->command->info('Payroll Test Data Seeded for ' . $admin->email);
        } else {
            $this->command->error('No user found to seed payroll data.');
        }
    }
}
