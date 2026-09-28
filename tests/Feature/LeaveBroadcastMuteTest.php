<?php

namespace Tests\Feature;

use App\Channels\FirebaseChannel;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\Leave\CancelLeaveRequest;
use App\Notifications\Leave\NewLeaveRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every leave request is broadcast to every HR-role user. One HR manager was
 * getting 121 a fortnight for leave she does not process, so a person can now
 * switch the broadcast off (notification_preferences.leave_request = false).
 *
 * What must hold: a muted HR user hears nothing; nobody else is affected; and
 * the switch never silences a request that is waiting on that person as the
 * employee's own manager.
 */
class LeaveBroadcastMuteTest extends TestCase
{
    use DatabaseTransactions;

    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->dept = Department::create(['name' => 'ZZ Leave Dept ' . uniqid(), 'code' => 'ZZL' . random_int(1000, 9999)]);
    }

    private function person(array $extra = []): User
    {
        // reporting_to_id is not mass-assignable; create() would drop it silently.
        $manager = array_key_exists('reporting_to_id', $extra) ? $extra['reporting_to_id'] : null;
        unset($extra['reporting_to_id']);
        $u = User::create($extra + [
            'first_name' => 'QA', 'last_name' => 'Leave ' . uniqid(),
            'email' => 'qa' . uniqid() . '@example.test',
            'code' => 'QL' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'),
            'status' => 'active',
            'department_id' => $this->dept->id,
        ]);
        $u->forceFill(['reporting_to_id' => $manager])->save();

        return $u->fresh();
    }

    private function mute(User $u): void
    {
        DB::table('notification_preferences')->insert([
            'user_id' => $u->id, 'preferences' => json_encode(['leave_request' => false]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function leaveFor(User $employee): LeaveRequest
    {
        $lr = new LeaveRequest();
        $lr->setRelation('user', $employee);
        $lr->user_id = $employee->id;

        return $lr;
    }

    public function test_a_muted_hr_user_gets_nothing_for_a_new_request(): void
    {
        $manager = $this->person();
        $employee = $this->person(['reporting_to_id' => $manager->id]);
        $hr = $this->person();
        $this->mute($hr);

        $this->assertSame([], (new NewLeaveRequest($this->leaveFor($employee)))->via($hr));
    }

    public function test_an_hr_user_who_has_not_muted_still_gets_the_broadcast(): void
    {
        $manager = $this->person();
        $employee = $this->person(['reporting_to_id' => $manager->id]);
        $hr = $this->person();

        $this->assertSame(['database', FirebaseChannel::class], (new NewLeaveRequest($this->leaveFor($employee)))->via($hr));
    }

    public function test_muting_never_hides_a_request_waiting_on_you_as_the_manager(): void
    {
        $manager = $this->person();
        $employee = $this->person(['reporting_to_id' => $manager->id]);
        $this->mute($manager);

        $this->assertSame(['database', 'mail', FirebaseChannel::class], (new NewLeaveRequest($this->leaveFor($employee)))->via($manager));
    }

    public function test_a_muted_hr_user_gets_nothing_for_a_cancellation(): void
    {
        $employee = $this->person(['reporting_to_id' => $this->person()->id]);
        $hr = $this->person();
        $this->mute($hr);

        $this->assertSame([], (new CancelLeaveRequest($this->leaveFor($employee)))->via($hr));
    }

    public function test_no_manager_leave_outside_the_three_sites_is_emailed_to_suchita(): void
    {
        $suchita = User::where('email', 'suchita@rustx.com')->first()
            ?? $this->person(['email' => 'suchita@rustx.com']);
        $suchita->forceFill(['status' => 'active'])->save();
        $priyal = User::where('email', 'priyal@rustx.com')->first();

        $employee = $this->person(['reporting_to_id' => null]);
        $n = new NewLeaveRequest($this->leaveFor($employee));

        $this->assertContains('mail', $n->via($suchita), 'Suchita should now receive the no-manager email');
        if ($priyal) {
            $this->assertNotContains('mail', $n->via($priyal), 'Priyal should no longer be emailed');
        }
    }
}
