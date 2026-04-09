<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $this->command->info('🔧 Seeding comprehensive permissions...');

    // Clear existing permissions
    DB::table('permissions')->delete();

    $permissions = [
      // Super Admin Permissions
      ['name' => 'admin.dashboard.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'admin.tenants.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'admin.users.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'admin.roles.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'admin.addons.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],

      // HR Permissions
      ['name' => 'hr.dashboard.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.employees.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.employees.create', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.employees.edit', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.employees.delete', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.attendance.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.attendance.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.leaves.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.leaves.approve', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.expenses.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.expenses.approve', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.departments.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.teams.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'hr.settings.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],

      // Manager Permissions
      ['name' => 'manager.dashboard.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'manager.team.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'manager.attendance.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'manager.leaves.approve', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'manager.expenses.approve', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],

      // Employee Permissions
      ['name' => 'employee.dashboard.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'employee.attendance.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'employee.leaves.create', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'employee.expenses.create', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'employee.profile.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'employee.payroll.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'employee.sos.create', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],

      // Addon-based Permissions
      ['name' => 'payroll.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'recruitment.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'training.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'performance.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
      ['name' => 'calendar.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
    ];

    // Insert permissions in batches
    foreach (array_chunk($permissions, 10) as $chunk) {
      Permission::insert($chunk);
    }

    $this->command->info('✅ Permissions seeded successfully!');
    $this->command->info('📊 Total permissions: ' . count($permissions));
  }
}
