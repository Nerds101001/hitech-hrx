<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OutdoorDuty;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Leave Approvals' Type filter listed leave types only: there was no way to
 * pick Outdoor Duty, and choosing any type hid outdoor duty altogether.
 */
class LeaveApprovalsTypeFilterTest extends TestCase
{
    use DatabaseTransactions;

    private function person(?string $role = null): User
    {
        Mail::fake();
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Filter ' . uniqid(), 'email' => 'qaflt' . uniqid() . '@example.test',
            'code' => 'QF' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        if ($role) {
            $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        }

        return $u;
    }

    private function list(User $admin, array $filters): array
    {
        return $this->actingAs($admin)->getJson(route('leaveRequests.getListAjax', ['draw' => 1, 'start' => 0, 'length' => 500, 'statusFilter' => 'pending'] + $filters))
            ->assertOk()->json('data');
    }

    public function test_the_filter_offers_outdoor_duty_and_every_leave_type(): void
    {
        $html = $this->actingAs($this->person('admin'))->get(route('leaveRequests.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<option value="outdoor">Outdoor Duty</option>', $html);
        foreach (LeaveType::where('status', 'active')->get() as $t) {
            $this->assertStringContainsString('<option value="' . $t->id . '">' . e($t->name), $html, "active type {$t->name} is offered");
        }
    }

    public function test_outdoor_duty_filter_shows_only_outdoor_duty_and_a_leave_type_shows_only_that_type(): void
    {
        $admin = $this->person('admin');
        $emp = $this->person('employee');
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        $leave = LeaveRequest::create(['user_id' => $emp->id, 'leave_type_id' => $alp->id, 'from_date' => now()->addDays(3)->toDateString(),
            'to_date' => now()->addDays(3)->toDateString(), 'status' => 'pending', 'user_notes' => 'QA']);
        $od = OutdoorDuty::create(['user_id' => $emp->id, 'date' => now()->addDays(4)->toDateString(), 'purpose' => 'QA', 'status' => 'pending', 'created_by_id' => $emp->id]);

        $ids = fn (array $rows) => collect($rows)->where('user_id', $emp->id)->pluck('unified_id')->sort()->values()->all();

        $this->assertSame(['leave_' . $leave->id, 'outdoor_' . $od->id], $ids($this->list($admin, [])), 'Type: All shows both');
        $this->assertSame(['outdoor_' . $od->id], $ids($this->list($admin, ['leaveTypeFilter' => 'outdoor'])), 'Outdoor Duty shows outdoor only');
        $this->assertSame(['leave_' . $leave->id], $ids($this->list($admin, ['leaveTypeFilter' => $alp->id])), 'a leave type shows that type only');
    }

    public function test_a_leave_type_name_is_shown_as_text_not_markup(): void
    {
        // REGRESSION (security re-audit M5): the Type column is sent as raw HTML
        // for its badges, and the type name went into it unescaped.
        $admin = $this->person('admin');
        $emp = $this->person('employee');
        $type = LeaveType::create(['name' => '<img src=x onerror=alert(1)>', 'code' => 'QX' . random_int(1000, 9999), 'status' => 'active', 'is_paid' => false]);
        LeaveRequest::create(['user_id' => $emp->id, 'leave_type_id' => $type->id, 'from_date' => now()->addDays(5)->toDateString(),
            'to_date' => now()->addDays(5)->toDateString(), 'status' => 'pending', 'user_notes' => 'QA']);

        $row = collect($this->list($admin, []))->firstWhere('user_id', $emp->id);
        $this->assertStringNotContainsString('<img src=x', $row['request_type']);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $row['request_type']);
    }
}
