<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeavePolicyProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * DashboardController's admin cards, 7-day trend charts, manager Team
 * Deployment widget, and liveStats() all independently counted "present"
 * today by matching raw Attendance status text or, in two places, by
 * counting any Attendance row at all regardless of status - so an
 * uncorrected miss-punch (or a dummy leave-linked row with no real punch)
 * inflated every one of these, and a worked designated off-Saturday never
 * counted as present anywhere. All now go through the same
 * AttendanceDayStatus resolver every other attendance surface shares.
 *
 * Delta-based throughout: this local database carries real synced
 * attendance data, so today's absolute counts aren't a clean baseline.
 */
class DashboardAttendanceCountsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(?string $role = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'DashCount',
            'email'      => 'qadashcount' . uniqid() . '@example.test',
            'code'       => 'QADC' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function liveStats(User $as): array
    {
        Auth::login($as);
        $response = app(\App\Http\Controllers\tenant\DashboardController::class)->liveStats();
        return json_decode($response->getContent(), true);
    }

    /**
     * Updated 2026-09-29: written on 1 Sep, when an open check-in today counted
     * as a miss-punch. Since 3528d6e7d (4 Sep) a day still running is not a
     * missed punch - someone checked in today and not yet out is at work.
     */
    public function test_live_stats_admin_path_counts_someone_still_at_work_today_as_present(): void
    {
        $admin = $this->makeUser('admin');
        $before = $this->liveStats($admin);

        $employee = $this->makeUser();
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => today()->setTime(9, 0),
            'check_out_time' => null,
            'status' => 'Present',
        ]);

        $after = $this->liveStats($admin);

        $this->assertEquals(
            (int) str_replace(',', '', $before['present_count']) + 1,
            (int) str_replace(',', '', $after['present_count']),
            'still at work today counts as present'
        );
    }

    public function test_live_stats_manager_path_counts_a_worked_off_saturday_as_present(): void
    {
        Carbon::setTestNow('2026-06-13 10:00:00'); // the 2nd Saturday of June 2026
        $today = today();

        $manager = $this->makeUser('manager');
        $before = $this->liveStats($manager);

        $occurrence = (string) (int) ceil($today->day / 7);
        $profile = LeavePolicyProfile::create(['name' => 'QA Sat Off ' . uniqid(), 'saturday_off_config' => [$occurrence], 'status' => 'active']);
        $report = $this->makeUser();
        $report->forceFill(['reporting_to_id' => $manager->id])->save();
        $report->update(['leave_policy_profile_id' => $profile->id]);
        Attendance::create([
            'user_id' => $report->id,
            'check_in_time' => $today->copy()->setTime(9, 0),
            'check_out_time' => $today->copy()->setTime(13, 0), // 240 min - clears the SP bar
            'status' => 'Present',
        ]);

        $after = $this->liveStats($manager);

        $this->assertEquals(
            (int) str_replace(',', '', $before['present_count']) + 1,
            (int) str_replace(',', '', $after['present_count']),
            'a credited off-Saturday day must count toward present for the manager view too'
        );
    }

    public function test_team_deployment_shows_a_holiday_as_off_not_absent(): void
    {
        Carbon::setTestNow('2026-06-14 10:00:00'); // Sunday

        $manager = $this->makeUser('manager');
        $report = $this->makeUser();
        $report->forceFill(['reporting_to_id' => $manager->id])->save();

        Auth::login($manager);
        $view = app(\App\Http\Controllers\tenant\DashboardController::class)->index();
        $deploy = $view->getData()['teamDeployment'] ?? collect();
        $entry = $deploy->first(fn ($d) => $d->user->id === $report->id);

        $this->assertNotNull($entry);
        $this->assertEquals('off', $entry->deploy_status, 'a Sunday with no attendance and no leave must show as a day off, not a false absence');
    }
}
