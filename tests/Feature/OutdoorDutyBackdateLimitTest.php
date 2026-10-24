<?php

namespace Tests\Feature;

use App\Models\LeaveUnlock;
use App\Models\OutdoorDuty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Outdoor Duty's own date picker locked to today only ("min" = today) with no
 * backdating at all, while the server side took any date with no check -
 * neither matched the one-week rule the regular leave form uses
 * (LeaveBackdate). Now both sides share the same rule: up to 7 days back, or
 * further with an HR leave unlock window.
 */
class OutdoorDutyBackdateLimitTest extends TestCase
{
    use DatabaseTransactions;

    private User $emp;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-24 11:00:00'));
        $this->emp = User::create([
            'first_name' => 'QA', 'last_name' => 'OD Backdate ' . uniqid(), 'email' => 'qaodbd' . uniqid() . '@example.test',
            'code' => 'QOB' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        $this->emp->assignRole(Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function apply(string $date)
    {
        return $this->actingAs($this->emp)->postJson(route('user.outdoor_duty.storeAjax'), [
            'od_date' => $date, 'od_purpose' => 'Client site visit',
        ]);
    }

    public function test_exactly_seven_days_back_is_allowed(): void
    {
        $this->apply('2026-10-17')->assertOk()->assertJson(['status' => 'success']);
        $this->assertNotNull(OutdoorDuty::where('user_id', $this->emp->id)->whereDate('date', '2026-10-17')->first());
    }

    public function test_eight_days_back_is_rejected(): void
    {
        $this->apply('2026-10-16')->assertStatus(422)->assertJsonFragment(['status' => 'error']);
        $this->assertNull(OutdoorDuty::where('user_id', $this->emp->id)->whereDate('date', '2026-10-16')->first());
    }

    public function test_an_open_unlock_window_allows_further_back(): void
    {
        LeaveUnlock::create([
            'scope' => 'all', 'user_ids' => null,
            'from_date' => '2026-10-01', 'to_date' => '2026-10-31',
            'is_active' => true, 'created_by' => $this->emp->id,
        ]);

        $this->apply('2026-10-05')->assertOk()->assertJson(['status' => 'success']);
    }

    public function test_today_and_future_dates_are_unaffected(): void
    {
        $this->apply('2026-10-24')->assertOk()->assertJson(['status' => 'success']);
    }
}
