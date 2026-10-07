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
}
