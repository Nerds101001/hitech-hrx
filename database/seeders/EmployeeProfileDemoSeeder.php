<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Designation;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\PayrollAdjustment;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Enums\UserAccountStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class EmployeeProfileDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Find the target demo employee
        $user = User::where('email', 'employee@rustx.com')->first();

        if (!$user) {
            $this->command->error('Demo employee "employee@rustx.com" not found. Please run DemoSeeder first.');
            return;
        }

        $this->command->info('Seeding demo data for: ' . $user->getFullName());

        // 2. Update Basic and Personal Info
        $user->update([
            'dob' => '1995-05-15',
            'gender' => 'male',
            'marital_status' => 'married',
            'spouse_name' => 'Jane Doe',
            'father_name' => 'John Doe Sr.',
            'mother_name' => 'Mary Doe',
            'blood_group' => 'O+',
            'birth_country' => 'India',
            'citizenship' => 'Indian',
            'date_of_joining' => Carbon::now()->subYears(2),
            'ctc_offered' => 750000,
            'base_salary' => 62500,
            'probation_end_date' => Carbon::now()->subYears(1),
            'probation_confirmed_at' => Carbon::now()->subYears(1),
            'status' => UserAccountStatus::ACTIVE,
            'passport_no' => 'Z1234567',
            'passport_expiry_date' => Carbon::now()->addYears(5),
            'visa_type' => 'Work Permit',
            'visa_expiry_date' => Carbon::now()->addYears(2),
        ]);

        // 3. Update Contact Info
        $user->update([
            'alternate_number' => '9876543210',
            'emergency_contact_name' => 'John Doe Sr.',
            'emergency_contact_relation' => 'Father',
            'emergency_contact_phone' => '9876543210',
            'temp_building' => 'Apt 4B, Blue Sky Apartments',
            'temp_street' => 'MG Road',
            'temp_city' => 'Bangalore',
            'temp_state' => 'Karnataka',
            'temp_zip' => '560001',
            'temp_country' => 'India',
            'perm_building' => '123, Green Valley',
            'perm_street' => 'Palm Avenue',
            'perm_city' => 'Pune',
            'perm_state' => 'Maharashtra',
            'perm_zip' => '411001',
            'perm_country' => 'India',
        ]);

        // 4. Seeding Bank Account
        BankAccount::updateOrCreate(
            ['user_id' => $user->id],
            [
                'bank_name' => 'HDFC Bank',
                'bank_code' => 'HDFC0001234',
                'account_name' => $user->getFullName(),
                'account_number' => '50100234567890',
                'branch_name' => 'Koramangala Branch',
                'tenant_id' => $user->tenant_id,
                'created_by_id' => $user->id,
            ]
        );

        // 5. Seeding Payroll Adjustments
        // Remove existing to avoid duplicates if any
        PayrollAdjustment::where('user_id', $user->id)->delete();

        PayrollAdjustment::create([
            'name' => 'House Rent Allowance',
            'code' => 'HRA',
            'type' => 'benefit',
            'applicability' => 'individual',
            'percentage' => 25,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        PayrollAdjustment::create([
            'name' => 'Provident Fund',
            'code' => 'PF',
            'type' => 'deduction',
            'applicability' => 'individual',
            'amount' => 1800,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        PayrollAdjustment::create([
            'name' => 'Professional Tax',
            'code' => 'PT',
            'type' => 'deduction',
            'applicability' => 'individual',
            'amount' => 200,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        // 6. Seeding Tasks (KPIs)
        Task::where('user_id', $user->id)->delete();

        Task::create([
            'title' => 'Complete Annual Security Audit',
            'description' => 'Review all server logs and update security protocols for the upcoming quarter.',
            'user_id' => $user->id,
            'assigned_by_id' => $user->reporting_to_id ?? 1,
            'due_date' => Carbon::now()->addDays(5),
            'status' => 'new',
            'tenant_id' => $user->tenant_id,
        ]);

        Task::create([
            'title' => 'Update Client Dashboard UI',
            'description' => 'Implement the new design system for the client portal analytics page.',
            'user_id' => $user->id,
            'assigned_by_id' => $user->reporting_to_id ?? 1,
            'due_date' => Carbon::now()->addDays(2),
            'status' => 'in_progress',
            'tenant_id' => $user->tenant_id,
        ]);

        Task::create([
            'title' => 'Q4 Performance Review Preparation',
            'description' => 'Consolidate team metrics and prepare presentation for the management board.',
            'user_id' => $user->id,
            'assigned_by_id' => $user->reporting_to_id ?? 1,
            'due_date' => Carbon::now()->subDays(1),
            'status' => 'completed',
            'tenant_id' => $user->tenant_id,
        ]);

        // 7. Seeding some approved Document Requests
        DocumentRequest::where('user_id', $user->id)->delete();
        
        $docType = DocumentType::first() ?? DocumentType::create(['name' => 'ID Card', 'code' => 'ID', 'tenant_id' => $user->tenant_id]);

        DocumentRequest::create([
            'remarks' => 'Aadhar Card',
            'document_type_id' => $docType->id,
            'user_id' => $user->id,
            'status' => 'approved',
            'action_taken_at' => Carbon::now(),
            'tenant_id' => $user->tenant_id,
        ]);

        DocumentRequest::create([
            'remarks' => 'PAN Card',
            'document_type_id' => $docType->id,
            'user_id' => $user->id,
            'status' => 'approved',
            'action_taken_at' => Carbon::now(),
            'tenant_id' => $user->tenant_id,
        ]);

        $this->command->info('Demo data seeded successfully for ' . $user->getFullName());
    }
}
