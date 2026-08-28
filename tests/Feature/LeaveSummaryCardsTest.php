<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
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

    /**
     * The ledger's Amount column was hardcoded per request shape (-0.5 for
     * any half-day, the full requested span for any full-day request)
     * regardless of lwp_days - so a half-day leave that ended up entirely
     * unpaid (no balance available at approval) still displayed "-0.5" and
     * "Leave Deducted" as if the pool had actually been charged. Reported
     * live: an employee's Available Balance card correctly showed the leave
     * never touched their balance, while the ledger row right below it
     * claimed otherwise.
     */
    public function test_ledger_amount_reflects_actual_paid_days_not_the_full_requested_span(): void
    {
        $user = User::create([
            'first_name' => 'QA', 'last_name' => 'LedgerAmount',
            'email'      => 'qaledgeramt' . uniqid() . '@example.test',
            'code'       => 'QALA' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
        $user->assignRole('employee');

        $hdl = LeaveType::where('code', 'HDL')->firstOrFail();
        // Deliberately no ALP balance row at all - available is 0, so
        // approval correctly leaves this unpaid (is_lwp=true, lwp_days=0.5).
        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $hdl->id,
            'from_date' => '2026-08-04', 'to_date' => '2026-08-04',
            'is_half_day' => true, 'half_day_session' => 'first_half',
            'status' => 'pending', 'user_notes' => 'ledger amount test',
        ]);
        $leave->update(['status' => 'approved']);
        $this->assertTrue((bool) $leave->fresh()->is_lwp, 'sanity check: this leave must genuinely be unpaid for the test to mean anything');

        $html = $this->actingAs($user)->get(route('user.leaves.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('>-0.5<', $html, 'an unpaid half-day leave must not display as if 0.5 days were deducted');
        $this->assertMatchesRegularExpression('/>-0\.0<\/span>/', $html, 'an unpaid leave must show -0.0 deducted, matching that the balance was never touched');
    }
}
