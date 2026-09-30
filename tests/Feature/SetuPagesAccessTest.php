<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Support\Nav;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Setu Salesperson Mappings and Setu Sales Sync sat inside the config-masters
 * route group, which admits admin, HR, accounts, every manager and anyone
 * holding a single config permission. So a delegate given only "Manage Expense
 * Types" could open both Setu pages, and the sidebar showed them the links. The
 * pages now carry their own admin/HR (or setu.manage) gate.
 */
class SetuPagesAccessTest extends TestCase
{
    use DatabaseTransactions;

    private const PAGES = ['/setu-mappings', '/setu-mappings/getListAjax', '/setu-sync'];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
    }

    private function person(string $role, array $permissions = []): User
    {
        $d = Department::create(['name' => 'ZZ HR ' . uniqid(), 'code' => 'ZZ' . random_int(10000, 99999)]);
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Setu ' . uniqid(),
            'email' => 'qasetu' . uniqid() . '@example.test', 'code' => 'QS' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'), 'status' => 'active', 'department_id' => $d->id,
        ]);
        $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        foreach ($permissions as $p) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    private function opens(User $u, string $url): bool
    {
        Nav::flush();

        return $this->actingAs($u)->get($url)->getStatusCode() === 200;
    }

    /** The Setu links drawn in this person's real sidebar. */
    private function setuLinks(User $u): array
    {
        Nav::flush();
        $html = $this->actingAs($u)->get('/kpi/my')->getContent();
        Nav::flush();
        preg_match_all('/class="hitech-submenu-link"[^>]*>\s*(Setu[^<]*?)\s*<\/a>/', $html, $m);

        return $m[1];
    }

    public function test_a_config_permission_no_longer_opens_the_setu_pages(): void
    {
        // Mirrors the delegate who had it: employee role, expense approver
        // (passes the admin area) plus "Manage Expense Types".
        $u = $this->person('employee', ['expense.approve', 'expense.manage_types']);
        foreach (self::PAGES as $url) {
            $this->assertFalse($this->opens($u, $url), $url);
        }
        $this->assertSame([], $this->setuLinks($u));
    }

    public function test_the_config_permission_still_opens_its_own_page(): void
    {
        $u = $this->person('employee', ['expense.approve', 'expense.manage_types']);
        $this->assertTrue($this->opens($u, '/expenseTypes'),
            'Manage Expense Types must still open the expense types page');
    }

    public function test_managers_and_accounts_are_refused(): void
    {
        foreach (['manager', 'accounts'] as $role) {
            $u = $this->person($role);
            foreach (self::PAGES as $url) {
                $this->assertFalse($this->opens($u, $url), "$role: $url");
            }
        }
    }

    public function test_admin_and_hr_still_open_them(): void
    {
        foreach (['admin', 'hr'] as $role) {
            $u = $this->person($role);
            foreach (self::PAGES as $url) {
                $this->assertTrue($this->opens($u, $url), "$role: $url");
            }
            $this->assertSame(['Setu Salesperson Mappings', 'Setu Sales Sync'], $this->setuLinks($u), $role);
        }
    }
}
