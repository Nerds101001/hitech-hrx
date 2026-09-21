<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AttendanceDayStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Working a Sunday earns a 1-day ALP comp-off and an SP badge once the punch is
 * long enough. That used to be a strict 4h; it is now 3h55m (235 minutes), so a
 * punch a few minutes short still counts. Only the off-day credit was relaxed:
 * an ordinary weekday's half-day bar stays at 4h.
 */
class SundayCreditThresholdTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private LeaveType $alp;
    private const SUNDAY = '2026-09-06';   // a Sunday

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->alp = LeaveType::where('code', 'ALP')->first()
            ?: LeaveType::create(['name' => 'All Purpose Leave', 'code' => 'ALP', 'is_paid' => true, 'is_active' => true]);
        $this->user = User::create([
            'first_name' => 'QA', 'last_name' => 'Sunday', 'email' => 'qasun' . uniqid() . '@example.test',
            'code' => 'QASU' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
    }

    private function balance(): float
    {
        return (float) (LeaveBalance::where('user_id', $this->user->id)->where('leave_type_id', $this->alp->id)->value('balance') ?? 0);
    }

    private function punch(string $date, int $minutes): Attendance
    {
        $in = Carbon::parse($date . ' 09:30:00');
        return Attendance::create(['user_id' => $this->user->id, 'check_in_time' => $in, 'check_out_time' => $in->copy()->addMinutes($minutes), 'status' => 'Present']);
    }

    public static function minutes(): array
    {
        // [minutes worked, credited?]  3:54 / 3:55 / 3:59 / 4:00 / 8:00
        return [[234, false], [235, true], [239, true], [240, true], [480, true]];
    }

    /** @dataProvider minutes */
    public function test_a_worked_sunday_is_credited_from_3h55_and_not_before(int $mins, bool $credited): void
    {
        $before = $this->balance();

        $att = $this->punch(self::SUNDAY, $mins);

        $this->assertEquals($before + ($credited ? 1 : 0), $this->balance(), "{$mins} minutes on a Sunday");
        $this->assertSame($credited, str_contains((string) $att->fresh()->admin_reason, 'Sunday Working Credit'), "tag at {$mins} minutes");
        $code = AttendanceDayStatus::resolve($this->user, Carbon::parse(self::SUNDAY), $att->fresh(), null, null)['code'];
        $this->assertSame($credited ? 'sp' : 'weekly_off', $code, "badge at {$mins} minutes");
    }

    public function test_one_second_short_of_3h55_is_not_enough(): void
    {
        $in = Carbon::parse(self::SUNDAY . ' 09:30:00');
        $att = Attendance::create(['user_id' => $this->user->id, 'check_in_time' => $in, 'check_out_time' => $in->copy()->addMinutes(234)->addSeconds(59), 'status' => 'Present']);

        $this->assertEquals(0, $this->balance());
        $this->assertSame('weekly_off', AttendanceDayStatus::resolve($this->user, Carbon::parse(self::SUNDAY), $att->fresh(), null, null)['code']);
    }

    public function test_it_is_credited_once_and_reversed_if_the_punch_is_corrected_below_3h55(): void
    {
        $att = $this->punch(self::SUNDAY, 237);
        $this->assertEquals(1, $this->balance());

        $att->fresh()->save();   // saving again must not credit twice
        $this->assertEquals(1, $this->balance(), 'no double credit');

        $att = $att->fresh();
        $att->update(['check_out_time' => $att->check_in_time->copy()->addMinutes(200)]);
        $this->assertEquals(0, $this->balance(), 'corrected below 3h55m, the day is reversed');
        $this->assertStringNotContainsString('Sunday Working Credit', (string) $att->fresh()->admin_reason);
    }

    public function test_a_weekday_still_needs_4h_for_a_half_day_the_relaxation_is_off_days_only(): void
    {
        $weekday = '2026-09-09';   // a Wednesday
        $short = $this->punch($weekday, 236);
        $ok = $this->punch('2026-09-10', 240);

        $shortCode = AttendanceDayStatus::resolve($this->user, Carbon::parse($weekday), $short->fresh(), null, null)['code'];
        $okCode = AttendanceDayStatus::resolve($this->user, Carbon::parse('2026-09-10'), $ok->fresh(), null, null)['code'];

        $this->assertNotSame('half-day', $shortCode, '3h56m on a weekday is not a half day');
        $this->assertSame('half-day', $okCode, '4h on a weekday still is');
        $this->assertEquals(0, $this->balance(), 'a weekday earns no comp-off');
    }

    public function test_the_named_value_is_3h55(): void
    {
        $this->assertSame(235, AttendanceDayStatus::OFFDAY_CREDIT_MINUTES);
        $this->assertSame(240, AttendanceDayStatus::CREDIT_MINUTES);
    }
}
