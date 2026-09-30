<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * initiateTermination(), confirmProbation(), extendProbation() and
 * failProbation() had their authorization checks commented out entirely -
 * the outer employees/* route gate (which OR's in narrow permissions like
 * employee.kpa and employee.manage_device, meant for unrelated profile tabs)
 * was the only thing standing between any of those and terminating or
 * failing the probation of any employee in the company. relieveEmployee()
 * and retireEmployee() were role-only, with no path for employee.lifecycle_
 * action to work the way it already did for the sibling moveToOnboarding().
 *
 * Fixing the controller checks alone was not enough: AdminRoleGuard sits in
 * front of this whole route group, and employee.lifecycle_action was not on
 * its ELEVATED_PERMISSIONS allowlist, so granting the permission to someone
 * without admin/hr/accounts role would have looked like it worked and done
 * nothing - blocked one layer earlier. This suite asserts the fix all the
 * way through the real route/middleware stack, not just the controller.
 *
 * DatabaseTransactions, never RefreshDatabase - the local database holds
 * real imported production data.
 */
class EmployeeLifecycleActionsTest extends TestCase
{
    use DatabaseTransactions;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->department = Department::create([
            'name' => 'ZZ QA Lifecycle Dept ' . uniqid(),
            'code' => 'ZZL' . random_int(1000, 9999),
        ]);
    }

    private function makeUser(?string $role = null, array $attrs = []): User
    {
        $user = User::create(array_merge([
            'first_name'    => 'QA',
            'last_name'     => 'Tester',
            'email'         => 'qa' . uniqid() . '@example.test',
            'code'          => 'QA' . random_int(100000, 999999),
            'password'      => Hash::make('secret-not-used'),
            'status'        => 'active',
            'department_id' => $this->department->id,
        ], $attrs));

        if ($role && ($model = Role::where('name', $role)->first())) {
            $user->assignRole($model);
        }

        return $user;
    }

    // ── the previously wide-open action: full round trip ────────────────

    public function test_admin_can_terminate_an_employee(): void
    {
        $admin  = $this->makeUser('admin');
        $target = $this->makeUser();

        $this->actingAs($admin)->postJson("/employees/{$target->id}/terminate", [
            'exitDate' => now()->toDateString(),
            'lastWorkingDay' => now()->toDateString(),
            'terminationType' => 'resignation',
            'exitReason' => 'QA test',
            'isEligibleForRehire' => true,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('terminated', $target->fresh()->status->value ?? $target->fresh()->status);
    }

    public function test_an_ordinary_employee_cannot_terminate_anyone(): void
    {
        $plain  = $this->makeUser();
        $target = $this->makeUser();

        $this->actingAs($plain)->postJson("/employees/{$target->id}/terminate", [
            'exitDate' => now()->toDateString(),
            'lastWorkingDay' => now()->toDateString(),
            'terminationType' => 'resignation',
            'exitReason' => 'QA test',
            'isEligibleForRehire' => true,
        ])->assertForbidden();

        $this->assertNotEquals('terminated', $target->fresh()->status->value ?? $target->fresh()->status);
    }

    /**
     * REGRESSION. This is the actual bug: before the fix, this request
     * succeeded, because the auth check was commented out and the outer
     * route gate accepts a handful of unrelated narrow permissions too.
     */
    public function test_holding_only_employee_kpa_is_not_enough_to_terminate(): void
    {
        $delegate = $this->makeUser();
        $delegate->givePermissionTo('employee.kpa');
        $target = $this->makeUser();

        $this->actingAs($delegate)->postJson("/employees/{$target->id}/terminate", [
            'exitDate' => now()->toDateString(),
            'lastWorkingDay' => now()->toDateString(),
            'terminationType' => 'resignation',
            'exitReason' => 'QA test',
            'isEligibleForRehire' => true,
        ])->assertForbidden();
    }

    /**
     * The actual capability being added: granting employee.lifecycle_action
     * alone (no admin/hr/accounts role) must clear BOTH AdminRoleGuard and
     * the controller check, all the way through the real middleware stack.
     */
    public function test_holding_employee_lifecycle_action_alone_can_terminate(): void
    {
        Permission::firstOrCreate(['name' => 'employee.lifecycle_action', 'guard_name' => 'web']);
        $delegate = $this->makeUser();
        $delegate->givePermissionTo('employee.lifecycle_action');
        $target = $this->makeUser();

        $this->actingAs($delegate)->postJson("/employees/{$target->id}/terminate", [
            'exitDate' => now()->toDateString(),
            'lastWorkingDay' => now()->toDateString(),
            'terminationType' => 'resignation',
            'exitReason' => 'QA test',
            'isEligibleForRehire' => true,
        ])->assertOk()->assertJson(['success' => true]);
    }

    // ── the other five: lighter checks, same shape of bug ────────────────

    public function test_an_ordinary_employee_cannot_confirm_probation(): void
    {
        $plain  = $this->makeUser();
        $target = $this->makeUser(null, ['probation_end_date' => now()->subDay()]);

        $this->actingAs($plain)
            ->postJson("/employees/{$target->id}/confirmProbation", [])
            ->assertForbidden();
    }

    public function test_an_ordinary_employee_cannot_extend_probation(): void
    {
        $plain  = $this->makeUser();
        $target = $this->makeUser(null, ['probation_end_date' => now()->subDay()]);

        $this->actingAs($plain)->postJson("/employees/{$target->id}/extendProbation", [
            'newProbationEndDate' => now()->addMonth()->toDateString(),
            'probationRemarks' => 'QA test',
        ])->assertForbidden();
    }

    public function test_an_ordinary_employee_cannot_fail_probation(): void
    {
        $plain  = $this->makeUser();
        $target = $this->makeUser();

        $this->actingAs($plain)
            ->postJson("/employees/{$target->id}/failProbation", [])
            ->assertForbidden();
    }

    public function test_an_ordinary_employee_cannot_relieve_or_retire_anyone(): void
    {
        $plain  = $this->makeUser();
        $target = $this->makeUser();

        $this->actingAs($plain)->postJson("/employees/relieve/{$target->id}")->assertForbidden();
        $this->actingAs($plain)->postJson("/employees/retire/{$target->id}")->assertForbidden();
    }

    public function test_admin_can_relieve_and_retire(): void
    {
        $admin = $this->makeUser('admin');
        $t1 = $this->makeUser();
        $t2 = $this->makeUser();

        $this->actingAs($admin)->postJson("/employees/relieve/{$t1->id}")->assertOk();
        $this->actingAs($admin)->postJson("/employees/retire/{$t2->id}")->assertOk();
    }
}
