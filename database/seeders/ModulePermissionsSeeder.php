<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;

class ModulePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Clear cached permissions
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Do NOT delete existing permissions to preserve custom user setups
        // Permission::query()->delete();

        $registry = config('permissions_registry');
        $createdCount = 0;

        if (!$registry) {
            $this->command->error("Registry not found. Did you clear the config cache?");
            return;
        }

        foreach ($registry as $module => $permissions) {
            foreach ($permissions as $permName => $humanLabel) {
                Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
                $createdCount++;
            }
        }

        $this->command->info("Advanced Business Permissions seeded successfully! Total loaded: {$createdCount}");
    }
}
