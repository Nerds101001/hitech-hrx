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
 * Working a Sunday earns an ALP comp-off once the punch is long enough: 1.0
 * day at a full 7h50m (470 minutes), 0.5 day from 4h (240 minutes) up to
 * that, and nothing below 4h. An ordinary weekday's half-day bar stays at
 * 4h (240 minutes) too - only the off-day full-day bar differs from a
 * normal day's.
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
        // [minutes worked, credited amount, expected code]
        return [
            [239, 0.0, 'weekly_off'],    // 3h59m: below even the half bar
            [240, 0.5, 'sp_half'],       // 4h00m: half bar
            [469, 0.5, 'sp_half'],       // 7h49m: just short of the full bar
            [470, 1.0, 'sp'],            // 7h50m: full bar
            [480, 1.0, 'sp'],            // 8h00m
        ];
    }

    /** @dataProvider minutes */
    public function test_a_worked_sunday_is_credited_by_tier(int $mins, float $credited, string $code): void
    {
        $before = $this->balance();

        $att = $this->punch(self::SUNDAY, $mins);

        $this->assertEquals($before + $credited, $this->balance(), "{$mins} minutes on a Sunday");
        if ($credited > 0) {
            $this->assertStringContainsString('Sunday Working Credit', (string) $att->fresh()->admin_reason, "tag at {$mins} minutes");
        }
        $resolved = AttendanceDayStatus::resolve($this->user, Carbon::parse(self::SUNDAY), $att->fresh(), null, null)['code'];
        $this->assertSame($code, $resolved, "badge at {$mins} minutes");
    }

    public function test_one_minute_short_of_the_half_bar_is_not_enough(): void
    {
        $in = Carbon::parse(self::SUNDAY . ' 09:30:00');
        $att = Attendance::create(['user_id' => $this->user->id, 'check_in_time' => $in, 'check_out_time' => $in->copy()->addMinutes(239)->addSeconds(59), 'status' => 'Present']);

        $this->assertEquals(0, $this->balance());
        $this->assertSame('weekly_off', AttendanceDayStatus::resolve($this->user, Carbon::parse(self::SUNDAY), $att->fresh(), null, null)['code']);
    }

    public function test_it_is_credited_once_and_reversed_if_the_punch_is_corrected_below_half_bar(): void
    {
        $att = $this->punch(self::SUNDAY, 300); // 5h -> half credit, 0.5
        $this->assertEquals(0.5, $this->balance());

        $att->fresh()->save();   // saving again must not credit twice
        $this->assertEquals(0.5, $this->balance(), 'no double credit');

        $att = $att->fresh();
        $att->update(['check_out_time' => $att->check_in_time->copy()->addMinutes(200)]);
        $this->assertEquals(0, $this->balance(), 'corrected below the half bar, the day is fully reversed');
        $this->assertStringNotContainsString('Sunday Working Credit', (string) $att->fresh()->admin_reason);
    }

    public function test_a_punch_moving_from_half_to_full_tops_up_the_difference(): void
    {
        $att = $this->punch(self::SUNDAY, 300); // 5h -> half credit, 0.5
        $this->assertEquals(0.5, $this->balance());

        $att->fresh()->update(['check_out_time' => $att->check_in_time->copy()->addMinutes(480)]); // 8h -> full credit, 1.0
        $this->assertEquals(1.0, $this->balance(), 'topped up by exactly the difference, not double-credited');
        $this->assertStringContainsString('(Full)', (string) $att->fresh()->admin_reason);
    }

    public function test_a_punch_moving_from_full_to_half_reverses_only_the_difference(): void
    {
        $att = $this->punch(self::SUNDAY, 480); // 8h -> full credit, 1.0
        $this->assertEquals(1.0, $this->balance());

        $att->fresh()->update(['check_out_time' => $att->check_in_time->copy()->addMinutes(300)]); // 5h -> half credit, 0.5
        $this->assertEquals(0.5, $this->balance(), 'reversed down to exactly the half credit');
        $this->assertStringContainsString('(Half)', (string) $att->fresh()->admin_reason);
    }

    public function test_a_weekday_still_needs_4h_for_a_half_day_same_bar_as_before(): void
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

    public function test_the_named_values_are_7h50_full_and_4h_half(): void
    {
        $this->assertSame(470, AttendanceDayStatus::OFFDAY_CREDIT_MINUTES);
        $this->assertSame(240, AttendanceDayStatus::OFFDAY_HALF_CREDIT_MINUTES);
        $this->assertSame(240, AttendanceDayStatus::CREDIT_MINUTES);
    }
}
