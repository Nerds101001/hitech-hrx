<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixRoleNames extends Command
{
    protected $signature = 'roles:fix';
    protected $description = 'Rename office_employee to hr and list all roles';

    public function handle(): void
    {
        $this->info('=== Current roles ===');
        $roles = DB::table('roles')->get();
        foreach ($roles as $role) {
            $this->line("  [{$role->id}] {$role->name} (guard: {$role->guard_name})");
        }

        $officeRole = DB::table('roles')->where('name', 'office_employee')->first();
        $hrRole     = DB::table('roles')->where('name', 'hr')->first();

        if (!$officeRole) {
            $this->warn("No role named 'office_employee' found - may already be fixed.");
        } elseif ($hrRole) {
            // 'hr' already exists - migrate model_has_roles references then delete old role
            $this->warn("'hr' role already exists (id={$hrRole->id}). Migrating users from office_employee (id={$officeRole->id}) to hr...");
            DB::table('model_has_roles')
                ->where('role_id', $officeRole->id)
                ->update(['role_id' => $hrRole->id]);
            DB::table('roles')->where('id', $officeRole->id)->delete();
            $this->info("Migrated all users from 'office_employee' to 'hr' and deleted old role.");
        } else {
            // Safe to rename
            DB::table('roles')->where('id', $officeRole->id)->update(['name' => 'hr']);
            $this->info("Renamed 'office_employee' -> 'hr'.");
        }

        // Clear spatie permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->info('Permission cache cleared.');

        $this->info('=== Updated roles ===');
        $roles = DB::table('roles')->get();
        foreach ($roles as $role) {
            $this->line("  [{$role->id}] {$role->name}");
        }
    }
}
