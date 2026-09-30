<?php

namespace Tests\Feature;

use App\Models\OutdoorDuty;
use App\Models\User;
use App\Notifications\Leave\NewOutdoorDuty;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Outdoor duty requests were saved with no alert to anyone, and the dashboard
 * pending counts/card only counted leave requests - so approvers never learned
 * one was waiting. Its approve endpoint also answered 200 when it refused, and
 * let any manager approve anyone's request.
 */
class OutdoorDutyApprovalAlertTest extends TestCase
{
    use DatabaseTransactions;

    private function person(?string $role = null, ?int $reportsTo = null): User
    {
        Mail::fake();
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'OD ' . uniqid(), 'email' => 'qaod' . uniqid() . '@example.test',
            'code' => 'QO' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        $u->forceFill(['reporting_to_id' => $reportsTo])->saveQuietly();
        if ($role) {
            $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        }

        return $u->fresh();
    }

    public function test_applying_alerts_the_manager_without_email(): void
    {
        Notification::fake();
        $manager = $this->person('manager');
        $emp = $this->person('employee', $manager->id);

        $this->actingAs($emp)->postJson(route('user.outdoor_duty.storeAjax'), [
            'od_date' => now()->addDay()->toDateString(), 'od_purpose' => 'Plant visit', 'od_location' => 'Manesar',
        ])->assertOk()->assertJson(['status' => 'success']);

        Notification::assertSentTo($manager, NewOutdoorDuty::class, function ($n, $channels) {
            return in_array('database', $channels, true) && !in_array('mail', $channels, true);
        });
    }

    public function test_a_pending_request_is_counted_and_listed_on_the_admin_dashboard(): void
    {
        $emp = $this->person('employee');
        $admin = $this->person('admin');
        Auth::login($admin);
        $data = fn () => app(\App\Http\Controllers\tenant\DashboardController::class)->index()->getData();

        Cache::flush();
        $before = $data()['pendingLeaveRequests'];
        $od = OutdoorDuty::create(['user_id' => $emp->id, 'date' => now()->toDateString(), 'purpose' => 'QA', 'status' => 'pending', 'created_by_id' => $emp->id]);
        Cache::flush();
        $after = $data();

        $this->assertSame($before + 1, $after['pendingLeaveRequests'], 'counted with pending leave');
        $this->assertTrue(collect($after['pendingApprovals'])->contains(fn ($a) => $a['type'] === 'Outdoor Duty' && $a['id'] === $od->id), 'listed on the Pending Approvals card');
    }

    public function test_approval_is_limited_to_the_managers_team_and_refusals_are_403(): void
    {
        $manager = $this->person('manager');
        $mine = $this->person('employee', $manager->id);
        $other = $this->person('employee');
        $od = fn (User $u) => OutdoorDuty::create(['user_id' => $u->id, 'date' => now()->toDateString(), 'purpose' => 'QA', 'status' => 'pending', 'created_by_id' => $u->id]);

        $theirs = $od($other);
        $this->actingAs($manager)->postJson(route('leaveRequests.outdoorDuty.actionAjax'), ['id' => $theirs->id, 'status' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $theirs->fresh()->status);

        $own = $od($manager);
        $this->actingAs($manager)->postJson(route('leaveRequests.outdoorDuty.actionAjax'), ['id' => $own->id, 'status' => 'approved'])->assertForbidden();

        $team = $od($mine);
        $this->actingAs($manager)->postJson(route('leaveRequests.outdoorDuty.actionAjax'), ['id' => $team->id, 'status' => 'approved'])->assertOk();
        $this->assertSame('approved', $team->fresh()->status);
    }
}
