<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveHistoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * getUnifiedHistory()'s opening-balance reconstruction assumed any balance
 * with used > 0 must have unexplained pre-tracking history, and manufactured
 * a synthetic "Earned for {month}" credit equal to that used amount - even
 * for a balance created entirely within the tracked window (LEDGER_OPENS),
 * whose one real credit audit already explains it in full and whose
 * deduction is already its own separate ledger line from the LeaveRequest
 * that caused it. That double-counted the same day twice: once as the real
 * credit, once as a phantom "opening balance". Reported live: a Short Leave
 * balance created 2026-08-01 with one +1 credit and a later -1 deduction
 * showed THREE identical "Earned for August 2026" rows instead of one.
 */
class LeaveHistoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'Ledger',
            'email'      => 'qaledger' . uniqid() . '@example.test',
            'code'       => 'QALG' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
    }

    /** THE CORE BUG: a fully-tracked, fully-explained balance must not get a phantom opening-balance credit. */
    public function test_a_fully_tracked_balance_gets_no_phantom_opening_balance_entry(): void
    {
        $user = $this->makeUser();
        $type = LeaveType::where('code', 'ALP')->firstOrFail();

        $balance = LeaveBalance::create([
            'user_id' => $user->id, 'leave_type_id' => $type->id,
            'balance' => 0, 'used' => 0,
        ]);

        // The one real, fully-tracked credit - mirrors what LeaveAccrualService does.
        LeaveBalance::$auditReason = 'Earned for Test Month';
        $balance->update(['balance' => 1.0]);

        // A later deduction, already its own separate ledger line elsewhere
        // (a LeaveRequest row) - not something this reconstruction should
        // re-explain via a second phantom credit.
        $balance->update(['used' => 1.0]);

        $history = LeaveHistoryService::getUnifiedHistory($user->id);
        $phantom = $history->first(fn($h) => str_starts_with($h->id, 'SYS-OPEN-' . $balance->id));

        $this->assertNull($phantom, 'a fully-tracked balance should never need a synthetic opening-balance reconstruction');

        $earnedEntries = $history->filter(fn($h) => str_contains($h->notes ?? '', 'Earned for'));
        $this->assertCount(1, $earnedEntries, 'exactly one real credit exists; a second, phantom one must not appear alongside it');
    }

    /** The discriminator: a genuinely legacy balance (predates the tracked window) still needs its opening figure reconstructed. */
    public function test_a_legacy_balance_predating_the_ledger_still_gets_its_opening_balance_shown(): void
    {
        $user = $this->makeUser();
        $type = LeaveType::where('code', 'ALP')->firstOrFail();

        // A raw insert, deliberately bypassing Eloquent/Auditable entirely -
        // this is what a genuinely legacy, pre-tracking row actually looks
        // like: real balance/used values with no audit trail explaining
        // them at all, unlike LeaveBalance::create() which itself leaves a
        // "created" audit behind and would understate the case this test
        // needs (real legacy data was never touched via an audited path).
        $legacyDate = \Carbon\Carbon::parse(LeaveHistoryService::LEDGER_OPENS)->subMonths(2);
        $balanceId = \Illuminate\Support\Facades\DB::table('leave_balances')->insertGetId([
            'user_id' => $user->id, 'leave_type_id' => $type->id,
            'balance' => 1.0, 'used' => 1.0,
            'created_at' => $legacyDate, 'updated_at' => $legacyDate,
        ]);
        $balance = LeaveBalance::find($balanceId);

        $history = LeaveHistoryService::getUnifiedHistory($user->id);
        $opening = $history->first(fn($h) => str_starts_with($h->id, 'SYS-OPEN-' . $balance->id));

        $this->assertNotNull($opening, 'a genuinely legacy balance with no tracked audits at all must still show its starting figure');
        $this->assertEquals(2.0, $opening->amount, 'legacy reconstruction must still add used back in - there is nothing else to explain it');
        $this->assertStringContainsString('Opening Balance', $opening->notes, 'a reconstructed legacy figure should read as an opening balance, not as if it were freshly earned');
    }

    /**
     * The ledger mixed every leave type's transactions into one flat list,
     * while the /user/leaves summary cards only total the poolable ALP/PL
     * types (excluding ML/MAT/PL_PAT/PAT/SHL/WFH - see the leaves blade).
     * A Short Leave "Earned for {month}" credit then showed up in the
     * ledger with nothing on the card side to match it, so the ledger's
     * own credit/debit sum silently diverged from Available Balance even
     * though neither number was individually wrong.
     */
    /**
     * A Short Leave BALANCE CREDIT must not become a pool adjustment row -
     * the Available Balance card never counted it, so showing it as a credit
     * beside poolable ones made the ledger's own sum diverge from the card.
     * Scoped to adjustment rows on purpose: this says nothing about whether
     * the employee's actual Short Leave REQUESTS are listed, which they must
     * be - see the test below.
     */
    public function test_a_short_leave_balance_credit_is_not_shown_as_a_pool_adjustment(): void
    {
        $user = $this->makeUser();
        $shl = LeaveType::where('code', 'SHL')->firstOrFail();

        $balance = LeaveBalance::create([
            'user_id' => $user->id, 'leave_type_id' => $shl->id,
            'balance' => 0, 'used' => 0,
        ]);
        LeaveBalance::$auditReason = 'Earned for Test Month';
        $balance->update(['balance' => 1.0]);

        $history = LeaveHistoryService::getUnifiedHistory($user->id);

        $this->assertTrue(
            $history->every(fn($h) => !($h->is_adjustment && $h->leave_type === $shl->name)),
            'a Short Leave credit has no place among the adjustments the poolable-only Available Balance card reconciles against'
        );
    }

    /**
     * THE BUG THIS FILE MISSED: the pool-exclusion list was also used to
     * `continue` past these requests, so an approved Short Leave deducted a
     * balance and then appeared on none of the three screens this collection
     * feeds - My Leaves, the admin Leave Statement, My Profile. Employees
     * saw the Short Leave card say "used" with nothing explaining where it
     * went, assumed the application had failed, and re-applied. 106 short
     * leaves across 76 people were invisible this way. The row must be
     * listed; only its pool contribution is zero.
     */
    public function test_an_approved_short_leave_request_is_listed_but_takes_nothing_from_the_pool(): void
    {
        $user = $this->makeUser();
        $shl  = LeaveType::where('code', 'SHL')->firstOrFail();

        $leave = LeaveRequest::create([
            'user_id'        => $user->id,
            'leave_type_id'  => $shl->id,
            // After LEDGER_OPENS, or the date cut hides it for an unrelated reason.
            'from_date'      => '2026-08-14',
            'to_date'        => '2026-08-14',
            'is_short_leave' => true,
            'duration_hours' => 2.0,
            'status'         => 'pending',
            'user_notes'     => 'short leave visibility test',
        ]);
        $leave->update(['status' => 'approved']);

        $history = LeaveHistoryService::getUnifiedHistory($user->id);
        $row     = $history->first(fn($h) => $h->id === 'REQ-' . $leave->id);

        $this->assertNotNull($row, 'an approved Short Leave must appear in the history the statement screens render');
        $this->assertSame($shl->name, $row->leave_type);
        $this->assertSame(0.0, (float) $row->amount, 'it is listed, but it never drew on the ALP/PL pool, so it must contribute 0 to it');
        $this->assertFalse($row->affects_pool, 'the row must mark itself as outside the pool so no caller totals it');
    }

    /** The control: an ordinary poolable leave still both shows AND charges the pool. */
    public function test_an_ordinary_leave_is_listed_and_does_charge_the_pool(): void
    {
        $user = $this->makeUser();
        $alp  = LeaveType::where('code', 'ALP')->firstOrFail();

        LeaveBalance::create([
            'user_id' => $user->id, 'leave_type_id' => $alp->id,
            'balance' => 5.0, 'used' => 0,
        ]);

        $leave = LeaveRequest::create([
            'user_id'       => $user->id,
            'leave_type_id' => $alp->id,
            'from_date'     => '2026-08-14',
            'to_date'       => '2026-08-14',
            'status'        => 'pending',
            'user_notes'    => 'poolable control',
        ]);
        $leave->update(['status' => 'approved']);

        $row = LeaveHistoryService::getUnifiedHistory($user->id)
            ->first(fn($h) => $h->id === 'REQ-' . $leave->id);

        $this->assertNotNull($row);
        $this->assertTrue($row->affects_pool);
        $this->assertSame(-1.0, (float) $row->amount, 'a poolable approved day must still show as a real pool deduction');
    }
}
