<?php

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The super delegate's "Switch to this user" in the Employee Directory refused
 * everyone still in onboarding ("not found or is not active"). It now enters
 * onboarding accounts too - and the way back works: onboarding accounts are
 * locked to the onboarding form / training portal, which have no top navbar,
 * so the Exit request was redirected away and there was no Exit button.
 */
class DelegateSwitchOnboardingTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->admin = $this->person('admin', UserAccountStatus::ACTIVE->value);
        config(['access.super_delegate_emails' => [$this->admin->email]]);
    }

    private function person(string $role, string $status, bool $training = false): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => ucfirst($role) . ' ' . uniqid(), 'email' => 'qadel' . uniqid() . '@example.test',
            'code' => 'QD' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => $status,
        ]);
        $u->forceFill(['is_training_required' => $training, 'training_status' => $training ? 'not_started' : null])->save();
        $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $u->fresh();
    }

    public function test_switching_into_a_joiner_held_for_training_and_back(): void
    {
        $joiner = $this->person('employee', UserAccountStatus::ONBOARDING_SUBMITTED->value, true);

        $this->actingAs($this->admin)->post('/user/delegation/switch/' . $joiner->id)->assertRedirect('/user/dashboard');
        $this->assertSame($this->admin->id, session('delegate_switch_original_id'));
        $this->assertSame($joiner->id, auth()->id());

        // Their world: the training portal - with a way out.
        $html = $this->get('/training')->assertOk()->getContent();
        $this->assertStringContainsString('Delegate access active', $html);
        $this->assertStringContainsString('/user/delegation/exit', $html);

        // Exit is not swallowed by the onboarding lock.
        $this->post('/user/delegation/exit')->assertRedirect('/employees');
        $this->assertSame($this->admin->id, auth()->id());
        $this->assertNull(session('delegate_switch_original_id'));
    }

    public function test_switching_into_someone_still_filling_the_onboarding_form(): void
    {
        $joiner = $this->person('employee', UserAccountStatus::ONBOARDING->value);
        $this->actingAs($this->admin)->post('/user/delegation/switch/' . $joiner->id);
        $this->assertSame($joiner->id, auth()->id());
        $this->get('/user/dashboard')->assertRedirect(route('onboarding.form'));
        $this->post('/user/delegation/exit');
        $this->assertSame($this->admin->id, auth()->id());
    }

    public function test_active_accounts_still_work(): void
    {
        $emp = $this->person('employee', UserAccountStatus::ACTIVE->value);
        $this->actingAs($this->admin)->post('/user/delegation/switch/' . $emp->id);
        $this->assertSame($emp->id, auth()->id());
    }

    public function test_accounts_that_have_left_stay_closed(): void
    {
        foreach ([UserAccountStatus::RELIEVED, UserAccountStatus::SUSPENDED, UserAccountStatus::TERMINATED] as $status) {
            $gone = $this->person('employee', $status->value);
            $this->actingAs($this->admin)->post('/user/delegation/switch/' . $gone->id)->assertSessionHas('error');
            $this->assertSame($this->admin->id, auth()->id(), $status->value);
        }
    }

    public function test_an_ordinary_delegate_still_enters_active_accounts_only(): void
    {
        $mgr = $this->person('manager', UserAccountStatus::ACTIVE->value);
        $joiner = $this->person('employee', UserAccountStatus::ONBOARDING_SUBMITTED->value, true);
        $joiner->forceFill(['access_delegate_id' => $mgr->id])->save();

        $this->actingAs($mgr)->post('/user/delegation/switch/' . $joiner->id)->assertSessionHas('error');
        $this->assertSame($mgr->id, auth()->id());
    }
}
