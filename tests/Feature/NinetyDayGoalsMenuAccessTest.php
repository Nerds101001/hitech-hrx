<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Support\Nav;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The 90-Day Goals sidebar section is for the Sales department, but its admin
 * links (Dashboard, New Program, Past Programs) are what "Manage 90-Day Goals"
 * (goals.manage) is for -- and that permission is handed to people outside
 * Sales. The section's department rule used to hide it from them entirely, so a
 * manager given the permission had working pages and no way to reach them.
 * departmentOrPermission on the section lets permission holders through.
 */
class NinetyDayGoalsMenuAccessTest extends TestCase
{
    use DatabaseTransactions;

    private function person(string $dept, string $role): User
    {
        Mail::fake();
        $d = Department::create(['name' => $dept . ' ' . uniqid(), 'code' => 'ZZ' . random_int(10000, 99999)]);
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Menu ' . uniqid(),
            'email' => 'qa' . uniqid() . '@example.test', 'code' => 'QM' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'), 'status' => 'active', 'department_id' => $d->id,
        ]);
        $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $u->fresh();
    }

    /** The links under 90-Day Goals in this person's real sidebar, or null when the section isn't drawn. */
    private function ninetyDayLinks(User $u): ?array
    {
        $this->actingAs($u);
        Nav::flush();
        $html = $this->get('/kpi/my')->getContent();
        Nav::flush();
        // The section heading itself - the bare words also appear in a script comment.
        $p = strpos($html, '<span class="hitech-menu-text">90-Day Goals</span>');
        if ($p === false) {
            return null;
        }
        $ul = strpos($html, '<ul class="hitech-submenu">', $p);
        $end = strpos($html, '</ul>', $ul);
        preg_match_all('/class="hitech-submenu-link"[^>]*>\s*([^<]+?)\s*<\/a>/', substr($html, $ul, $end - $ul), $m);

        return array_map('trim', $m[1]);
    }

    public function test_a_manager_outside_sales_given_goals_manage_sees_the_admin_links(): void
    {
        $u = $this->person('ZZ Customer Excellence', 'manager');
        $u->givePermissionTo(Permission::firstOrCreate(['name' => 'goals.manage', 'guard_name' => 'web']));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Past Programs gave way to the Programs list (2026-10-01).
        $this->assertSame(['Dashboard', 'Programs', 'New Program'], $this->ninetyDayLinks($u->fresh()));
    }

    public function test_the_same_manager_without_the_permission_sees_no_section(): void
    {
        $this->assertNull($this->ninetyDayLinks($this->person('ZZ Customer Excellence', 'manager')));
    }

    public function test_preview_access_agrees_with_the_sidebar(): void
    {
        $u = $this->person('ZZ Customer Excellence', 'manager');
        $u->givePermissionTo(Permission::firstOrCreate(['name' => 'goals.manage', 'guard_name' => 'web']));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $section = collect(Nav::previewMenuFor($u->fresh())['menu'] ?? Nav::previewMenuFor($u->fresh()))
            ->first(fn ($x) => ((array) $x)['name'] === '90-Day Goals');
        $this->assertTrue((bool) ($section['visible'] ?? false), $section['reason'] ?? 'hidden');
    }
}
