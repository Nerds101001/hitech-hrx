<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\KpiController;
use App\Models\Department;
use App\Models\KpiAssignment;
use App\Models\KpiTemplate;
use App\Models\User;
use App\Services\KpiTemplateService;
use App\Support\Nav;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Performance group of the sidebar was five near-identical entries; it is
 * now four, in the order the work is done. These tests pin what each kind of
 * person is shown, and that the per-employee page (no longer in the menu)
 * still opens and keeps its script hooks after being re-skinned.
 */
class KpiPerformanceMenuTest extends TestCase
{
    use DatabaseTransactions;

    private function performance(): array
    {
        $menu = json_decode(file_get_contents(base_path('resources/menu/tenantVerticalMenu.json')), true)['menu'];
        foreach ($menu as $item) {
            if (($item['name'] ?? null) === 'Performance') {
                return $item;
            }
        }
        $this->fail('Performance menu group is missing');
    }

    private function person(?string $role, ?Department $dept = null): User
    {
        static $n = 0;
        $n++;
        $u = User::create(['first_name' => 'QA', 'last_name' => "Menu {$n}", 'email' => 'qamenu' . uniqid() . '@example.test',
            'code' => 'QAMN' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
            'department_id' => optional($dept)->id]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u->fresh();
    }

    /** Mirrors the sidebar: the item's own roles, then whether the route would let them in. */
    private function visibleFor(User $user): array
    {
        Nav::flush();
        $names = [];
        foreach ($this->performance()['submenu'] as $sub) {
            $mine = array_map('strtolower', $user->roles->pluck('name')->all());
            if (isset($sub['roles']) && !array_intersect($mine, array_map('strtolower', $sub['roles']))) {
                continue;
            }
            if (!Nav::allows($sub['url'], $user)) {
                continue;
            }
            $names[] = $sub['name'];
        }
        return $names;
    }

    public function test_the_group_is_four_entries_in_working_order(): void
    {
        $names = array_column($this->performance()['submenu'], 'name');

        // Bulk KPI Entry joined the group with the data-owner feature (535b948d8).
        $this->assertSame(['KPI / KRA Setup', 'KPI Approvals', 'Scorecards', 'Bulk KPI Entry'], $names);
        $urls = array_column($this->performance()['submenu'], 'url');
        $this->assertNotContains('/kpi/manage', $urls, 'the per-employee page opens from Setup, not the sidebar');
    }

    public function test_ninety_day_goals_is_its_own_entry_for_the_sales_department(): void
    {
        $menu = json_decode(file_get_contents(base_path('resources/menu/tenantVerticalMenu.json')), true)['menu'];
        $entry = collect($menu)->firstWhere('name', '90-Day Goals');

        $this->assertNotNull($entry, 'the tracker is still in the menu');
        // A group of its own since the 90-Day sidebar split (salespeople: My Dashboard /
        // My Programs; admin: Dashboard / New / Past), no longer a single link.
        $this->assertNotEmpty($entry['submenu'] ?? [], 'a group, not a single link');
        $urls = array_column($entry['submenu'], 'url');
        $this->assertContains('/performance/ninety-day-goals/my-dashboard', $urls);
        $this->assertSame('Sales', $entry['department'], 'shown to the Sales department; admin and HR always see department entries');
        $this->assertArrayNotHasKey('roles', $entry, 'not limited to admin and HR any more');
        $this->assertEmpty(array_filter(array_column($this->performance()['submenu'], 'url'), fn ($u) => str_starts_with((string) $u, '/performance/ninety-day-goals')), 'and not listed twice');
    }

    public function test_setup_stays_highlighted_while_the_per_employee_page_is_open(): void
    {
        $setup = $this->performance()['submenu'][0];

        $this->assertContains('tenant.kpi.templates', (array) $setup['slug']);
        $this->assertContains('tenant.kpi.manage', (array) $setup['slug']);
    }

    public function test_admin_sees_all_four(): void
    {
        $this->assertSame(['KPI / KRA Setup', 'KPI Approvals', 'Scorecards', 'Bulk KPI Entry'], $this->visibleFor($this->person('admin')));
    }

    public function test_a_named_data_owner_sees_bulk_entry(): void
    {
        $dept = Department::create(['name' => 'ZZ Owner ' . uniqid(), 'code' => 'ZZD' . random_int(1000, 9999)]);
        $owner = $this->person('manager', $dept);
        $t = KpiTemplate::create(['title' => 'ZZ Owned KPI', 'category' => 'KPI', 'department_id' => $dept->id, 'frequency' => 'monthly',
            'scoring_type' => 'target', 'max_marks' => 10, 'target_value' => 100, 'data_source' => 'manual', 'is_active' => true]);
        \App\Models\KpiDataOwner::create(['user_id' => $owner->id, 'kpi_template_id' => $t->id]);

        $this->assertContains('Bulk KPI Entry', $this->visibleFor($owner));
    }

    public function test_an_ordinary_manager_sees_none_of_it(): void
    {
        $this->assertSame([], $this->visibleFor($this->person('manager')));
    }

    public function test_a_named_approver_sees_only_approvals(): void
    {
        $dept = Department::create(['name' => 'ZZ Menu ' . uniqid(), 'code' => 'ZZM' . random_int(1000, 9999)]);
        $gm = $this->person('manager', $dept);
        $staff = $this->person(null, $dept);
        $t = KpiTemplate::create(['title' => 'ZZ KPI', 'category' => 'KPI', 'department_id' => $dept->id, 'frequency' => 'monthly',
            'scoring_type' => 'target', 'max_marks' => 10, 'target_value' => 100, 'data_source' => 'approval', 'approver_id' => $gm->id, 'is_active' => true]);
        KpiTemplateService::apply($t);
        $this->assertTrue(KpiAssignment::where('user_id', $staff->id)->exists());

        $this->assertSame(['KPI Approvals'], $this->visibleFor($gm));
    }

    public function test_the_per_employee_page_still_opens_and_keeps_every_script_hook(): void
    {
        Mail::fake();
        View::share('errors', new \Illuminate\Support\ViewErrorBag());
        $dept = Department::create(['name' => 'ZZ Ops ' . uniqid(), 'code' => 'ZZO' . random_int(1000, 9999)]);
        $employee = $this->person(null, $dept);
        $admin = $this->person('admin');

        Auth::setUser($admin);
        $req = Request::create('/kpi/manage?user_id=' . $employee->id, 'GET', ['user_id' => $employee->id]);
        $req->setUserResolver(fn () => $admin);
        $html = app(KpiController::class)->manage($req)->render();

        $this->assertStringContainsString('Per-employee KPI / KRA', $html);
        $this->assertStringContainsString('layout-full-width', $html, 'themed page, not the stock container');
        foreach (['class="card mb-4"', 'btn-label-secondary', 'badge bg-label-success', 'badge bg-label-info'] as $stock) {
            $this->assertStringNotContainsString($stock, $html, "stock Bootstrap leftover: $stock");
        }
        foreach (['id="kpiModal"', 'id="evalModal"', 'id="kpiForm"', 'id="evalForm"', 'add-from-routine', 'name="user_id"', 'kpi_user_id'] as $hook) {
            $this->assertStringContainsString($hook, $html, $hook);
        }
        $this->assertStringContainsString(route('tenant.kpi.templates'), $html, 'a way back to Setup');
    }
}
