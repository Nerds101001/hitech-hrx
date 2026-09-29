<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * REGRESSION (security re-audit, HIGH). The attendance list built its HTML by
 * hand: an employee's name and code went into the markup and into an
 * onclick="..." string escaped only with addslashes, and an adjustment reason
 * went into a single-quoted data attribute the same way. Markup in any of them
 * ran as script for the admin/HR opening Attendance.
 */
class AttendanceListXssTest extends TestCase
{
    use DatabaseTransactions;

    private const PAYLOAD = '<img src=x onerror=alert(1)>';

    public function test_record_text_is_escaped_in_every_cell_of_the_list(): void
    {
        Mail::fake();
        $admin = User::create([
            'first_name' => 'QA', 'last_name' => 'Admin', 'email' => 'qaxa' . uniqid() . '@example.test',
            'code' => 'QX' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        $victim = User::create([
            'first_name' => self::PAYLOAD, 'last_name' => "O'Brien\" onmouseover=\"alert(2)",
            'email' => 'qaxv' . uniqid() . '@example.test', 'code' => "C1'); alert(3);//",
            'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        $att = Attendance::create([
            'user_id' => $victim->id, 'check_in_time' => now()->setTime(9, 30), 'check_out_time' => now()->setTime(18, 30),
            'status' => 'present', 'created_by_id' => $admin->id,
        ]);
        $att->forceFill(['admin_reason' => "fixed' onfocus='alert(4)' x='" . self::PAYLOAD, 'updated_by_id' => $admin->id])->saveQuietly();

        $json = $this->actingAs($admin)->getJson('/attendance/indexAjax?' . http_build_query([
            'userId' => $victim->id, 'date' => now()->toDateString(), 'draw' => 1, 'start' => 0, 'length' => 10,
        ]))->assertOk()->json();

        $row = collect($json['data'])->firstWhere('id', $att->id) ?? $json['data'][0];
        $html = $row['user'] . $row['actions'];

        $this->assertStringNotContainsString('<img src=x', $html, 'no raw markup from the name or reason');
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $row['user'], 'the name is shown, escaped');
        $this->assertStringNotContainsString("onmouseover=\"alert", $html, 'a quote in the name cannot open a new attribute');
        $this->assertStringNotContainsString("onfocus='alert", $html, 'a quote in the reason cannot open a new attribute');
        $this->assertDoesNotMatchRegularExpression("/showEmployeeSummary\\('[^']*'\\); alert/", $html, 'the code cannot close the onclick string');
    }
}
