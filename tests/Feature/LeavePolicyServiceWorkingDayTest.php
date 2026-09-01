<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\LeavePolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * LeavePolicyService::isWorkingDay()'s holiday cache included every Holiday
 * row for the site regardless of holiday_type, so a part-day concession
 * (e.g. a 90-minute morning grace period) made the WHOLE day register as a
 * non-working day - identical to a real full-day holiday. Every other place
 * that checks "is this day a holiday" (Holiday::appliesTo(), registryAjax's
 * own $holidaysBySite/$partDayBySite split) already excludes part-day
 * records for exactly this reason: a grace period is still a working day.
 * Reported live: anyone at a site with a part-day concession who genuinely
 * didn't show up (no approved leave, no punch) silently stopped counting as
 * Absent for that day and showed as OFF instead.
 */
class LeavePolicyServiceWorkingDayTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUserWithShift(int $siteId, bool $fridayWorks = true): User
    {
        $shift = Shift::create([
            'name' => 'QA Shift ' . uniqid(), 'code' => 'QASH' . random_int(100000, 999999),
            'start_date' => '2026-01-01', 'is_infinite' => true,
            'start_time' => '09:00', 'end_time' => '18:00',
            'sunday' => false, 'monday' => true, 'tuesday' => true, 'wednesday' => true,
            'thursday' => true, 'friday' => $fridayWorks, 'saturday' => true,
        ]);
        return User::create([
            'first_name' => 'QA', 'last_name' => 'WorkingDay',
            'email'      => 'qawd' . uniqid() . '@example.test',
            'code'       => 'QAWD' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
            'site_id'    => $siteId, 'shift_id' => $shift->id,
        ]);
    }

    /** 2026-06-12 is a Friday. */
    public function test_a_part_day_holiday_concession_is_still_a_working_day(): void
    {
        $site = Site::create(['name' => 'QA Working Day Site ' . uniqid()]);
        $employee = $this->makeUserWithShift($site->id);

        Holiday::create([
            'name' => 'QA Grace Period', 'date' => '2026-06-12', 'code' => 'QAGP' . random_int(100000, 999999),
            'holiday_type' => 'hours', 'hours_off' => 1.5, 'site_id' => $site->id, 'status' => 'active',
        ]);

        $this->assertTrue(
            LeavePolicyService::isWorkingDay($employee, '2026-06-12'),
            'a 90-minute part-day concession must not turn the whole day into a non-working day - it only reduces the required presence minutes'
        );
    }

    /** Sanity check the fix didn't break the legitimate full-day case. */
    public function test_a_full_day_holiday_is_still_not_a_working_day(): void
    {
        $site = Site::create(['name' => 'QA Working Day Site ' . uniqid()]);
        $employee = $this->makeUserWithShift($site->id);

        Holiday::create([
            'name' => 'QA Full Holiday', 'date' => '2026-06-19', 'code' => 'QAFH' . random_int(100000, 999999),
            'holiday_type' => 'full', 'site_id' => $site->id, 'status' => 'active',
        ]);

        $this->assertFalse(
            LeavePolicyService::isWorkingDay($employee, '2026-06-19'),
            'a genuine full-day holiday must still correctly suppress the day'
        );
    }
}
