<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\CcAttendanceController;
use App\Models\Attendance;
use App\Models\CcSalespersonMap;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AttendanceDayStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Part-day leave (short leave / half-day leave / WFH) is the one case where
 * CCare is meant to mark the rest of the day on top of an approved/applied
 * leave. Reported: picking an outcome the matrix didn't explicitly know
 * about (most commonly "Absent" on a short/half-day leave day) stamped an
 * admin_reason and made AttendanceDayStatus::resolve() trust a bare
 * 'absent' code instead of correctly classifying the day from the still-
 * attached leave (e.g. unpaid_leave / half-day-paid) -- the leave's own
 * effect got silently dropped from the day's classification even though
 * leave_request_id stayed attached to the row.
 *
 * DatabaseTransactions, never RefreshDatabase -- the local database holds
 * real imported production data.
 */
class CcAttendancePartDayLeaveTest extends TestCase
{
    use DatabaseTransactions;

    private CcAttendanceController $controller;
    private User $ccare;
    private User $salesperson;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->controller = app(CcAttendanceController::class);

        $this->ccare       = $this->makeUser();
        $this->salesperson = $this->makeUser();
        CcSalespersonMap::forceCreate([
            'cc_user_id'    => $this->ccare->id,
            'sales_user_id' => $this->salesperson->id,
            'role'          => 'ccare',
        ]);

        // Plain weekday, not a Sunday or a real company holiday (this DB
        // holds real imported holiday data) -- a holiday/Sunday on the test
        // date triggers the comp-off credit hook's OWN admin_reason, which
        // would be unrelated noise in these assertions. Still inside the
        // CCare register's D+2 filling window.
        $d = now()->subDay();
        while ($d->isSunday() || \App\Models\Holiday::where('status', 'active')->whereDate('date', $d->toDateString())->exists()) {
            $d->subDay();
        }
        $this->date = $d->format('Y-m-d');
        Auth::login($this->ccare);
    }

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'PartDay',
            'email'      => 'ccapd' . uniqid() . '@example.test',
            'code'       => 'CCA' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
    }

    private function submit(array $payload)
    {
        app('session.store')->flush();
        $r = Request::create('/cc-attendance', 'POST', array_merge(['date' => $this->date], $payload));
        $r->setLaravelSession(app('session.store'));
        app()->instance('request', $r);
        return $this->controller->store($r);
    }

    private function flash(string $key): string
    {
        return (string) (app('session.store')->get($key) ?? '');
    }

    private function savedRow(): ?Attendance
    {
        return Attendance::where('user_id', $this->salesperson->id)
            ->whereDate('check_in_time', $this->date)->first();
    }

    private function shortLeave(string $status = 'approved'): LeaveRequest
    {
        $type = LeaveType::where('is_short_leave', true)->where('code', 'SHL')->firstOrFail();
        return LeaveRequest::withoutEvents(fn () => LeaveRequest::create([
            'user_id'        => $this->salesperson->id,
            'leave_type_id'  => $type->id,
            'from_date'      => $this->date,
            'to_date'        => $this->date,
            'status'         => $status,
            'is_short_leave' => true,
            'start_time'     => $this->date . ' 10:00:00',
            'end_time'       => $this->date . ' 12:00:00',
            'duration_hours' => 2,
            'user_notes'     => 'bank work',
        ]));
    }

    private function halfDayLeave(string $status = 'approved', bool $paid = true, string $session = 'first_half'): LeaveRequest
    {
        $type = LeaveType::where('is_paid', $paid)->where('is_short_leave', false)
            ->where('code', '!=', 'WFH')->firstOrFail();
        return LeaveRequest::withoutEvents(fn () => LeaveRequest::create([
            'user_id'          => $this->salesperson->id,
            'leave_type_id'    => $type->id,
            'from_date'        => $this->date,
            'to_date'          => $this->date,
            'status'           => $status,
            'is_half_day'      => true,
            'half_day_session' => $session,
            'is_lwp'           => !$paid,
            'lwp_days'         => $paid ? 0 : 0.5,
            'user_notes'       => 'personal work',
        ]));
    }

    // ══ short leave ═══════════════════════════════════════════════════════

    public function test_short_leave_plus_absent_is_saved_not_dropped_silently(): void
    {
        $leave = $this->shortLeave('approved');

        $resp = $this->submit([
            'attendance' => [$this->salesperson->id => 'Absent'],
            'notes'      => [$this->salesperson->id => 'never came back after the short leave'],
        ]);

        $row = $this->savedRow();
        $this->assertNotNull($row, 'the row should be saved, not silently dropped');
        $this->assertSame('absent', $row->status);
        $this->assertNull($row->admin_reason, 'no admin_reason -- the matrix must classify this from the leave link, not trust a raw override');
        $this->assertSame($leave->id, $row->leave_request_id, 'the leave link must stay attached');
        $this->assertStringContainsString('1 salesperson marked', $this->flash('success'));
    }

    public function test_short_leave_plus_half_day_is_saved_with_partial_hours(): void
    {
        $this->shortLeave('approved');

        $this->submit([
            'attendance' => [$this->salesperson->id => 'Half day'],
            'notes'      => [$this->salesperson->id => 'worked part of the remaining day'],
        ]);

        $row = $this->savedRow();
        $this->assertNotNull($row);
        $this->assertSame('half-day', $row->status);
        $this->assertNull($row->admin_reason);
        $workMins = AttendanceDayStatus::workedMinutes($row->check_in_time, $row->check_out_time);
        $this->assertGreaterThanOrEqual(AttendanceDayStatus::CREDIT_MINUTES, $workMins);
        $this->assertLessThan(AttendanceDayStatus::SHORT_LEAVE_PRESENT_MINUTES, $workMins);
    }

    // ══ half-day leave ════════════════════════════════════════════════════

    public function test_half_day_leave_plus_absent_is_saved_not_dropped_silently(): void
    {
        $leave = $this->halfDayLeave('approved', paid: true);

        $this->submit([
            'attendance' => [$this->salesperson->id => 'Absent'],
            'notes'      => [$this->salesperson->id => 'did not come for the other half'],
        ]);

        $row = $this->savedRow();
        $this->assertNotNull($row, 'the row should be saved, not silently dropped');
        $this->assertSame('absent', $row->status);
        $this->assertNull($row->admin_reason, 'no admin_reason -- the matrix must classify this from the leave link');
        $this->assertSame($leave->id, $row->leave_request_id);
    }

    // ══ "Sunday working" / "Holiday working" make no sense on a leave day ═

    public function test_sunday_working_is_rejected_on_a_short_leave_day(): void
    {
        $this->shortLeave('approved');

        $this->submit([
            'attendance' => [$this->salesperson->id => 'Sunday working'],
            'notes'      => [$this->salesperson->id => 'should not save'],
        ]);

        $this->assertNull($this->savedRow(), 'nothing should be written for an invalid combo');
        $this->assertStringContainsString('already has a leave claim', $this->flash('error'));
    }

    public function test_holiday_working_is_rejected_on_a_half_day_leave_day(): void
    {
        $this->halfDayLeave('approved');

        $this->submit([
            'attendance' => [$this->salesperson->id => 'Holiday working'],
            'notes'      => [$this->salesperson->id => 'should not save'],
        ]);

        $this->assertNull($this->savedRow());
        $this->assertStringContainsString('already has a leave claim', $this->flash('error'));
    }

    // ══ the already-working combos stay unaffected ═══════════════════════

    public function test_short_leave_plus_present_still_works(): void
    {
        $this->shortLeave('approved');

        $this->submit([
            'attendance' => [$this->salesperson->id => 'Present'],
            'notes'      => [$this->salesperson->id => 'worked the rest of the day'],
        ]);

        $row = $this->savedRow();
        $this->assertNotNull($row);
        $this->assertSame('present', $row->status);
        $this->assertNull($row->admin_reason);
    }

    public function test_half_day_leave_plus_half_day_still_works(): void
    {
        $this->halfDayLeave('approved');

        $this->submit([
            'attendance' => [$this->salesperson->id => 'Half day'],
            'notes'      => [$this->salesperson->id => 'worked the other half'],
        ]);

        $row = $this->savedRow();
        $this->assertNotNull($row);
        $this->assertSame('half-day', $row->status);
        $this->assertNull($row->admin_reason);
    }
}
