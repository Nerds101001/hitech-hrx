<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\SalesPipelineController as Sales;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Sales Report month lock, and the one-off reopen of April to August 2026
 * that runs through the end of Monday 21 Sept 2026 (IST) and then closes by itself.
 */
class SalesReportMonthLockTest extends TestCase
{
    use DatabaseTransactions;

    private function person(?string $role = null): User
    {
        static $n = 0;
        $n++;
        $u = User::create(['first_name' => 'QA', 'last_name' => "Lock {$n}", 'email' => 'qalock' . uniqid() . '@example.test',
            'code' => 'QALK' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active']);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u->fresh();
    }

    private function at(string $ist): void
    {
        Carbon::setTestNow(Carbon::parse($ist, 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_april_to_august_are_open_to_the_sales_team_through_the_end_of_monday_21_sept(): void
    {
        $manager = $this->person('manager');
        $plain = $this->person();

        foreach (['2026-09-19 12:00:00', '2026-09-20 09:00:00', '2026-09-21 00:00:01', '2026-09-21 23:59:59'] as $moment) {
            $this->at($moment);
            foreach (['2026-04', '2026-05', '2026-06', '2026-07', '2026-08'] as $month) {
                $this->assertFalse(Sales::isMonthLockedForUser($manager, $month), "manager, $month, $moment");
                $this->assertFalse(Sales::isMonthLockedForUser($plain, $month), "salesperson, $month, $moment");
                $this->assertTrue(Sales::isReopened($month), "$month, $moment");
            }
        }
    }

    public function test_it_closes_again_by_itself_from_tuesday(): void
    {
        $manager = $this->person('manager');

        foreach (['2026-09-22 00:00:01', '2026-10-15 10:00:00'] as $moment) {
            $this->at($moment);
            foreach (['2026-04', '2026-05', '2026-06', '2026-07', '2026-08'] as $month) {
                $this->assertTrue(Sales::isMonthLockedForUser($manager, $month), "$month, $moment");
                $this->assertFalse(Sales::isReopened($month), "$month, $moment");
            }
        }
    }

    public function test_only_the_listed_months_are_reopened_not_older_or_newer_ones(): void
    {
        $manager = $this->person('manager');
        $this->at('2026-09-19 12:00:00');

        $this->assertTrue(Sales::isMonthLockedForUser($manager, '2026-03'), 'last financial year stays closed');
        $this->assertTrue(Sales::isMonthLockedForUser($manager, '2025-12'), 'older months stay closed');
        $this->assertTrue(Sales::isMonthLockedForUser($manager, '2026-10'), 'a future month stays closed to non-admins');
        $this->assertFalse(Sales::isMonthLockedForUser($manager, '2026-09'), 'the current month is open as always');
    }

    public function test_admin_and_hr_can_always_edit_any_month(): void
    {
        $this->at('2026-09-25 12:00:00');   // after the reopen has ended
        foreach (['admin', 'hr'] as $role) {
            $u = $this->person($role);
            foreach (['2026-04', '2026-08', '2026-09'] as $m) {
                $this->assertFalse(Sales::isMonthLockedForUser($u, $m), "$role, $m");
            }
        }
    }

    public function test_the_normal_rule_is_unchanged_open_through_the_5th_then_locked(): void
    {
        $plain = $this->person();

        $this->at('2026-11-05 23:00:00');
        $this->assertFalse(Sales::isMonthLockedForUser($plain, '2026-10'), 'last month is open through the 5th');
        $this->at('2026-11-06 09:00:00');
        $this->assertTrue(Sales::isMonthLockedForUser($plain, '2026-10'), 'and locked from the 6th');
        $this->assertFalse(Sales::isMonthLockedForUser($plain, '2026-11'), 'the current month is open');
    }
}
