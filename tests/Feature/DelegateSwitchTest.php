<?php

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Http\Controllers\tenant\users\UserDashboardController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Delegate access — the one-click "work as this user" switch.
 *
 * Two ways in, and they must not blur into each other:
 *  - the header hub, where a normal user may only enter a vacant-position
 *    account whose access_delegate_id points at them, never a privileged one;
 *  - the Employee Directory, where a super delegate (config/access.php) may
 *    enter any active account.
 * Exit must always land back on the real account, and must refuse to restore
 * an account that no longer has the right to be there.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds real
 * imported production data.
 */
class DelegateSwitchTest extends TestCase
{
    use DatabaseTransactions;

    private UserDashboardController $controller;
    private User $super;
    private User $employee;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->controller = app(UserDashboardController::class);

        $email = config('access.super_delegate_emails')[0] ?? 'ai@rustx.com';
        $this->super = User::where('email', $email)->firstOrFail();

        [$this->employee, $this->other] = User::role('employee')
            ->where('status', UserAccountStatus::ACTIVE)
            ->whereNull('access_delegate_id')
            ->orderBy('id')
            ->take(2)
            ->get()
            ->all();
    }

    protected function tearDown(): void
    {
        session()->forget(['delegate_switch_original_id', 'delegate_switch_super']);
        Auth::logout();
        parent::tearDown();
    }

    public function test_super_delegate_can_switch_into_any_active_account(): void
    {
        $this->assertTrue($this->super->isSuperDelegate());

        Auth::login($this->super);
        $this->controller->switchToDelegate($this->employee->id);

        $this->assertSame($this->employee->id, Auth::id());
        $this->assertSame($this->super->id, session('delegate_switch_original_id'));
        $this->assertTrue(session('delegate_switch_super'));
    }

    public function test_super_delegate_returns_to_own_account_on_exit(): void
    {
        Auth::login($this->super);
        $this->controller->switchToDelegate($this->employee->id);
        $this->controller->exitDelegate();

        $this->assertSame($this->super->id, Auth::id());
        $this->assertNull(session('delegate_switch_original_id'));
        $this->assertNull(session('delegate_switch_super'));
    }

    /**
     * Regression: auth()->login() migrates the session, which destroyed the
     * session file out from under any request already in flight. That request
     * then handed the browser back the dead id and the next refresh landed on
     * the login page. Switching must reuse the same session throughout.
     */
    public function test_switch_and_exit_do_not_rotate_the_session_id(): void
    {
        Auth::login($this->super);
        $before = session()->getId();

        $this->controller->switchToDelegate($this->employee->id);
        $this->assertSame($before, session()->getId(), 'switching must not rotate the session id');
        $this->assertSame($this->employee->id, Auth::id());

        $this->controller->exitDelegate();
        $this->assertSame($before, session()->getId(), 'exiting must not rotate the session id');
        $this->assertSame($this->super->id, Auth::id());
    }

    public function test_ordinary_user_cannot_switch_into_an_undelegated_account(): void
    {
        $this->assertFalse($this->employee->isSuperDelegate());

        Auth::login($this->employee);
        $this->controller->switchToDelegate($this->other->id);

        $this->assertSame($this->employee->id, Auth::id());
        $this->assertNull(session('delegate_switch_original_id'));
    }

    public function test_ordinary_user_cannot_switch_into_a_privileged_account_even_when_delegated(): void
    {
        // Pointer says "you may act for the admin account" — the role check
        // must still refuse, that is the escalation guard.
        $this->super->forceFill(['access_delegate_id' => $this->employee->id])->save();

        Auth::login($this->employee);
        $this->controller->switchToDelegate($this->super->id);

        $this->assertSame($this->employee->id, Auth::id());
        $this->assertNull(session('delegate_switch_original_id'));
    }

    public function test_switching_to_own_account_is_rejected(): void
    {
        Auth::login($this->super);
        $this->controller->switchToDelegate($this->super->id);

        $this->assertNull(session('delegate_switch_original_id'));
    }

    public function test_switching_while_already_in_a_delegate_session_is_rejected(): void
    {
        Auth::login($this->super);
        $this->controller->switchToDelegate($this->employee->id);
        $this->controller->switchToDelegate($this->other->id);

        // Still inside the first account, not hopped into the second.
        $this->assertSame($this->employee->id, Auth::id());
    }

    public function test_exit_logs_out_when_the_original_account_lost_super_rights(): void
    {
        Auth::login($this->super);
        $this->controller->switchToDelegate($this->employee->id);

        // Simulate the allow-list being tightened mid-session.
        config(['access.super_delegate_emails' => ['nobody@example.com']]);
        $this->controller->exitDelegate();

        $this->assertFalse(Auth::check());
        $this->assertNull(session('delegate_switch_original_id'));
    }

    /**
     * The UI hides the control for everyone else — these two prove the gate is
     * real, by POSTing the route directly the way a curious admin with the
     * browser console open would.
     */
    public function test_another_admin_posting_the_route_directly_is_refused(): void
    {
        $otherAdmin = User::role('admin')
            ->where('email', '!=', $this->super->email)
            ->where('status', UserAccountStatus::ACTIVE)
            ->firstOrFail();

        $this->assertFalse($otherAdmin->isSuperDelegate());

        $this->actingAs($otherAdmin)
            ->post('/user/delegation/switch/' . $this->employee->id)
            ->assertRedirect();

        $this->assertSame($otherAdmin->id, auth()->id());
        $this->assertNull(session('delegate_switch_original_id'));
    }

    public function test_an_employee_posting_the_route_directly_is_refused(): void
    {
        $this->actingAs($this->employee)
            ->post('/user/delegation/switch/' . $this->other->id)
            ->assertRedirect();

        $this->assertSame($this->employee->id, auth()->id());
        $this->assertNull(session('delegate_switch_original_id'));
    }

    /** A near-miss email must not slip through the allow-list. */
    public function test_lookalike_email_does_not_qualify(): void
    {
        $imposter = new User(['email' => 'ai@rustx.com.attacker.io']);
        $this->assertFalse($imposter->isSuperDelegate());

        $spaced = new User(['email' => ' ai@rustx.com ']);
        $this->assertFalse($spaced->isSuperDelegate());

        // Case is the one thing that should not matter.
        $upper = new User(['email' => 'AI@RustX.com']);
        $this->assertTrue($upper->isSuperDelegate());
    }

    public function test_inactive_accounts_cannot_be_entered(): void
    {
        $this->employee->forceFill(['status' => UserAccountStatus::INACTIVE])->save();

        Auth::login($this->super);
        $this->controller->switchToDelegate($this->employee->id);

        $this->assertSame($this->super->id, Auth::id());
        $this->assertNull(session('delegate_switch_original_id'));
    }
}
