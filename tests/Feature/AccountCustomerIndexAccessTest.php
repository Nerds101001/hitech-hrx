<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * REGRESSION (security re-audit L2): account/customerIndex(+Ajax) sit outside
 * web.php's admin|hr group, so any logged-in employee could list every
 * customer account and its contact details.
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

    public function test_an_employee_cannot_list_customer_accounts(): void
    {
        $emp = $this->person('employee');

        $this->actingAs($emp)->get(route('account.customerIndex'))->assertForbidden();
        $this->actingAs($emp)->getJson(route('account.customerIndexAjax'))->assertForbidden();
    }

    public function test_admin_is_not_blocked_by_the_gate(): void
    {
        // The Ajax body belongs to a dormant customer-subscription layer and
        // answers 400 for its own reasons; the point here is only that the new
        // gate lets admin through.
        $status = $this->actingAs($this->person('admin'))->getJson(route('account.customerIndexAjax', ['draw' => 1, 'start' => 0, 'length' => 5]))->status();
        $this->assertNotSame(403, $status);
    }
}
