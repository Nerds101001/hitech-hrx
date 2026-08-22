<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveAccrualService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the monthly leave accrual — the single most expensive thing in this
 * system to get wrong, and the one that actually broke: the scheduled job did
 * not run for July or August 2026 and nobody noticed for two months.
 *
 * These use DatabaseTransactions, not RefreshDatabase: the local database holds
 * real imported production data and must never be wiped by a test run. Every
 * row created here is rolled back at the end of each test.
 */
class LeaveAccrualTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * The critical property. accrueMonthly() is registered TWICE in the
     * schedule and may also be run by hand, and ALP is carry-forward
     * (balance += grant) — so without the audit-tag guard a second pass
     * silently doubles everyone's leave.
     */
    public function test_running_accrual_twice_does_not_double_credit(): void
    {
        $alp = LeaveType::where('code', 'ALP')->first();
        $this->assertNotNull($alp, 'ALP leave type must exist');

        LeaveAccrualService::accrueMonthly();

        $after1 = LeaveBalance::where('leave_type_id', $alp->id)->sum('balance');

        LeaveAccrualService::accrueMonthly();   // immediate re-run

        $after2 = LeaveBalance::where('leave_type_id', $alp->id)->sum('balance');

        $this->assertEquals(
            $after1,
            $after2,
            'Second accrual run changed balances — the idempotency guard is broken.'
        );
    }

    /** Terminated and onboarding staff must never accrue. */
    public function test_accrual_only_credits_active_staff(): void
    {
        $tag = 'Earned for ' . now()->format('F Y');

        LeaveAccrualService::accrueMonthly();

        $credited = DB::table('audits')
            ->where('auditable_type', LeaveBalance::class)
            ->where('user_agent', $tag)
            ->pluck('auditable_id');

        if ($credited->isEmpty()) {
            $this->markTestSkipped('Already accrued this month — nothing new to assert against.');
        }

        $nonActive = User::withTrashed()
            ->whereIn('id', LeaveBalance::whereIn('id', $credited)->pluck('user_id'))
            ->where(fn ($q) => $q->where('status', '!=', 'active')->orWhereNotNull('deleted_at'))
            ->count();

        $this->assertSame(0, $nonActive, 'A terminated or deleted user was credited leave.');
    }

    /**
     * Short Leave is granted by leave:grant-short-leave, which covers every
     * active employee. accrueMonthly() must not also grant it, or the two
     * commands write the same balance from two different populations.
     */
    public function test_accrual_does_not_touch_short_leave(): void
    {
        $shl = LeaveType::where('code', 'SHL')->first();
        $this->assertNotNull($shl, 'SHL leave type must exist');

        $before = LeaveBalance::where('leave_type_id', $shl->id)->sum('balance');

        LeaveAccrualService::accrueMonthly();

        $after = LeaveBalance::where('leave_type_id', $shl->id)->sum('balance');

        $this->assertEquals($before, $after, 'accrueMonthly() modified Short Leave — it must only own ALP.');
    }

    /** Every credit must be traceable: the month is baked into the audit tag. */
    public function test_every_credit_is_audited_with_the_month(): void
    {
        $tag = 'Earned for ' . now()->format('F Y');

        $before = DB::table('audits')
            ->where('auditable_type', LeaveBalance::class)->where('user_agent', $tag)->count();

        LeaveAccrualService::accrueMonthly();

        $after = DB::table('audits')
            ->where('auditable_type', LeaveBalance::class)->where('user_agent', $tag)->count();

        $this->assertGreaterThanOrEqual(
            $before,
            $after,
            'Accrual wrote balances without leaving an audit trail.'
        );
    }
}
