<?php

namespace App\Console\Commands;

use App\Enums\UserAccountStatus;
use App\Models\Department;
use App\Models\Designation;
use App\Models\LeavePolicyProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UpdateSidUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:update-sid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update sid@rustx.com user with specific details';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::beginTransaction();
        
        try {
            $this->info('Starting user update for sid@rustx.com...');

            // Find or create the user — email stored as Sid@rustx.com (capital S)
            $user = User::withoutGlobalScopes()
                ->whereRaw('LOWER(email) = ?', ['sid@rustx.com'])
                ->first();
            
            if (!$user) {
                $this->error('User sid@rustx.com not found!');
                $this->info('Creating new user...');
                
                $user = new User();
                $user->email = 'sid@rustx.com';
                $user->first_name = 'Sid';
                $user->last_name = 'Rustx';
                $user->name = 'Sid Rustx';
                $user->password = Hash::make('Hitech@2026!'); // Default password
            }

            // Find Admin department
            $department = Department::withoutGlobalScopes()
                ->where('name', 'LIKE', '%Admin%')
                ->orWhere('name', 'LIKE', '%admin%')
                ->first();
            
            if (!$department) {
                $this->warn('Admin department not found. Creating it...');
                $department = Department::create([
                    'name' => 'Admin',
                    'code' => 'ADMIN',
                    'status' => 'active'
                ]);
            }
            
            $this->info("Department: {$department->name} (ID: {$department->id})");

            // Find Director designation
            $designation = Designation::withoutGlobalScopes()
                ->where('name', 'LIKE', '%Director%')
                ->orWhere('name', 'LIKE', '%director%')
                ->first();
            
            if (!$designation) {
                $this->warn('Director designation not found. Creating it...');
                $designation = Designation::create([
                    'name' => 'Director',
                    'code' => 'DIR',
                    'department_id' => $department->id,
                    'status' => 'active'
                ]);
            }
            
            $this->info("Designation: {$designation->name} (ID: {$designation->id})");

            // Find Ludhiana leave policy
            $leavePolicy = LeavePolicyProfile::withoutGlobalScopes()
                ->whereRaw('LOWER(name) LIKE ?', ['%ludhiana%'])
                ->first();
            
            if (!$leavePolicy) {
                // List all available policies for info
                $allPolicies = LeavePolicyProfile::withoutGlobalScopes()->pluck('name', 'id')->toArray();
                $this->warn('Ludhiana leave policy not found.');
                $this->warn('Available policies: ' . implode(', ', $allPolicies));
                $leavePolicy = null;
            } else {
                $this->info("Leave Policy: {$leavePolicy->name} (ID: {$leavePolicy->id})");
            }

            // Update user details
            $user->department_id = $department->id;
            $user->designation_id = $designation->id;
            $user->leave_policy_profile_id = $leavePolicy ? $leavePolicy->id : null;
            $user->reporting_to_id = null; // Not set as per requirement
            $user->work_type = 'office';   // on-site = 'office' in DB enum
            $user->date_of_joining = '2012-01-01'; // Date of joining: 01-01-2012
            $user->attendance_type = 'open'; // anywhere = 'open' in DB enum
            $user->status = UserAccountStatus::ACTIVE;
            
            $user->save();
            
            $this->info("User updated successfully!");

            // Assign Admin role
            $adminRole = Role::where('name', 'admin')
                ->orWhere('name', 'Admin')
                ->first();
            
            if (!$adminRole) {
                $this->warn('Admin role not found. Creating it...');
                $adminRole = Role::create([
                    'name' => 'admin',
                    'guard_name' => 'web'
                ]);
            }
            
            $user->syncRoles([$adminRole->name]);
            $this->info("Admin role assigned to user!");

            DB::commit();
            
            $this->info('');
            $this->info('✅ User update completed successfully!');
            $this->info('');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Email', $user->email],
                    ['Name', $user->name],
                    ['Role', 'Admin'],
                    ['Department', $department->name],
                    ['Designation', $designation->name],
                    ['Leave Policy', $leavePolicy ? $leavePolicy->name : 'Not Set'],
                    ['Reporting To', 'Not Set'],
                    ['Work Type', $user->work_type],
                    ['Date of Joining', $user->date_of_joining],
                    ['Attendance Type', $user->attendance_type],
                    ['Status', $user->status->value ?? $user->status],
                ]
            );
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error updating user: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
