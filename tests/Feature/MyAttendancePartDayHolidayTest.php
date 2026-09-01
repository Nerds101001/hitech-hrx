<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UserDashboardController::attendanceRegistryAjax() (the "My Attendance"
 * personal calendar) and attendanceIndex() (its List View) both determined
 * $isHoliday from "any Holiday row exists on this date" - not from whether
 * that holiday was a FULL day. A part-day concession (e.g. a 90-minute
 * morning-off) therefore short-circuited into the Holiday branch, hiding the
 * employee's real status: an absence rendered as "Holiday" and a genuine
 * approved paid leave also rendered as "Holiday" instead of "Paid Leave".
 * Reported live via screenshots of two employees both showing "HOLIDAY" on
 * 2026-08-28 (a partial Rakhi concession) despite having different real
 * statuses (Absent vs Paid Leave) on the admin-side Monthly Registry.
 *
 * This is the third and fourth copy of a bug already fixed once in
 * LeavePolicyService::isWorkingDay() and once in the admin AttendanceController
 * - each of these four surfaces carried its own separate holiday-type check.
 *
 * 2026-06-15 (Monday) / 2026-06-16 (Tuesday) are used because they do not
 * coincide with any holiday already in this database and are not Sundays.
 */
class MyAttendancePartDayHolidayTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'MyAttendance',
            'email'      => 'qamyatt' . uniqid() . '@example.test',
            'code'       => 'QAMA' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
    }

    private function partDayHoliday(string $date): Holiday
    {
        return Holiday::create([
            'name' => 'QA Partial Rakhi',
            'code' => 'QAPD' . random_int(100000, 999999),
            'date' => $date,
            'status' => 'active',
            'holiday_type' => 'hours',
            'hours_off' => 1.5, // 90 minutes concession -> required bar = 465-90 = 375 (6h15m)
        ]);
    }

    private function callCalendar(User $employee, array $params): array
    {
        Auth::login($employee);
        $response = app(\App\Http\Controllers\tenant\users\UserDashboardController::class)
            ->attendanceRegistryAjax(Request::create('/x', 'GET', $params));
        return json_decode($response->getContent(), true);
    }

    private function callListView(User $employee, array $params): \Illuminate\Support\Collection
    {
        Auth::login($employee);
        $view = app(\App\Http\Controllers\tenant\users\UserDashboardController::class)
            ->attendanceIndex(Request::create('/x', 'GET', $params));
        return $view->getData()['attendances'];
    }

    public function test_calendar_shows_absent_not_holiday_with_no_punch_on_a_part_day_holiday(): void
    {
        $employee = $this->makeUser();
        $this->partDayHoliday('2026-06-15');

        $data = $this->callCalendar($employee, ['month' => 6, 'year' => 2026]);

        $this->assertEquals('Absent', $data['calendar'][15]['status']);
    }

    public function test_calendar_shows_absent_not_holiday_for_a_dummy_absence_row_on_a_part_day_holiday(): void
    {
        $employee = $this->makeUser();
        $this->partDayHoliday('2026-06-15');

        // Mirrors the real production shape: a midnight-stamped attendance
        // row with no check-out, left behind by an unpaid/absence marking.
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-15 00:00:00',
            'check_out_time' => null,
            'status' => 'absent',
        ]);

        $data = $this->callCalendar($employee, ['month' => 6, 'year' => 2026]);

        $this->assertEquals('Absent', $data['calendar'][15]['status']);
    }

    public function test_calendar_shows_paid_leave_not_holiday_for_an_approved_leave_on_a_part_day_holiday(): void
    {
        $employee = $this->makeUser();
        $this->partDayHoliday('2026-06-15');
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        LeaveBalance::create(['user_id' => $employee->id, 'leave_type_id' => $alp->id, 'balance' => 2.0, 'used' => 0]);

        $leave = LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-06-15', 'to_date' => '2026-06-15',
            'status' => 'pending', 'user_notes' => 'my-attendance part-day holiday paid leave test',
        ]);
        $leave->update(['status' => 'approved']); // triggers processApproval - creates the Attendance row itself

        $data = $this->callCalendar($employee, ['month' => 6, 'year' => 2026]);

        $this->assertEquals('Paid Leave', $data['calendar'][15]['status']);
    }

    public function test_calendar_shows_present_when_worked_minutes_clear_the_concession_adjusted_bar(): void
    {
        $employee = $this->makeUser();
        $this->partDayHoliday('2026-06-15');

        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-15 09:00:00',
            'check_out_time' => '2026-06-15 15:30:00', // 6h30m = 390 min, clears the 375-min reduced bar
            'status' => 'Present',
        ]);

        $data = $this->callCalendar($employee, ['month' => 6, 'year' => 2026]);

        $this->assertEquals('Present', $data['calendar'][15]['status']);
    }

    public function test_calendar_still_shows_holiday_for_a_genuine_full_day_holiday(): void
    {
        $employee = $this->makeUser();
        Holiday::create([
            'name' => 'QA Full Holiday', 'code' => 'QAFH' . random_int(100000, 999999), 'date' => '2026-06-16',
            'status' => 'active', 'holiday_type' => 'full',
        ]);

        $data = $this->callCalendar($employee, ['month' => 6, 'year' => 2026]);

        $this->assertEquals('Holiday', $data['calendar'][16]['status']);
    }

    public function test_list_view_shows_absent_not_holiday_for_a_dummy_absence_row_on_a_part_day_holiday(): void
    {
        $employee = $this->makeUser();
        $this->partDayHoliday('2026-06-15');

        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-15 00:00:00',
            'check_out_time' => null,
            'status' => 'absent',
        ]);

        $attendances = $this->callListView($employee, ['month' => 6, 'year' => 2026]);
        $day = $attendances->first(fn ($a) => $a->created_at->toDateString() === '2026-06-15');

        $this->assertNotNull($day);
        $this->assertEquals('Absent', $day->status);
    }
}
