<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Support\Nav;
use Database\Seeders\RemoveRecruitmentFromManagerRoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Recruitment is HR's module. Managers held the whole recruitment catalogue
 * (RecruitmentPermissionSeeder gave it to them), and the recruitment route
 * group admitted every manager and accounts user by role - Interview Schedule
 * has no check of its own, so it opened for them regardless of any box.
 */
class RecruitmentHrOnlyTest extends TestCase
{
    use DatabaseTransactions;

    private const PAGES = ['/job', '/job-application', '/candidates', '/job-category', '/job-stage', '/custom-question', '/interview-schedule'];
    private const CATALOGUE = ['Manage Job', 'Manage Job Application', 'Manage Job OnBoard', 'Manage Job Category',
        'Manage Job Stage', 'Manage Custom Question', 'Create Interview Schedule'];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        foreach (self::CATALOGUE as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Permission::firstOrCreate(['name' => 'recruitment.view', 'guard_name' => 'web']);
        // the state production was in: admin, hr and manager all holding it
        foreach (['admin', 'hr', 'manager'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web'])->givePermissionTo(self::CATALOGUE);
        }
        // ...and accounts holding none (a local copy may have them ticked)
        $accounts = Role::firstOrCreate(['name' => 'accounts', 'guard_name' => 'web']);
        foreach (array_merge(self::CATALOGUE, ['recruitment.view']) as $p) {
            if ($accounts->hasPermissionTo($p)) {
                $accounts->revokePermissionTo($p);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function person(string $role, array $permissions = []): User
    {
        $d = Department::create(['name' => 'ZZ Dept ' . uniqid(), 'code' => 'ZZ' . random_int(10000, 99999)]);
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Recruit ' . uniqid(),
            'email' => 'qarec' . uniqid() . '@example.test', 'code' => 'QR' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'), 'status' => 'active', 'department_id' => $d->id,
        ]);
        $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        foreach ($permissions as $p) {
            $u->givePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    private function statusOf(User $u, string $url): int
    {
        Nav::flush();

        return $this->actingAs($u)->get($url)->getStatusCode();
    }

    public function test_the_seeder_takes_recruitment_off_the_manager_role_only(): void
    {
        app(RemoveRecruitmentFromManagerRoleSeeder::class)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertSame([], Role::findByName('manager', 'web')->permissions()->whereIn('name', self::CATALOGUE)->pluck('name')->all());
        foreach (['admin', 'hr'] as $r) {
            $this->assertSame(count(self::CATALOGUE), Role::findByName($r, 'web')->permissions()->whereIn('name', self::CATALOGUE)->count(), $r);
        }
    }

    public function test_the_seeder_is_safe_to_run_twice(): void
    {
        app(RemoveRecruitmentFromManagerRoleSeeder::class)->run();
        app(RemoveRecruitmentFromManagerRoleSeeder::class)->run();
        $this->assertSame(0, Role::findByName('manager', 'web')->permissions()->whereIn('name', self::CATALOGUE)->count());
    }

    public function test_managers_and_accounts_cannot_open_recruitment_even_with_the_old_boxes(): void
    {
        foreach (['manager', 'accounts'] as $role) {
            $u = $this->person($role);
            foreach (self::PAGES as $url) {
                $this->assertNotSame(200, $this->statusOf($u, $url), "$role: $url");
            }
        }
    }

    public function test_hr_still_opens_every_recruitment_page(): void
    {
        $u = $this->person('hr');
        foreach (self::PAGES as $url) {
            $this->assertSame(200, $this->statusOf($u, $url), $url);
        }
    }

    public function test_one_person_can_still_be_given_recruitment(): void
    {
        $u = $this->person('manager', ['recruitment.view', 'Manage Job']);
        $this->assertSame(200, $this->statusOf($u, '/job'));
    }
}
