<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\UnpaidAbsenceReconciler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UnpaidAbsenceReconciler::run() - converts a leave marked unpaid (LWP) for
 * lack of balance at approval time to paid, once enough balance becomes
 * available within the SAME calendar month - e.g. a Monday taken as leave
 * with no balance available, followed by a Sunday worked that same month
 * earning a comp-off day. Reported live: nothing previously revisited an
 * old LWP decision once new balance arrived, so the Monday stayed Absent
 * forever even after the Sunday credit made it fully payable.
 */
class UnpaidAbsenceReconcilerTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'UnpaidRecon',
            'email'      => 'qaupr' . uniqid() . '@example.test',
            'code'       => 'QAUP' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
    }

    /**
     * Credits the balance and backdates the resulting audit row's created_at
     * to a specific date - $balance->update() alone stamps the real current
     * wall-clock time, which is well past August in this environment, so
     * anything meant to represent an in-August credit must be backdated
     * explicitly or the balanceAsOfMonthEnd() cutoff excludes it too.
     */
    private function creditBalance(LeaveBalance $balance, float $amount, string $on, string $reason): void
    {
        $newBalance = (float) $balance->balance + $amount;
        DB::table('audits')->insert([
            'auditable_type' => LeaveBalance::class, 'auditable_id' => $balance->id,
            'event' => 'updated',
            'old_values' => json_encode(['balance' => (float) $balance->balance]),
            'new_values' => json_encode(['balance' => $newBalance]),
            'user_agent' => $reason,
            'created_at' => $on, 'updated_at' => $on,
        ]);
        $balance->updateQuietly(['balance' => $newBalance]);
    }

    public function test_a_leave_marked_unpaid_for_lack_of_balance_is_paid_once_balance_arrives_same_month(): void
    {
        $employee = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        LeaveBalance::create(['user_id' => $employee->id, 'leave_type_id' => $alp->id, 'balance' => 0, 'used' => 0]);

        // No balance available at approval time - correctly marked unpaid.
        $leave = LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-08-04', 'to_date' => '2026-08-04',
            'status' => 'pending', 'user_notes' => 'unpaid absence reconciler test',
        ]);
        $leave->update(['status' => 'approved']);
        $this->assertTrue((bool) $leave->fresh()->is_lwp, 'sanity check: must genuinely be unpaid before the credit arrives');

        // The employee later works a Sunday this same month and earns the comp-off day.
        $balance = LeaveBalance::where('user_id', $employee->id)->where('leave_type_id', $alp->id)->first();
        $this->creditBalance($balance, 1, '2026-08-10 11:00:00', 'Earned via Sunday Working on 09-08-2026');

        $result = UnpaidAbsenceReconciler::run(false, $employee->id, 8, 2026);

        $this->assertEquals(1, $result['converted']);
        $leave->refresh();
        $this->assertFalse((bool) $leave->is_lwp, 'the leave must now be marked paid');
        $this->assertEquals(0, (float) $leave->lwp_days);
        $balance->refresh();
        $this->assertEquals(1.0, (float) $balance->used, 'the newly-earned day must be consumed to cover the leave');
    }

    public function test_a_credit_in_a_different_month_does_not_reach_back(): void
    {
        $employee = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        LeaveBalance::create(['user_id' => $employee->id, 'leave_type_id' => $alp->id, 'balance' => 0, 'used' => 0]);

        $leave = LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-08-04', 'to_date' => '2026-08-04',
            'status' => 'pending', 'user_notes' => 'different month test',
        ]);
        $leave->update(['status' => 'approved']);
        $this->assertTrue((bool) $leave->fresh()->is_lwp, 'sanity check: must genuinely be unpaid before the credit arrives');

        // The credit arrives after approval, same as the real scenario - but we
        // run the reconciler for September, not August.
        $balance = LeaveBalance::where('user_id', $employee->id)->where('leave_type_id', $alp->id)->first();
        $balance->update(['balance' => 1]);

        $result = UnpaidAbsenceReconciler::run(false, $employee->id, 9, 2026);

        $this->assertEquals(0, $result['converted'], 'an August absence must not be picked up when reconciling September');
        $this->assertTrue((bool) $leave->fresh()->is_lwp, 'the August leave must remain unpaid');
    }

    /**
     * REGRESSION — a dry run recomputed $available from $balance->used on
     * every iteration, but $balance->used is only ever persisted when
     * actually applying, so two unpaid leaves in the same month against a
     * balance that could only ever cover ONE of them both re-read the SAME
     * unspent starting balance and both showed as payable.
     */
    public function test_two_unpaid_leaves_with_balance_for_only_one_converts_only_the_older_one(): void
    {
        $employee = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        LeaveBalance::create(['user_id' => $employee->id, 'leave_type_id' => $alp->id, 'balance' => 0, 'used' => 0]);

        $older = LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-08-03', 'to_date' => '2026-08-03',
            'status' => 'pending', 'user_notes' => 'older unpaid leave',
        ]);
        $older->update(['status' => 'approved']);

        $newer = LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-08-10', 'to_date' => '2026-08-10',
            'status' => 'pending', 'user_notes' => 'newer unpaid leave',
        ]);
        $newer->update(['status' => 'approved']);

        // Only enough balance for ONE of the two 1-day leaves.
        $balance = LeaveBalance::where('user_id', $employee->id)->where('leave_type_id', $alp->id)->first();
        $this->creditBalance($balance, 1, '2026-08-15 11:00:00', 'Earned via Holiday Working on 15-08-2026');

        $result = UnpaidAbsenceReconciler::run(true, $employee->id, 8, 2026);

        $this->assertEquals(1, $result['converted'], 'only one of the two leaves can genuinely be covered by the single available day');
        $this->assertEquals($older->id, $result['rows'][0]['leave'], 'the older leave must be the one covered, not the newer one');
    }

    /**
     * REGRESSION — eligibility was checked against today's LIVE balance,
     * which already includes a LATER month's own accrual once that month
     * has started (e.g. reconciling August in September, after September
     * 1st's accrual has posted). That silently let a later month's credit
     * reach back into an earlier month's absence - exactly the cross-month
     * reconciliation this service explicitly promises never to do.
     * Reported live: an August absence's "covering" credit turned out to be
     * dated September 1st.
     */
    public function test_a_credit_dated_in_a_later_month_does_not_cover_an_earlier_months_absence(): void
    {
        $employee = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        LeaveBalance::create(['user_id' => $employee->id, 'leave_type_id' => $alp->id, 'balance' => 0, 'used' => 0]);

        $leave = LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-08-29', 'to_date' => '2026-08-29',
            'status' => 'pending', 'user_notes' => 'cross-month credit test',
        ]);
        $leave->update(['status' => 'approved']);
        $this->assertTrue((bool) $leave->fresh()->is_lwp, 'sanity check: must genuinely be unpaid before any credit arrives');

        $balance = LeaveBalance::where('user_id', $employee->id)->where('leave_type_id', $alp->id)->first();

        // September's own monthly accrual - dated AFTER the target month
        // (August) ends. The live balance now shows 1 available, exactly
        // like the real Sep-1 accrual would, but it must not count.
        $balance->update(['balance' => 1]);
        DB::table('audits')->insert([
            'auditable_type' => LeaveBalance::class, 'auditable_id' => $balance->id,
            'event' => 'updated', 'old_values' => json_encode(['balance' => 0]), 'new_values' => json_encode(['balance' => 1]),
            'user_agent' => 'Earned for September 2026',
            'created_at' => '2026-09-01 00:01:00', 'updated_at' => '2026-09-01 00:01:00',
        ]);

        $result = UnpaidAbsenceReconciler::run(true, $employee->id, 8, 2026);

        $this->assertEquals(0, $result['converted'], 'a September credit must never cover an August absence, even though the live balance already reflects it');
        $this->assertTrue((bool) $leave->fresh()->is_lwp);
    }

    public function test_a_pre_cutoff_absence_is_never_converted_even_with_balance(): void
    {
        $employee = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        LeaveBalance::create(['user_id' => $employee->id, 'leave_type_id' => $alp->id, 'balance' => 5, 'used' => 0]);

        // 2026-07-15 predates LEDGER_OPENS (2026-07-31) - already inside Accounts' verified opening figure.
        $leave = LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-07-15', 'to_date' => '2026-07-15',
            'status' => 'pending', 'user_notes' => 'pre-cutoff safety test',
        ]);
        $leave->updateQuietly(['status' => 'approved', 'is_lwp' => true, 'lwp_days' => 1]);

        $result = UnpaidAbsenceReconciler::run(false, $employee->id, 7, 2026);

        $this->assertEquals(0, $result['converted'], 'a pre-cutoff absence must never be reconciled regardless of available balance');
        $this->assertTrue((bool) $leave->fresh()->is_lwp);
    }
}
