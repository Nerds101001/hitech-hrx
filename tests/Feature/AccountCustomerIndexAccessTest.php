<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * REGRESSION (security re-audit L2). account/customerIndex(+Ajax) listed every
 * customer account to any logged-in employee. It was first gated to admin/HR,
 * then removed (2026-09-29): an unused page of the dormant customer-
 * subscription layer, broken on prod anyway (no users.is_customer column).
 */
class AccountCustomerIndexAccessTest extends TestCase
{
    use DatabaseTransactions;

    private function person(string $role): User
    {
        Mail::fake();
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Cust ' . uniqid(), 'email' => 'qacust' . uniqid() . '@example.test',
            'code' => 'QC' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $u;
    }

    public function test_the_customers_page_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('account.customerIndex'));
        $this->assertFalse(Route::has('account.customerIndexAjax'));

        foreach (['employee', 'admin'] as $role) {
            $this->actingAs($this->person($role))->get('/account/customerIndex')->assertNotFound();
            $this->actingAs($this->person($role))->getJson('/account/customerIndexAjax')->assertNotFound();
        }
    }

    public function test_the_super_admin_menu_no_longer_links_to_it(): void
    {
        $menu = file_get_contents(base_path('resources/menu/verticalMenu.json'));
        $this->assertStringNotContainsString('customerIndex', $menu);
        $this->assertNotNull(json_decode($menu), 'the menu is still valid JSON');
    }
}
