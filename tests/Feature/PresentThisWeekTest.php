<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The employee dashboard's "This week's presence" stat used to count any
 * distinct date with a non-null check_in_time - including a dummy 00:00
 * leave-linked row with no real punch at all. Now resolved through the same
 * AttendanceDayStatus resolver every attendance surface shares.
 */
class PresentThisWeekTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'Week',
            'email'      => 'qaweek' . uniqid() . '@example.test',
            'code'       => 'QAWK' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
    }

    public function test_a_dummy_leave_linked_row_with_no_real_punch_does_not_count_as_present(): void
    {
        Carbon::setTestNow('2026-06-16 10:00:00'); // Tuesday, same week as the dummy row below

        $employee = $this->makeUser();
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-15 00:00:00', // Monday, dummy stamp
            'check_out_time' => null,
            'status' => 'Present',
        ]);

        Auth::login($employee);
        $view = app(\App\Http\Controllers\tenant\users\UserDashboardController::class)->index();
        $presentThisWeek = $view->getData()['presentThisWeek'];

        $this->assertEquals(0, $presentThisWeek, 'a dummy 00:00 row with no real punch must not count as a present day');
    }

    public function test_a_genuine_worked_day_this_week_counts_as_present(): void
    {
        Carbon::setTestNow('2026-06-16 10:00:00');

        $employee = $this->makeUser();
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-15 09:00:00',
            'check_out_time' => '2026-06-15 18:00:00',
            'status' => 'Present',
        ]);

        Auth::login($employee);
        $view = app(\App\Http\Controllers\tenant\users\UserDashboardController::class)->index();
        $presentThisWeek = $view->getData()['presentThisWeek'];

        $this->assertEquals(1, $presentThisWeek);
    }
}
