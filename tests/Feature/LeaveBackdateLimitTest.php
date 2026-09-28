<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\LeaveController as ApiLeaveController;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\LeaveUnlock;
use App\Models\User;
use App\Support\LeaveBackdate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Leave can be applied at most one week back (was 30 days on the web form and
 * unlimited in the mobile app). One rule, LeaveBackdate, for the form, its
 * date picker and the app.
 */
class LeaveBackdateLimitTest extends TestCase
{
    use DatabaseTransactions;

    private User $emp;
    private LeaveType $type;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-28 11:00:00'));
        $d = Department::create(['name' => 'ZZ Leave ' . uniqid(), 'code' => 'ZL' . random_int(10000, 99999)]);
        $this->emp = User::create([
            'first_name' => 'QA', 'last_name' => 'Leave ' . uniqid(), 'email' => 'qaleave' . uniqid() . '@example.test',
            'code' => 'QL' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active', 'department_id' => $d->id,
        ]);
        $this->emp->assignRole(Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']));
        $this->type = LeaveType::where('code', 'CL')->first() ?? LeaveType::first();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_limit_is_one_week(): void
    {
        $this->assertSame(7, LeaveBackdate::MAX_DAYS);
        $this->assertSame('2026-09-21', LeaveBackdate::earliest());
        $this->assertNull(LeaveBackdate::error('2026-09-21'), 'exactly 7 days back is allowed');
        $this->assertNull(LeaveBackdate::error('2026-09-28'));
        $this->assertNull(LeaveBackdate::error('2026-10-05'));
        $this->assertNotNull(LeaveBackdate::error('2026-09-20'), '8 days back is refused');
        $this->assertNotNull(LeaveBackdate::error('2026-08-30'), 'what the old 30-day rule allowed is refused');
    }

    public function test_web_form_refuses_more_than_a_week_back(): void
    {
        $res = $this->actingAs($this->emp)->from('/user/leaves')->post('/user/leaves/store', [
            'leave_type_id' => $this->type->id, 'from_date' => '2026-09-20', 'to_date' => '2026-09-20', 'user_notes' => 'test',
        ]);
        $res->assertSessionHasErrors(['from_date' => LeaveBackdate::error('2026-09-20')]);
    }

    public function test_web_form_lets_a_week_back_through_the_date_check(): void
    {
        $res = $this->actingAs($this->emp)->from('/user/leaves')->post('/user/leaves/store', [
            'leave_type_id' => $this->type->id, 'from_date' => '2026-09-21', 'to_date' => '2026-09-21', 'user_notes' => 'test',
        ]);
        $errors = session('errors');
        $this->assertFalse($errors && str_contains((string) $errors->first('from_date'), 'in the past'), 'a week back must pass the back-dating check');
    }

    public function test_mobile_app_refuses_more_than_a_week_back(): void
    {
        $this->actingAs($this->emp);
        $res = app(ApiLeaveController::class)->createLeaveRequest(Request::create('/api', 'POST', [
            'fromDate' => '2026-09-20', 'toDate' => '2026-09-20', 'leaveType' => $this->type->id, 'comments' => 'test',
        ]));
        $this->assertStringContainsString('1 week', $res->getContent());
        $this->assertSame(0, \App\Models\LeaveRequest::where('user_id', $this->emp->id)->count());
    }

    public function test_the_date_picker_starts_a_week_back(): void
    {
        $html = $this->actingAs($this->emp)->get('/user/leaves')->getContent();
        $this->assertStringContainsString('const minDate = "2026-09-21"', $html);
    }

    // ── Leave Unlock Windows (HR) ────────────────────────────────────────

    private function window(string $from, string $to, ?array $only = null, bool $active = true): LeaveUnlock
    {
        return LeaveUnlock::create(['from_date' => $from, 'to_date' => $to, 'scope' => $only ? 'selected' : 'all',
            'user_ids' => $only, 'is_active' => $active, 'reason' => 'test']);
    }

    private function person(string $role): User
    {
        $u = User::create(['first_name' => 'QA', 'last_name' => ucfirst($role) . ' ' . uniqid(), 'email' => 'qa' . $role . uniqid() . '@example.test',
            'code' => 'QU' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active']);
        $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $u->fresh();
    }

    public function test_an_open_window_allows_its_dates(): void
    {
        $this->window('2026-09-01', '2026-09-15');
        $this->assertNull(LeaveBackdate::error('2026-09-10', '2026-09-12', $this->emp->id));
        $this->assertNotNull(LeaveBackdate::error('2026-09-17', '2026-09-17', $this->emp->id), 'a date outside the window stays closed');
    }

    public function test_every_old_day_of_the_leave_must_be_covered(): void
    {
        $this->window('2026-09-01', '2026-09-15');
        // 14th-15th are covered, 16th-20th are not, 21st onwards is within the week
        $this->assertNotNull(LeaveBackdate::error('2026-09-14', '2026-09-22', $this->emp->id));
        $this->window('2026-09-16', '2026-09-20');
        $this->assertNull(LeaveBackdate::error('2026-09-14', '2026-09-22', $this->emp->id), 'two windows together cover it');
    }

    public function test_a_window_for_selected_people_only_opens_for_them(): void
    {
        $other = $this->person('employee');
        $this->window('2026-09-01', '2026-09-15', [$other->id]);
        $this->assertNull(LeaveBackdate::error('2026-09-10', null, $other->id));
        $this->assertNotNull(LeaveBackdate::error('2026-09-10', null, $this->emp->id));
    }

    public function test_a_disabled_window_opens_nothing(): void
    {
        $this->window('2026-09-01', '2026-09-15', null, false);
        $this->assertNotNull(LeaveBackdate::error('2026-09-10', null, $this->emp->id));
    }

    public function test_web_form_and_app_honour_the_window(): void
    {
        $this->window('2026-09-01', '2026-09-15');
        $this->actingAs($this->emp)->from('/user/leaves')->post('/user/leaves/store', [
            'leave_type_id' => $this->type->id, 'from_date' => '2026-09-10', 'to_date' => '2026-09-10', 'user_notes' => 'test',
        ]);
        $errors = session('errors');
        $this->assertFalse($errors && str_contains((string) $errors->first('from_date'), 'in the past'));

        $this->actingAs($this->emp);
        $res = app(ApiLeaveController::class)->createLeaveRequest(Request::create('/api', 'POST', [
            'fromDate' => '2026-09-10', 'toDate' => '2026-09-10', 'leaveType' => $this->type->id, 'comments' => 'test',
        ]));
        $this->assertStringNotContainsString('1 week', $res->getContent());
    }

    public function test_the_date_picker_opens_back_to_the_window(): void
    {
        $this->window('2026-09-01', '2026-09-15');
        $this->assertSame('2026-09-01', LeaveBackdate::earliestFor($this->emp->id));
        $this->assertStringContainsString('const minDate = "2026-09-01"', $this->actingAs($this->emp)->get('/user/leaves')->getContent());
    }

    public function test_hr_opens_switches_off_and_removes_a_window(): void
    {
        $hr = $this->person('hr');
        $this->actingAs($hr)->get('/leave-unlocks')->assertOk()->assertSee('Leave Unlock Windows');
        $this->actingAs($hr)->post('/leave-unlocks', ['from_date' => '2026-09-01', 'to_date' => '2026-09-10', 'scope' => 'selected',
            'user_ids' => [$this->emp->id], 'reason' => 'hospital'])->assertRedirect();
        $w = LeaveUnlock::latest('id')->firstOrFail();
        $this->assertSame([$this->emp->id], $w->user_ids);
        $this->assertNull(LeaveBackdate::error('2026-09-05', null, $this->emp->id));

        $this->actingAs($hr)->post("/leave-unlocks/{$w->id}/toggle");
        $this->assertFalse($w->fresh()->is_active);
        $this->assertNotNull(LeaveBackdate::error('2026-09-05', null, $this->emp->id));

        $this->actingAs($hr)->delete("/leave-unlocks/{$w->id}");
        $this->assertNull(LeaveUnlock::find($w->id));
    }

    public function test_a_window_is_capped_at_thirty_days(): void
    {
        $hr = $this->person('hr');
        $this->actingAs($hr)->from('/leave-unlocks')->post('/leave-unlocks', ['from_date' => '2026-07-01', 'to_date' => '2026-09-10', 'scope' => 'all']);
        $this->assertSame(0, LeaveUnlock::count());
    }

    public function test_only_admin_and_hr_manage_windows(): void
    {
        foreach (['employee', 'manager', 'accounts'] as $role) {
            $u = $this->person($role);
            $this->assertNotSame(200, $this->actingAs($u)->get('/leave-unlocks')->getStatusCode(), $role);
            $this->actingAs($u)->post('/leave-unlocks', ['from_date' => '2026-09-01', 'to_date' => '2026-09-10', 'scope' => 'all']);
        }
        $this->assertSame(0, LeaveUnlock::count());
        $this->assertSame(200, $this->actingAs($this->person('admin'))->get('/leave-unlocks')->getStatusCode());
    }
}
