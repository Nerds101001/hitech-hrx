<?php

namespace Tests\Feature;

use App\Models\KpiAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * REGRESSION (security re-audit, MEDIUM).
 * - Salary import saved whatever salaries the browser posted back as JSON, and
 *   the payroll route group lets a view-only user in.
 * - KPI add/edit/retire/score checked the role only, so a manager with the KPI
 *   grant could change any employee's KPIs, not just their team's.
 */
class SalaryAndKpiWriteScopeTest extends TestCase
{
    use DatabaseTransactions;

    private function person(?string $role = null, array $perms = [], ?int $reportsTo = null): User
    {
        Mail::fake();
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Scope ' . uniqid(), 'email' => 'qasc' . uniqid() . '@example.test',
            'code' => 'QS' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'),
            'status' => 'active', 'base_salary' => 10000,
        ]);
        // Not mass-assignable on User.
        $u->forceFill(['reporting_to_id' => $reportsTo])->saveQuietly();
        if ($role) {
            $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        }
        foreach ($perms as $p) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    private function forgedRecords(User $victim): string
    {
        return json_encode([[
            'user_id' => $victim->id, 'base_salary' => 999999, 'salary_policy_id' => null,
            'custom_basic' => null, 'custom_hra' => null, 'custom_ca' => null, 'custom_medical' => null,
            'custom_edu' => null, 'custom_special_allowance' => null, 'custom_pt' => null,
            'custom_epf' => null, 'custom_esic' => null,
        ]]);
    }

    public function test_a_view_only_payroll_user_cannot_import_salaries(): void
    {
        $viewer = $this->person('employee', ['payroll.view']);
        $victim = $this->person();

        $this->actingAs($viewer)->post(route('payroll.salary-import.store'), ['records' => $this->forgedRecords($victim)])
            ->assertForbidden();
        $this->assertEquals(10000, (float) $victim->fresh()->base_salary);
    }

    public function test_posted_salary_figures_are_ignored(): void
    {
        $admin = $this->person('admin');
        $victim = $this->person();

        // No preview in this session: nothing read from a file, so nothing saved.
        $this->actingAs($admin)->post(route('payroll.salary-import.store'), ['records' => $this->forgedRecords($victim)])
            ->assertRedirect(route('payroll.index'))
            ->assertSessionHas('error');
        $this->assertEquals(10000, (float) $victim->fresh()->base_salary);
    }

    public function test_a_manager_changes_kpis_of_their_own_reports_only(): void
    {
        $manager = $this->person('manager', ['kpi.manage']);
        $mine = $this->person(null, [], $manager->id);
        $other = $this->person();

        $payload = fn (User $u) => ['user_id' => $u->id, 'title' => 'QA KPI', 'category' => 'KPI', 'frequency' => 'monthly',
            'scoring_type' => 'target', 'max_marks' => 5, 'target_value' => 10];

        $this->actingAs($manager)->postJson(route('tenant.kpi.store'), $payload($other))->assertForbidden();
        $this->actingAs($manager)->postJson(route('tenant.kpi.store'), $payload($mine))->assertOk();

        $theirs = KpiAssignment::create(['user_id' => $other->id, 'title' => 'Not yours', 'category' => 'KPI',
            'frequency' => 'monthly', 'scoring_type' => 'target', 'max_marks' => 5, 'target_value' => 10, 'is_active' => true]);

        $this->actingAs($manager)->putJson(route('tenant.kpi.update', $theirs->id), ['title' => 'Hijacked', 'category' => 'KPI',
            'frequency' => 'monthly', 'scoring_type' => 'target', 'max_marks' => 5])->assertForbidden();
        $this->actingAs($manager)->deleteJson(route('tenant.kpi.destroy', $theirs->id))->assertForbidden();
        $this->actingAs($manager)->postJson(route('tenant.kpi.evaluate', $theirs->id), ['achieved_value' => 10])->assertForbidden();

        $theirs->refresh();
        $this->assertSame('Not yours', $theirs->title);
        $this->assertTrue((bool) $theirs->is_active);
    }

    public function test_admin_is_not_limited_to_a_team(): void
    {
        $admin = $this->person('admin');
        $anyone = $this->person();

        $this->actingAs($admin)->postJson(route('tenant.kpi.store'), ['user_id' => $anyone->id, 'title' => 'QA KPI',
            'category' => 'KPI', 'frequency' => 'monthly', 'scoring_type' => 'target', 'max_marks' => 5, 'target_value' => 10])
            ->assertOk();
    }
}
