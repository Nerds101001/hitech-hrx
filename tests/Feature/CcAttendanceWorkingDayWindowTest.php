<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\CcAttendanceController;
use App\Models\Attendance;
use App\Models\CcSalespersonMap;
use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Sales Field Ops attendance: a date's filling window counts WORKING days.
 *
 * It was D+2 calendar days at 12:00 PM, so a weekend or a holiday used it up
 * before the team was back: Friday's sheet locked on Sunday, Saturday's at
 * Monday noon. Now a date stays open until 12:00 PM on the 2nd working day
 * after it - Sundays and holidays (and the person's off-Saturdays) don't count.
 *
 * Fixed dates in March 2031, a month with no holidays in the data:
 * Mon 10, Tue 11, Wed 12, Thu 13, Fri 14, Sat 15, Sun 16, Mon 17, Tue 18.
 */
class CcAttendanceWorkingDayWindowTest extends TestCase
{
    use DatabaseTransactions;

    private CcAttendanceController $controller;
    private User $ccare;
    private User $salesperson;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->controller = app(CcAttendanceController::class);
        $this->ccare = $this->makeUser();
        $this->salesperson = $this->makeUser();
        CcSalespersonMap::forceCreate(['cc_user_id' => $this->ccare->id, 'sales_user_id' => $this->salesperson->id, 'role' => 'ccare']);
        Auth::login($this->ccare);
        $this->assertTrue(Carbon::parse('2031-03-10')->isMonday(), 'the fixed dates assume Monday 10 March 2031');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'Window', 'email' => 'ccw' . uniqid() . '@example.test',
            'code' => 'CCW' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
    }

    private function deadline(string $date): string
    {
        return $this->controller->lockDeadline($date, $this->ccare)->format('Y-m-d H:i');
    }

    public function test_saturday_stays_open_over_sunday_until_tuesday_noon(): void
    {
        $this->assertSame('2031-03-18 12:00', $this->deadline('2031-03-15'));
    }

    public function test_friday_is_not_cut_short_by_sunday(): void
    {
        // Saturday is a working day for someone with no off-Saturday rule.
        $this->assertSame('2031-03-17 12:00', $this->deadline('2031-03-14'));
    }

    public function test_an_ordinary_weekday_keeps_its_two_days(): void
    {
        $this->assertSame('2031-03-13 12:00', $this->deadline('2031-03-11'));
    }

    public function test_a_holiday_does_not_count(): void
    {
        Holiday::create(['name' => 'QA holiday', 'date' => '2031-03-12', 'code' => 'QAH' . random_int(1000, 9999), 'status' => \App\Enums\Status::ACTIVE, 'holiday_type' => 'full']);

        // Tuesday: Wednesday is the holiday, so Thursday and Friday are its two days.
        $this->assertSame('2031-03-14 12:00', $this->deadline('2031-03-11'));
    }

    public function test_monthly_sheet_keeps_saturday_editable_on_monday(): void
    {
        // A department name the monthly page lets in, without touching real rows.
        $dept = \App\Models\Department::create(['name' => 'Customer Care QA ' . uniqid(), 'code' => 'CCQA' . random_int(1000, 9999)]);
        $this->ccare->forceFill(['department_id' => $dept->id])->save();

        Carbon::setTestNow('2031-03-17 15:00:00');      // Monday afternoon
        $view = $this->controller->monthly(Request::create('/cc-attendance/monthly', 'GET', ['month' => 3, 'year' => 2031]));
        $open = array_keys($view->getData()['openDates']);

        // Fri 14 (till Mon noon) has closed; Sat 15 (till Tue noon) and today are open.
        $this->assertSame(['2031-03-15', '2031-03-16', '2031-03-17'], $open);
    }

    public function test_saturday_can_be_marked_on_monday_and_locks_after_tuesday_noon(): void
    {
        $submit = function () {
            app('session.store')->flush();
            $r = Request::create('/cc-attendance', 'POST', ['date' => '2031-03-15', 'attendance' => [$this->salesperson->id => 'Present'], 'notes' => [$this->salesperson->id => 'visited two customers']]);
            $r->setLaravelSession(app('session.store'));
            app()->instance('request', $r);
            $this->controller->store($r);
        };

        Carbon::setTestNow('2031-03-17 15:00:00');      // Monday afternoon, back after Sunday
        $submit();
        $this->assertNotNull(Attendance::where('user_id', $this->salesperson->id)->whereDate('check_in_time', '2031-03-15')->first(), 'still open on Monday');

        Carbon::setTestNow('2031-03-18 12:01:00');      // Tuesday, just past noon
        $submit();
        $this->assertStringContainsString('locked', (string) app('session.store')->get('error'));
        $this->assertStringContainsString('18 Mar 2031, 12:00 PM', (string) app('session.store')->get('error'));
    }

    /**
     * The monthly sheet builds its own leave tags independently of
     * AttendanceDayStatus (a naive from_date..to_date fill with no holiday
     * check), so the resolver fix doesn't reach it - found 2026-10-24 via
     * Sachin Sharma EMP-004's Oct 2-5 leave, where Oct 2 (a full holiday)
     * still showed the ALP tag on this specific page. A full holiday with
     * nothing marked now reads "Holiday" here too, and the leave tag doesn't
     * render on it - the day on either side of the holiday still does.
     */
    public function test_monthly_sheet_shows_holiday_not_the_leave_tag_on_an_unworked_holiday(): void
    {
        $dept = \App\Models\Department::create(['name' => 'Customer Care QA ' . uniqid(), 'code' => 'CCQA' . random_int(1000, 9999)]);
        $this->ccare->forceFill(['department_id' => $dept->id])->save();

        $holidayName = 'QA Monthly Holiday ' . uniqid();
        Holiday::create(['name' => $holidayName, 'date' => '2031-03-12', 'code' => 'QAH' . random_int(1000, 9999), 'status' => \App\Enums\Status::ACTIVE, 'holiday_type' => 'full']);

        $alp = \App\Models\LeaveType::where('code', 'ALP')->firstOrFail();
        $leave = \App\Models\LeaveRequest::create([
            'user_id' => $this->salesperson->id, 'leave_type_id' => $alp->id,
            'from_date' => '2031-03-11', 'to_date' => '2031-03-13',
            'status' => 'pending', 'user_notes' => 'monthly sheet holiday-vs-leave test',
        ]);
        $leave->updateQuietly(['status' => 'approved']);

        Carbon::setTestNow('2031-03-13 09:00:00');       // the 12th (holiday) is still within its marking window
        $view = $this->controller->monthly(Request::create('/cc-attendance/monthly', 'GET', ['month' => 3, 'year' => 2031]));
        \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag());
        $html = view('tenant.cc_attendance.monthly', $view->getData())->render();

        $this->assertStringContainsString($holidayName, $html, 'the holiday cell shows its name, not a leave tag');
        $this->assertSame(2, substr_count($html, '>ALP<'), 'only the two real leave days (11th and 13th) get the ALP tag, not the 12th');

        // The select for the still-open holiday cell (12th) offers only HP, not
        // the full P/A/HD/WFH/PS set an ordinary open day's select offers.
        $marker = 'name="attendance[' . $this->salesperson->id . '][2031-03-12]"';
        $selectStart = strpos($html, $marker);
        $this->assertNotFalse($selectStart, 'the holiday cell has its select rendered');
        $selectBlock = substr($html, $selectStart, strpos($html, '</select>', $selectStart) - $selectStart);
        $this->assertStringContainsString('cc-opt-hp', $selectBlock, 'HP is offered on the holiday');
        $this->assertStringNotContainsString('cc-opt-p"', $selectBlock, 'Present is not offered on the holiday');
        $this->assertStringNotContainsString('cc-opt-a"', $selectBlock, 'Absent is not offered on the holiday');
        $this->assertStringNotContainsString('cc-opt-hd', $selectBlock, 'Half day is not offered on the holiday');
        $this->assertStringNotContainsString('cc-opt-wfh', $selectBlock, 'WFH is not offered on the holiday');
        $this->assertStringNotContainsString('cc-opt-ps', $selectBlock, 'Sunday working is not offered on the holiday');
    }

    /**
     * Server-side half of the same rule, in case of a stale page or a crafted
     * submission: only "Holiday working" (or clearing) is accepted on a full
     * holiday - Present, Absent, Half day, WFH, Sunday working are all
     * rejected, same spirit as the existing leave-claim rejection above.
     */
    public function test_store_rejects_every_status_but_holiday_working_on_a_holiday(): void
    {
        Holiday::create(['name' => 'QA Store Holiday', 'date' => '2031-03-12', 'code' => 'QAH' . random_int(1000, 9999), 'status' => \App\Enums\Status::ACTIVE, 'holiday_type' => 'full']);

        $submit = function (string $status) {
            app('session.store')->flush();
            $r = Request::create('/cc-attendance', 'POST', ['date' => '2031-03-12', 'attendance' => [$this->salesperson->id => $status], 'notes' => [$this->salesperson->id => 'reporting note']]);
            $r->setLaravelSession(app('session.store'));
            app()->instance('request', $r);
            $this->controller->store($r);
        };

        Carbon::setTestNow('2031-03-13 09:00:00');

        $submit('Present');
        $this->assertNull(Attendance::where('user_id', $this->salesperson->id)->whereDate('check_in_time', '2031-03-12')->first(), 'Present is rejected on a holiday');
        $this->assertStringContainsString('only "Holiday working" can be marked', (string) app('session.store')->get('error'));

        $submit('Holiday working');
        $att = Attendance::where('user_id', $this->salesperson->id)->whereDate('check_in_time', '2031-03-12')->first();
        $this->assertNotNull($att, 'Holiday working is accepted');
    }
}
