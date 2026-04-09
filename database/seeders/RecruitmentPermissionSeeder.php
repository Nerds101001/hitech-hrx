<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class RecruitmentPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'Manage Job',
            'Create Job',
            'Edit Job',
            'Delete Job',
            'Show Job',
            'Manage Job Application',
            'Create Job Application',
            'Edit Job Application',
            'Delete Job Application',
            'Show Job Application',
            'Move Job Application',
            'Add Job Application Skill',
            'Add Job Application Note',
            'Manage Job Category',
            'Create Job Category',
            'Edit Job Category',
            'Delete Job Category',
            'Manage Job Stage',
            'Create Job Stage',
            'Edit Job Stage',
            'Delete Job Stage',
            'Manage Custom Question',
            'Create Custom Question',
            'Edit Custom Question',
            'Delete Custom Question',
            'Manage Interview Schedule',
            'Create Interview Schedule',
            'Edit Interview Schedule',
            'Delete Interview Schedule',
            'Manage Job OnBoard',
            'Create Job OnBoard',
            'Edit Job OnBoard',
            'Delete Job OnBoard',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Assign all recruitment permissions to the admin, hr, and manager role if it exists
        $rolesToAssign = ['admin', 'hr', 'manager'];
        foreach ($rolesToAssign as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        $this->command->info('✅ Recruitment permissions seeded and assigned to admin!');
    }
}
