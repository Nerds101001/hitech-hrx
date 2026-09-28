<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\LeaveController as ApiLeaveController;
use App\Models\Department;
use App\Models\LeaveType;
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
}
