<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use App\Services\Api\Attendance\AttendanceService;
use App\Services\AttendanceDayStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Carbon 3's diffInMinutes() is signed: punches stored reversed (check-out
 * before check-in, as some biometric reports list them) gave a negative
 * duration. Every worked-time calculation goes through
 * AttendanceDayStatus::workedMinutes(), which is unsigned.
 */
class AttendanceUnsignedDurationTest extends TestCase
{
    use DatabaseTransactions;

    private const MONDAY = '2026-06-15';

    private function user(): User
    {
        $shift = Shift::whereTime('start_time', '<', '12:00:00')->whereTime('end_time', '>', '15:00:00')->firstOrFail();

        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Unsigned',
            'email' => 'qaunsigned' . uniqid() . '@example.test',
            'code' => 'QAUS' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'),
            'status' => 'active',
        ]);
        $u->forceFill(['shift_id' => $shift->id])->save();

        return $u->fresh('shift');
    }

    public function test_the_helper_is_unsigned(): void
    {
        $in = Carbon::parse(self::MONDAY . ' 18:30:00');
        $out = Carbon::parse(self::MONDAY . ' 09:30:00');

        $this->assertSame(540, AttendanceDayStatus::workedMinutes($in, $out));
        $this->assertSame(540, AttendanceDayStatus::workedMinutes($out, $in));
    }

    public function test_reversed_punches_are_not_marked_absent_by_the_mobile_attendance_service(): void
    {
        $u = $this->user();

        $forward = app(AttendanceService::class)->calculateDayStatus($u, self::MONDAY, Carbon::parse(self::MONDAY . ' 09:30:00'), Carbon::parse(self::MONDAY . ' 18:30:00'));
        $reversed = app(AttendanceService::class)->calculateDayStatus($u, self::MONDAY, Carbon::parse(self::MONDAY . ' 18:30:00'), Carbon::parse(self::MONDAY . ' 09:30:00'));

        $this->assertNotSame(Attendance::STATUS_ABSENT, $reversed['status'], 'a 9-hour day must not be Absent because the punches are reversed');
        $this->assertSame($forward['status'], $reversed['status'], 'the same day whichever order the punches are stored in');
        $this->assertSame($forward['is_late'], $reversed['is_late']);
    }
}
