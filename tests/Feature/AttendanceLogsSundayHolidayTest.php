<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeavePolicyProfile;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AttendanceController::indexAjax() - the Attendance Logs listing (not the
 * Monthly Registry, which already gets this right). A day worked on a
 * Sunday or a holiday had no distinct status at all here - it fell through
 * to plain "Present" (teal), identical to an ordinary weekday, even though
 * the same day on the Monthly Registry already renders green as SP/HP to
 * mark it as above-and-beyond. Reported live.
 */
class AttendanceLogsSundayHolidayTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(?string $role = null, ?int $siteId = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Logs',
            'email'      => 'qalogs' . uniqid() . '@example.test',
            'code'       => 'QALG' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
            'site_id'    => $siteId,
        ]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u;
    }

    private function indexAjaxResponse(int $userId, string $date): array
    {
        $admin = $this->makeUser('admin');
        Auth::login($admin);
        $response = app(\App\Http\Controllers\tenant\AttendanceController::class)
            ->indexAjax(Request::create('/attendance/indexAjax', 'GET', [
                'draw' => 1, 'start' => 0, 'length' => 10,
                'userId' => $userId, 'date' => $date,
            ]));
        return json_decode($response->getContent(), true);
    }

    private function statusHtmlFor(int $userId, string $date): string
    {
        $data = $this->indexAjaxResponse($userId, $date);
        return $data['data'][0]['status'] ?? '';
    }

    /** 2026-06-14 is a Sunday (checked against the same holiday-free window used elsewhere in this suite). */
    public function test_a_worked_sunday_shows_sp_in_green_not_plain_present(): void
    {
        $employee = $this->makeUser();
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-14 09:00:00',
            'check_out_time' => '2026-06-14 18:00:00',
            'status' => 'Present',
        ]);

        $html = $this->statusHtmlFor($employee->id, '2026-06-14');

        $this->assertStringContainsString('bg-success', $html, 'a worked Sunday must render with the same green used for SP on the Monthly Registry');
        $this->assertStringContainsString('SP', $html);
        $this->assertStringNotContainsString('bg-teal', $html, 'must not fall back to the plain weekday Present color');
    }

    public function test_a_worked_holiday_shows_hp_in_green_not_plain_present(): void
    {
        $site = Site::first() ?? Site::create(['name' => 'QA Test Site']);
        $employee = $this->makeUser(null, $site->id);

        // A Wednesday - the only reason this day is special is the Holiday row below.
        Holiday::create([
            'name' => 'QA Test Holiday', 'date' => '2026-06-17', 'code' => 'QATH' . random_int(100000, 999999),
            'holiday_type' => 'full', 'site_id' => $site->id, 'status' => 'active',
        ]);
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-17 09:00:00',
            'check_out_time' => '2026-06-17 18:00:00',
            'status' => 'Present',
        ]);

        $html = $this->statusHtmlFor($employee->id, '2026-06-17');

        $this->assertStringContainsString('bg-success', $html, 'a worked holiday must render with the same green used for HP on the Monthly Registry');
        $this->assertStringContainsString('HP', $html);
        $this->assertStringNotContainsString('bg-teal', $html, 'must not fall back to the plain weekday Present color');
    }

    /**
     * A designated off-Saturday (per the employee's own saturday_off_config)
     * already earns the identical comp-off credit as a worked Sunday - see
     * Attendance.php's own crediting hook - but had no matching status here,
     * so it rendered as an ordinary Present with no visible sign it was a
     * credited bonus day. Reported live: an employee on an "even Saturdays
     * off" policy worked her 2nd Saturday, got the comp-off credit in her
     * balance, but the Attendance Logs page showed nothing to indicate it.
     *
     * 2026-06-13 is the 2nd Saturday of June 2026.
     */
    public function test_a_worked_designated_off_saturday_shows_sp_in_green_not_plain_present(): void
    {
        $profile = LeavePolicyProfile::create([
            'name' => 'QA Even Saturday Off', 'saturday_off_config' => ['2', '4'], 'status' => 'active',
        ]);
        $employee = $this->makeUser();
        $employee->update(['leave_policy_profile_id' => $profile->id]);

        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-13 09:00:00',
            'check_out_time' => '2026-06-13 18:00:00',
            'status' => 'Present',
        ]);

        $html = $this->statusHtmlFor($employee->id, '2026-06-13');

        $this->assertStringContainsString('bg-success', $html, 'a worked designated off-Saturday must render with the same green used for a worked Sunday');
        $this->assertStringContainsString('SP', $html);
        $this->assertStringNotContainsString('bg-teal', $html, 'must not fall back to the plain weekday Present color');
    }

    /** A Saturday that is NOT one of the employee's designated off-Saturdays is an ordinary working day - must not get the SP treatment. 2026-06-06 is the 1st Saturday of June 2026. */
    public function test_a_worked_ordinary_saturday_stays_plain_present(): void
    {
        $profile = LeavePolicyProfile::create([
            'name' => 'QA Even Saturday Off 2', 'saturday_off_config' => ['2', '4'], 'status' => 'active',
        ]);
        $employee = $this->makeUser();
        $employee->update(['leave_policy_profile_id' => $profile->id]);

        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-06 09:00:00',
            'check_out_time' => '2026-06-06 18:00:00',
            'status' => 'Present',
        ]);

        $html = $this->statusHtmlFor($employee->id, '2026-06-06');

        $this->assertStringNotContainsString('bg-success', $html, 'the 1st Saturday is not one of this profile\'s off-Saturdays - it is an ordinary working day');
    }

    /**
     * The header "24 Present / 1 Absent / ... " summary cards used to be
     * tallied by a separate raw-SQL query with a flat 465-minute cutoff and
     * no idea a part-day holiday concession lowers that bar - so a day
     * genuinely worked in full (once the concession is accounted for, as
     * the row beneath it already correctly shows "Present") fell into the
     * SQL's crude "half day" bucket and counted toward the Late card
     * instead of Present, disagreeing with the very row it was summarising.
     * Both now go through the same resolver. A Sunday/holiday-credited row
     * would not expose this: Attendance's own crediting hook stamps
     * admin_reason on those, which the old SQL's admin_reason IS NOT NULL
     * clause happened to also count as present by coincidence - this uses
     * an ordinary working day with a concession instead, where no crediting
     * hook is involved and admin_reason stays empty.
     */
    public function test_stats_header_counts_a_part_day_holiday_present_not_late(): void
    {
        $employee = $this->makeUser();
        Holiday::create([
            'name' => 'QA Concession', 'code' => 'QACS' . random_int(100000, 999999), 'date' => '2026-06-17',
            'holiday_type' => 'hours', 'hours_off' => 1.5, 'status' => 'active',
        ]);
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-17 09:00:00',
            'check_out_time' => '2026-06-17 15:30:00', // 390 min: clears the 375-min concession bar, under the old flat 465
            'status' => 'Present',
        ]);

        $data = $this->indexAjaxResponse($employee->id, '2026-06-17');

        $this->assertStringContainsString('Present', $data['data'][0]['status']);
        $this->assertEquals(1, $data['stats']['present'], 'the header card must agree with the Present badge shown in the row');
        $this->assertEquals(0, $data['stats']['late']);
    }
}
