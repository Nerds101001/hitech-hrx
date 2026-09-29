<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeavePolicyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AttendanceController::index()'s header cards (Present/Absent/Late) and
 * chartAjax()'s trend chart both used to count Attendance rows by matching
 * raw status text - so an uncorrected miss-punch (real check-in, no
 * check-out, raw status still 'Present' by default) inflated the Present
 * card exactly the same way it inflated payroll, and a worked designated
 * off-Saturday never counted as present at all, since its raw status never
 * says 'present'. Both now go through the same AttendanceDayStatus resolver
 * as every other attendance surface.
 */
class AttendanceDashboardCardsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(?string $role = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Dash',
            'email'      => 'qadash' . uniqid() . '@example.test',
            'code'       => 'QADS' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u;
    }

    private function callIndex(): array
    {
        $admin = $this->makeUser('admin');
        Auth::login($admin);
        $view = app(\App\Http\Controllers\tenant\AttendanceController::class)->index();
        return $view->getData();
    }

    private function callChart(array $params): array
    {
        $admin = $this->makeUser('admin');
        Auth::login($admin);
        $response = app(\App\Http\Controllers\tenant\AttendanceController::class)
            ->chartAjax(Request::create('/attendance/chartAjax', 'GET', $params));
        return json_decode($response->getContent(), true);
    }

    /**
     * Updated 2026-09-29. This test was written on 1 Sep, when a check-in with
     * no check-out today was treated as a miss-punch (Absent). On 4 Sep
     * (3528d6e7d) AttendanceDayStatus deliberately changed that: a day still
     * running is not a missed punch, the person is at work. A missed punch is
     * only a PAST day with no check-out.
     */
    public function test_index_counts_someone_checked_in_today_and_not_yet_out_as_present(): void
    {
        // Delta-based: this local database carries real synced attendance
        // data, so today's absolute counts aren't a clean baseline - only
        // the change this one row causes is.
        $before = $this->callIndex();

        $employee = $this->makeUser();
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => today()->setTime(9, 0),
            'check_out_time' => null,
            'status' => 'Present',
        ]);

        $after = $this->callIndex();

        $this->assertEquals($before['todayPresentCount'] + 1, $after['todayPresentCount'], 'still at work today counts as Present');
        $this->assertEquals($before['todayAbsentCount'], $after['todayAbsentCount'], 'and not as Absent');
    }

    public function test_index_present_card_counts_a_worked_off_saturday(): void
    {
        $today = today();
        if (!$today->isSaturday()) {
            $this->markTestSkipped('today is not a Saturday in this run');
        }
        $before = $this->callIndex();

        $occurrence = (string) (int) ceil($today->day / 7);
        $profile = LeavePolicyProfile::create(['name' => 'QA Sat Off ' . uniqid(), 'saturday_off_config' => [$occurrence], 'status' => 'active']);
        $employee = $this->makeUser();
        $employee->update(['leave_policy_profile_id' => $profile->id]);

        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => $today->copy()->setTime(9, 0),
            'check_out_time' => $today->copy()->setTime(13, 0), // 240 min - clears the SP bar
            'status' => 'Present',
        ]);

        $after = $this->callIndex();

        $this->assertEquals($before['todayPresentCount'] + 1, $after['todayPresentCount'], 'a credited off-Saturday day must count toward Present');
    }

    public function test_chart_deduplicates_a_user_with_two_rows_on_the_same_date(): void
    {
        $employee = $this->makeUser();
        $date = today()->subDays(2);
        // A dummy leave-linked row and a real punch on the same date - a real
        // scenario this file's own resolver-based row-selection already
        // handles elsewhere (prefers the real punch).
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => $date->copy()->setTime(0, 0),
            'check_out_time' => null,
            'status' => 'Present',
        ]);
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => $date->copy()->setTime(9, 0),
            'check_out_time' => $date->copy()->setTime(18, 0),
            'status' => 'Present',
        ]);

        $data = $this->callChart(['period' => '7days', 'userId' => $employee->id]);

        $dateIndex = array_search($date->format('D'), $data['categories']);
        $this->assertNotFalse($dateIndex);
        $this->assertEquals(1, $data['series'][0]['data'][$dateIndex], 'two rows for the same user on the same date must count once');
    }
}
