<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\OutdoorDuty;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Approving an outdoor duty looked only for a row starting at exactly 09:00, so
 * a day that already had a biometric punch or a CCare mark got a second row
 * beside it (DIPIKA DAS and VANDANA MISHRA 24 Aug, UDIT TANDON 30 Sep 2026).
 * 10-12 June 2026 are weekdays and not holidays in this database.
 */
class OutdoorDutyNoDuplicateRowTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $emp;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $mk = fn ($n) => User::create(['first_name' => 'QA', 'last_name' => "OD $n " . uniqid(), 'email' => 'qaodd' . uniqid() . '@example.test',
            'code' => 'QD' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active']);
        $this->admin = $mk('admin');
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        $this->emp = $mk('emp');
    }

    private function approveOn(string $date): OutdoorDuty
    {
        $od = OutdoorDuty::create(['user_id' => $this->emp->id, 'date' => $date, 'purpose' => 'Client visit', 'status' => 'pending', 'created_by_id' => $this->emp->id]);
        $this->actingAs($this->admin)->postJson(route('leaveRequests.outdoorDuty.actionAjax'), ['id' => $od->id, 'status' => 'approved'])->assertOk();

        return $od;
    }

    private function rows(string $date)
    {
        return Attendance::where('user_id', $this->emp->id)->whereDate('check_in_time', $date)->get();
    }

    public function test_a_day_with_a_biometric_punch_keeps_one_row_now_tied_to_the_duty(): void
    {
        Attendance::create(['user_id' => $this->emp->id, 'check_in_time' => '2026-06-10 10:01:00', 'check_out_time' => '2026-06-10 18:02:00', 'status' => 'Present']);

        $od = $this->approveOn('2026-06-10');

        $rows = $this->rows('2026-06-10');
        $this->assertCount(1, $rows, 'no second row');
        $this->assertTrue((bool) $rows[0]->is_outdoor_duty);
        $this->assertSame($od->id, $rows[0]->outdoor_duty_id);
        $this->assertSame('10:01', $rows[0]->check_in_time->format('H:i'), 'the real punch is kept');
    }

    public function test_a_day_marked_absent_becomes_present_on_the_duty(): void
    {
        Attendance::create(['user_id' => $this->emp->id, 'check_in_time' => '2026-06-11 09:30:00', 'check_out_time' => '2026-06-11 18:30:00',
            'status' => 'absent', 'admin_reason' => 'Marked by CCare']);

        $this->approveOn('2026-06-11');

        $rows = $this->rows('2026-06-11');
        $this->assertCount(1, $rows);
        $this->assertSame('present', strtolower($rows[0]->status));
        $this->assertTrue((bool) $rows[0]->is_outdoor_duty);
    }

    public function test_a_day_with_no_row_still_gets_one(): void
    {
        $od = $this->approveOn('2026-06-12');

        $rows = $this->rows('2026-06-12');
        $this->assertCount(1, $rows);
        $this->assertSame($od->id, $rows[0]->outdoor_duty_id);
        $this->assertSame('09:00', $rows[0]->check_in_time->format('H:i'));
    }
}
