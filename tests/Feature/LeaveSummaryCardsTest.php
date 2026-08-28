<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * "My Leaves"'s three top stat cards. balance is a running total of
 * everything ever credited; used is a running total of everything ever
 * consumed - a deduction only ever increments used, never decrements
 * balance. Total Allocated read as balance+used (double-counting whatever
 * had been used) and Available Balance read as balance alone (ignoring
 * used entirely) - both wrong, and invisible for as long as used stayed at
 * 0 for most people, which the separately-fixed half-day-never-deducts bug
 * made far more common than it should have been. Reported live: an
 * employee with 2.0 ever credited and 0.5 used saw Allocated=2.5 and
 * Available=2.0 instead of the correct 2.0 and 1.5.
 */
class LeaveSummaryCardsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_allocated_and_available_account_for_days_already_used(): void
    {
        $user = User::create([
            'first_name' => 'QA', 'last_name' => 'Cards',
            'email'      => 'qacards' . uniqid() . '@example.test',
            'code'       => 'QACD' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
        $user->assignRole('employee');

        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        LeaveBalance::create([
            'user_id' => $user->id, 'leave_type_id' => $alp->id,
            'balance' => 2.0, 'used' => 0.5,
        ]);

        $html = $this->actingAs($user)->get(route('user.leaves.index'))->assertOk()->getContent();

        // Allocated must stay at what was actually credited (2.0), not
        // 2.5 (balance+used double-counting the 0.5 already spent).
        $this->assertMatchesRegularExpression(
            '/Total Allocated.*?class="stat-value[^"]*">\s*2\.0/s',
            $html,
            'Total Allocated must equal balance alone (2.0), not balance+used (2.5)'
        );
        // Available must actually subtract what has been used (2.0 - 0.5 =
        // 1.5), not just report the raw balance (2.0) as if none were used.
        $this->assertMatchesRegularExpression(
            '/Available Balance.*?class="stat-value[^"]*">\s*1\.5/s',
            $html,
            'Available Balance must be balance minus used (1.5), not balance alone (2.0)'
        );
    }
}
