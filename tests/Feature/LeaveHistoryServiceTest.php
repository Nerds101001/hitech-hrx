<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
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
}
