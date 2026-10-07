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

    public function test_reversed_punches_are_put_in_order_before_saving(): void
    {
        $d = self::MONDAY;
        [$in, $out] = AttendanceDayStatus::orderPunches(Carbon::parse("$d 17:33"), Carbon::parse("$d 09:47"));
        $this->assertSame(["$d 09:47", "$d 17:33"], [$in->format('Y-m-d H:i'), $out->format('Y-m-d H:i')]);

        [$in, $out] = AttendanceDayStatus::orderPunches(Carbon::parse("$d 09:30"), Carbon::parse("$d 18:30"));
        $this->assertSame(["$d 09:30", "$d 18:30"], [$in->format('Y-m-d H:i'), $out->format('Y-m-d H:i')], 'punches already in order are left alone');

        [$in, $out] = AttendanceDayStatus::orderPunches(Carbon::parse("$d 09:30"), null);
        $this->assertNull($out);
    }

    public function test_an_overnight_shift_check_out_moves_to_the_next_morning(): void
    {
        $night = (object) ['start_time' => '22:00:00', 'end_time' => '06:00:00'];
        $d = self::MONDAY;

        [$in, $out] = AttendanceDayStatus::orderPunches(Carbon::parse("$d 21:55"), Carbon::parse("$d 06:05"), $night);
        $this->assertSame(["$d 21:55", '2026-06-16 06:05'], [$in->format('Y-m-d H:i'), $out->format('Y-m-d H:i')]);
        $this->assertSame(490, AttendanceDayStatus::workedMinutes($in, $out), '8h10m, not 15h50m');

        // A night-shift person's two morning punches are still just swapped.
        [$in, $out] = AttendanceDayStatus::orderPunches(Carbon::parse("$d 09:33"), Carbon::parse("$d 06:06"), $night);
        $this->assertSame(["$d 06:06", "$d 09:33"], [$in->format('Y-m-d H:i'), $out->format('Y-m-d H:i')]);
    }

    public function test_the_biometric_upload_stores_reversed_punches_in_order(): void
    {
        $u = $this->user();
        $admin = User::role('admin')->where('status', 'active')->firstOrFail();
        $this->actingAs($admin);
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Cache::put('biometric_import_' . $admin->id, [
            ['user_id' => $u->id, 'date' => '15/06/2026', 'check_in' => '17:33', 'check_out' => '09:47'],
        ], 600);

        app(\App\Http\Controllers\tenant\AttendanceImportController::class)->storeBiometricImport(new \Illuminate\Http\Request());

        $a = Attendance::where('user_id', $u->id)->whereDate('check_in_time', self::MONDAY)->firstOrFail();
        $this->assertSame('09:47', $a->check_in_time->format('H:i'));
        $this->assertSame('17:33', $a->check_out_time->format('H:i'));
        $this->assertNotSame(Attendance::STATUS_ABSENT, $a->status);
    }
}
