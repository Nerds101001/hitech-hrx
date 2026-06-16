<?php

namespace Database\Seeders;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $demos = [
            [
                'first_name'        => 'Demo',
                'last_name'         => 'Admin',
                'email'             => 'admin@demo.com',
                'code'              => 'DEMO-ADMIN-1',
                'password'          => Hash::make('password'),
                'status'            => UserAccountStatus::ACTIVE,
                'email_verified_at' => Carbon::now(),
                'role'              => 'admin',
            ],
            [
                'first_name'        => 'Demo',
                'last_name'         => 'HR',
                'email'             => 'hr@demo.com',
                'code'              => 'DEMO-HR-1',
                'password'          => Hash::make('password'),
                'status'            => UserAccountStatus::ACTIVE,
                'email_verified_at' => Carbon::now(),
                'role'              => 'hr',
            ],
            [
                'first_name'        => 'Demo',
                'last_name'         => 'Employee',
                'email'             => 'emp@demo.com',
                'code'              => 'DEMO-EMP-1',
                'password'          => Hash::make('password'),
                'status'            => UserAccountStatus::ACTIVE,
                'email_verified_at' => Carbon::now(),
                'role'              => 'employee',
            ],
            [
                'first_name'        => 'Demo',
                'last_name'         => 'Manager',
                'email'             => 'manager@demo.com',
                'code'              => 'DEMO-MGR-1',
                'password'          => Hash::make('password'),
                'status'            => UserAccountStatus::ACTIVE,
                'email_verified_at' => Carbon::now(),
                'role'              => 'manager',
                'department_name'   => null,
            ],
            [
                'first_name'        => 'Demo',
                'last_name'         => 'Salesperson',
                'email'             => 'sales@demo.com',
                'code'              => 'DEMO-SLS-1',
                'password'          => Hash::make('password'),
                'status'            => UserAccountStatus::ACTIVE,
                'email_verified_at' => Carbon::now(),
                'role'              => 'employee',
                'department_name'   => 'Sales',
            ],
            [
                'first_name'        => 'Demo',
                'last_name'         => 'CCARE',
                'email'             => 'ccare@demo.com',
                'code'              => 'DEMO-CKR-1',
                'password'          => Hash::make('password'),
                'status'            => UserAccountStatus::ACTIVE,
                'email_verified_at' => Carbon::now(),
                'role'              => 'employee',
                'department_name'   => 'Customer Care',
            ],
        ];

        foreach ($demos as $data) {
            $role = $data['role'];
            $departmentName = $data['department_name'] ?? null;
            
            unset($data['role']);
            unset($data['department_name']);

            if ($departmentName) {
                $dept = \App\Models\Department::firstOrCreate(
                    ['name' => $departmentName],
                    ['code' => strtoupper(substr($departmentName, 0, 3)), 'tenant_id' => 1]
                );
                $data['department_id'] = $dept->id;
            }

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                $data
            );

            // Assign role (sync so it doesn't duplicate)
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            $user->syncRoles([$role]);

            $this->command->info("✅ {$user->email} ({$role}) ready.");
        }

        // Map CCARE to Salesperson
        $ccareUser = User::where('email', 'ccare@demo.com')->first();
        $salesUser = User::where('email', 'sales@demo.com')->first();
        if ($ccareUser && $salesUser) {
            \App\Models\CcSalespersonMap::firstOrCreate([
                'cc_user_id' => $ccareUser->id,
                'sales_user_id' => $salesUser->id,
            ], [
                'tenant_id' => $ccareUser->tenant_id ?? 1
            ]);
            $this->command->info("✅ CCARE mapped to Salesperson.");
        }
    }
}
