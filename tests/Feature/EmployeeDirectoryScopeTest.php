<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\EmployeeController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * REGRESSION. An Employee-role person given "View Employee List" (employee.view)
 * as a special permission could open the Employee Directory, but the list was
 * scoped by role alone, so it showed only their own row.
 */
class EmployeeDirectoryScopeTest extends TestCase
{
    use DatabaseTransactions;

    private function person(): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Dir ' . uniqid(),
            'email' => 'qa' . uniqid() . '@example.test', 'code' => 'QD' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        $u->assignRole(Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']));

        return $u;
    }

    /** Rows the directory's list returns for $viewer. */
    private function rowsFor(User $viewer): int
    {
        $this->actingAs($viewer);
        $res = app(EmployeeController::class)->userListAjax(new Request(['start' => 0, 'length' => 500, 'draw' => 1]));

        return (int) json_decode($res->getContent(), true)['recordsTotal'];
    }

    public function test_view_employee_list_shows_the_whole_directory(): void
    {
        Mail::fake();
        $viewer = $this->person();
        $this->person();
        $this->person();

        $this->assertSame(1, $this->rowsFor($viewer), 'without the permission: only themselves');

        $viewer->givePermissionTo(Permission::firstOrCreate(['name' => 'employee.view', 'guard_name' => 'web']));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertGreaterThan(3, $this->rowsFor($viewer->fresh()), 'with View Employee List: everyone');
    }
}
