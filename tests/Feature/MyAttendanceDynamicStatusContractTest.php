<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * resources/views/tenant/users/attendance/index.blade.php (the "My
 * Attendance" List View) reads UserDashboardController::attendanceIndex()'s
 * $attendance->dynamic_status field and compares it against literal strings
 * - 'miss-punch' and 'half-day' (hyphenated), matching the values that field
 * held before AttendanceDayStatus consolidated the status logic. When the
 * consolidation briefly used underscored codes ('miss_punch'/'half_day')
 * instead, the Blade's comparisons silently stopped matching - a worked day
 * with a real punch-in and no punch-out on a past day would fall through to
 * the badge's default "Present" (green) instead of "Miss Punch" (pink),
 * hiding a punch that genuinely needs an HR/Admin correction.
 *
 * This locks the exact string contract the Blade depends on, since nothing
 * else would catch a silent string-spelling mismatch like this - the
 * controller-side tests only ever asserted against known, correct codes,
 * never against what the Blade template itself expects to receive.
 */
class MyAttendanceDynamicStatusContractTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'Contract',
            'email'      => 'qacontract' . uniqid() . '@example.test',
            'code'       => 'QACT' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
    }

    private function dynamicStatusFor(User $employee, string $date): string
    {
        Auth::login($employee);
        $view = app(\App\Http\Controllers\tenant\users\UserDashboardController::class)
            ->attendanceIndex(Request::create('/x', 'GET', ['start_date' => $date, 'end_date' => $date, 'filter' => 'custom']));
        $attendances = $view->getData()['attendances'];
        $day = $attendances->first(fn ($a) => $a->created_at->toDateString() === $date);
        return $day->dynamic_status;
    }

    public function test_a_missed_punch_reports_the_hyphenated_dynamic_status_the_blade_expects(): void
    {
        $employee = $this->makeUser();
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2020-06-15 09:00:00', // definitely in the past
            'check_out_time' => null,
            'status' => 'Present',
        ]);

        $this->assertEquals('miss-punch', $this->dynamicStatusFor($employee, '2020-06-15'));
    }

    public function test_a_half_day_reports_the_hyphenated_dynamic_status_the_blade_expects(): void
    {
        $employee = $this->makeUser();
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-15 09:00:00',
            'check_out_time' => '2026-06-15 13:00:00', // 240 min: clears half-day, under the 465-min present bar
            'status' => 'Present',
        ]);

        $this->assertEquals('half-day', $this->dynamicStatusFor($employee, '2026-06-15'));
    }
}
