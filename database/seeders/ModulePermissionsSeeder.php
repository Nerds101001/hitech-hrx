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
        $json = file_get_contents(resource_path('menu/tenantVerticalMenu.json'));
        $data = json_decode($json, true);
        $menus = $data['menu'] ?? $data;
        
        $actions = ['view', 'create', 'edit', 'delete'];
        $createdCount = 0;

        foreach ($menus as $menu) {
            if (isset($menu['name'])) {
                $slug = Str::slug($menu['name']);
                foreach ($actions as $action) {
                    $permName = "{$slug}.{$action}";
                    Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
                    $createdCount++;
                }

                if (isset($menu['submenu'])) {
                    foreach ($menu['submenu'] as $submenu) {
                        if (isset($submenu['name'])) {
                            $subSlug = Str::slug($submenu['name']);
                            foreach ($actions as $action) {
                                $subPermName = "{$subSlug}.{$action}";
                                Permission::firstOrCreate(['name' => $subPermName, 'guard_name' => 'web']);
                                $createdCount++;
                            }
                        }
                    }
                }
            }
        }

        $this->command->info("Granular permissions generated successfully! Total evaluated/created: {$createdCount}");
    }
}
