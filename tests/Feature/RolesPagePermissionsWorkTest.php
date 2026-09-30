<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Support\Nav;
use App\Support\RoleAssignment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Roles & Permissions page has to mean what it says for EVERY role - a
 * box ticked on Employee, or on a new role made on that page, opens that
 * box's page and only that page. Before this:
 *
 *  - the admin area admitted only admin/hr/accounts/manager by name, so a
 *    box ticked for any other role did nothing (AdminRoleGuard::permissionOpens);
 *  - several pages shared one gate, so one box opened all of them (any one
 *    settings box opened all twelve settings pages; "Approve Leave" opened
 *    Attendance Logs; Departments/Designations/Teams opened each other);
 *  - a new role replaced Employee, losing every self-service page;
 *  - some boxes were dead (Approve/Pay Travel Claims, Asset Categories,
 *    Scorecards, KPI setup, Audit Logs).
 *
 * Role-name access (admin, hr, accounts, manager) is unchanged.
 */
class RolesPagePermissionsWorkTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
    }

    /** A person in a non-sales department holding $roles (custom roles bring Employee along). */
    private function person(array $roles, array $special = []): User
    {
        $d = Department::create(['name' => 'ZZ Finance ' . uniqid(), 'code' => 'ZZ' . random_int(10000, 99999)]);
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Box ' . uniqid(),
            'email' => 'qabox' . uniqid() . '@example.test', 'code' => 'QB' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'), 'status' => 'active', 'department_id' => $d->id,
        ]);
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        $u->assignRole(array_unique(array_merge(...array_map(fn ($r) => RoleAssignment::rolesFor($r), $roles))));
        foreach ($special as $p) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    /** A new role made on the Roles page with exactly these boxes ticked. */
    private function customRole(array $boxes): string
    {
        $name = 'zz_role_' . uniqid();
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $boxes));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $name;
    }

    private function opens(User $u, string $url): bool
    {
        Nav::flush();

        return $this->actingAs($u)->get($url)->getStatusCode() === 200;
    }

    private function assertOpens(User $u, array $open, array $closed): void
    {
        foreach ($open as $url) {
            $this->assertTrue($this->opens($u, $url), "should open $url");
        }
        foreach ($closed as $url) {
            $this->assertFalse($this->opens($u, $url), "should NOT open $url");
        }
    }

    // ── a new role: each box opens its own page, not its neighbours ──────

    public function test_one_settings_box_opens_only_its_own_settings_page(): void
    {
        $u = $this->person([$this->customRole(['shifts.manage'])]);
        $this->assertOpens($u, ['/shifts'], ['/holidays', '/leaveTypes', '/expenseTypes', '/ipgroup', '/setu-sync']);
    }

    public function test_approve_leave_opens_leave_requests_not_attendance(): void
    {
        $u = $this->person([$this->customRole(['leave.approve'])]);
        $this->assertOpens($u, ['/leaveRequests'], ['/attendance', '/visits']);
    }

    public function test_departments_designations_and_teams_are_separate(): void
    {
        $u = $this->person([$this->customRole(['teams.manage'])]);
        $this->assertOpens($u, ['/teams'], ['/departments', '/designations']);
    }

    public function test_boxes_that_used_to_do_nothing_now_open_their_page(): void
    {
        foreach ([
            'travel_claim.approve' => '/travel-claims/admin/dashboard',
            'travel_claim.pay' => '/travel-claims/admin/dashboard',
            'assets.manage_categories' => '/asset-categories',
            'scorecards.manage' => '/performance/scorecards',
            'audit.view' => '/auditLogs',
            'kpi.manage' => '/kpi/templates',
            'biometric.manage' => '/biometric',
            'payroll.view' => '/payroll',
        ] as $box => $url) {
            $this->assertTrue($this->opens($this->person([$this->customRole([$box])]), $url), "$box -> $url");
        }
    }

    public function test_the_rest_of_the_admin_area_stays_closed(): void
    {
        $u = $this->person([$this->customRole(['shifts.manage'])]);
        $this->assertOpens($u, [], ['/employees', '/payroll', '/attendance', '/asset-management', '/roles']);
    }

    // ── a new role keeps everything an employee has ──────────────────────

    public function test_a_new_role_keeps_the_employee_self_service_pages(): void
    {
        $role = $this->customRole(['shifts.manage']);
        $u = $this->person([$role]);
        $this->assertEqualsCanonicalizing([$role, 'employee'], $u->roles->pluck('name')->all());
        $this->assertTrue($this->opens($u, '/user/dashboard'));
        $this->assertSame($role, RoleAssignment::primaryRole($u), 'the employee form must preselect the new role, not Employee');
    }

    public function test_base_roles_are_given_alone(): void
    {
        foreach (['employee', 'manager', 'hr', 'accounts', 'admin'] as $r) {
            $this->assertSame([$r], RoleAssignment::rolesFor($r));
        }
    }

    // ── Employee role + special permissions (e.g. an HR executive) ───────

    public function test_employee_with_view_employees_and_assets_gets_exactly_those(): void
    {
        $u = $this->person(['employee'], ['employee.view', 'assets.view', 'assets.create', 'assets.edit', 'assets.delete', 'assets.assign', 'assets.manage_categories']);
        $this->assertOpens($u,
            ['/employees', '/asset-management', '/asset-categories', '/uniform-requests/manage'],
            ['/attendance', '/payroll', '/leaveRequests', '/shifts', '/job', '/performance/scorecards']);
    }

    // ── role-name access is unchanged ────────────────────────────────────

    public function test_role_names_still_open_what_they_did(): void
    {
        $this->assertOpens($this->person(['manager']), ['/shifts', '/holidays', '/attendance', '/leaveRequests', '/asset-categories'], []);
        $this->assertOpens($this->person(['accounts']), ['/shifts', '/holidays', '/attendance'], []);
        $this->assertOpens($this->person(['hr']), ['/shifts', '/departments', '/designations', '/teams', '/travel-claims/admin/dashboard', '/auditLogs'], []);
    }

    public function test_a_plain_employee_still_gets_nothing_in_the_admin_area(): void
    {
        $this->assertOpens($this->person(['employee']), [], ['/shifts', '/employees', '/leaveRequests', '/asset-management', '/auditLogs']);
    }

    // ── the sidebar counts a box only where the page's own gate names it ──

    public function test_a_box_in_a_shared_group_does_not_draw_its_neighbours_links(): void
    {
        // A manager with "Manage 90-Day Goals": that box is on the settings
        // shortcuts group's shared list, and the manager role passes Biometric
        // Devices' and Asset Categories' own gates by name - but neither page
        // checks for goals.manage, so neither link may be drawn because of it.
        $u = $this->person(['manager'], ['goals.manage']);
        Nav::flush();
        $links = collect(Nav::previewMenuFor($u))->flatMap(fn ($e) => collect($e['submenu'] ?? [])
            ->where('visible', true)->map(fn ($c) => $e['name'] . ' > ' . $c['name']))->all();
        $this->assertNotContains('Time & Attendance > Biometric Devices', $links);
        $this->assertNotContains('Asset Management > Asset Categories', $links);
        $this->assertContains('90-Day Goals > Dashboard', $links, 'the box still shows its own pages');
    }

    // ── who may hand out boxes ───────────────────────────────────────────

    public function test_a_manager_cannot_hand_out_a_box_they_do_not_hold(): void
    {
        $manager = $this->person(['manager']);
        $target = $this->person(['employee']);
        Permission::firstOrCreate(['name' => 'payroll.view', 'guard_name' => 'web']);
        $res = $this->actingAs($manager)->postJson('/roles/toggleUserPermissionAjax', ['user_id' => $target->id, 'permission' => 'payroll.view', 'grant' => true]);
        $this->assertStringContainsString('only give permissions you have yourself', $res->getContent());
        $this->assertFalse($target->fresh()->hasDirectPermission('payroll.view'));
    }

    public function test_hr_can_hand_out_an_ordinary_box(): void
    {
        $hr = $this->person(['hr']);
        $target = $this->person(['employee']);
        Permission::firstOrCreate(['name' => 'assets.view', 'guard_name' => 'web']);
        $this->actingAs($hr)->postJson('/roles/toggleUserPermissionAjax', ['user_id' => $target->id, 'permission' => 'assets.view', 'grant' => true]);
        $this->assertTrue($target->fresh()->hasDirectPermission('assets.view'));
    }
}
