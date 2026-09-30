<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\CcAttendanceController;
use App\Models\Attendance;
use App\Models\CcSalespersonMap;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Saving the CCare salesperson attendance register.
 *
 * Written after a live report: a CCare user marked a salesperson five times and
 * nothing saved, with no message of any kind. Two defects were behind it —
 * validation errors were never rendered on the page, and the controller
 * reported "Attendance saved" even when it had written nothing at all. These
 * tests exist so a submission can never again be silently lost or falsely
 * confirmed.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds real
 * imported production data.
 */
class CcAttendanceSaveTest extends TestCase
{
    use DatabaseTransactions;

    private CcAttendanceController $controller;
    private User $ccare;
    private User $salesperson;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->controller = app(CcAttendanceController::class);

        $this->ccare       = $this->makeUser();
        $this->salesperson = $this->makeUser();
        CcSalespersonMap::forceCreate([
            'cc_user_id'    => $this->ccare->id,
            'sales_user_id' => $this->salesperson->id,
            'role'          => 'ccare',
        ]);

        // Yesterday: inside the D+2 filling window, so never locked.
        $this->date = now()->subDay()->format('Y-m-d');
        Auth::login($this->ccare);
    }

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'Attend',
            'email'      => 'cca' . uniqid() . '@example.test',
            'code'       => 'CCA' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
    }

    private function submit(array $payload, array $files = [])
    {
        app('session.store')->flush();
        $r = Request::create('/cc-attendance', 'POST', array_merge(['date' => $this->date], $payload), [], $files);
        $r->setLaravelSession(app('session.store'));
        app()->instance('request', $r);
        return $this->controller->store($r);
    }

    private function flash(string $key): string
    {
        return (string) (app('session.store')->get($key) ?? '');
    }

    private function savedRow(): ?Attendance
    {
        return Attendance::where('user_id', $this->salesperson->id)
            ->whereDate('check_in_time', $this->date)->first();
    }

    // ══ the happy path still works ═══════════════════════════════════════

    public function test_a_status_with_a_reporting_note_is_saved(): void
    {
        $this->submit([
            'attendance' => [$this->salesperson->id => 'Present'],
            'notes'      => [$this->salesperson->id => 'visited Acme and Bolt'],
        ]);

        $row = $this->savedRow();
        $this->assertNotNull($row, 'the attendance row should exist');
        $this->assertSame('present', $row->status);
        $this->assertSame('visited Acme and Bolt', $row->notes);
        $this->assertStringContainsString('1 salesperson marked', $this->flash('success'));
    }

    // ══ the leave module owns a day once leave is applied for ════════════

    /** Build a leave request covering the register date, without firing the approval hook. */
    private function leaveFor(string $status): \App\Models\LeaveRequest
    {
        return \App\Models\LeaveRequest::withoutEvents(fn () => \App\Models\LeaveRequest::create([
            'user_id'       => $this->salesperson->id,
            'leave_type_id' => \App\Models\LeaveType::where('code', 'ALP')->firstOrFail()->id,
            'from_date'     => $this->date,
            'to_date'       => $this->date,
            'status'        => $status,
            'user_notes'    => 'family function',
        ]));
    }

    /**
     * A salesperson who has APPLIED for leave is out of CCare's hands, even
     * before anyone approves it. Previously the guard only looked at the
     * attendance row, so a pending leave left the day wide open to be marked
     * Absent -- and that absence then survived the approval.
     */
    public function test_a_salesperson_with_a_pending_leave_cannot_be_marked(): void
    {
        $this->leaveFor('pending');

        $this->submit([
            'attendance' => [$this->salesperson->id => 'Absent'],
            'notes'      => [$this->salesperson->id => 'not reachable'],
        ]);

        $this->assertNull($this->savedRow(), 'no attendance row may be written while leave is pending');
        $this->assertStringContainsString('applied for leave', $this->flash('error'));
    }

    /** And once it is approved, the same. */
    public function test_a_salesperson_with_an_approved_leave_cannot_be_marked(): void
    {
        $this->leaveFor('approved');

        $this->submit([
            'attendance' => [$this->salesperson->id => 'Absent'],
            'notes'      => [$this->salesperson->id => 'not reachable'],
        ]);

        $this->assertNull($this->savedRow(), 'no attendance row may be written over an approved leave');
        $this->assertStringContainsString('approved leave', $this->flash('error'));
    }

    /** Marking Present is refused too -- the block is about the day, not the verdict. */
    public function test_present_is_refused_on_a_leave_day_as_well(): void
    {
        $this->leaveFor('approved');

        $this->submit([
            'attendance' => [$this->salesperson->id => 'Present'],
            'notes'      => [$this->salesperson->id => 'saw him at the depot'],
        ]);

        $this->assertNull($this->savedRow());
    }

    /**
     * Clearing must still work: a day marked in error, before the leave was
     * applied for, has to remain removable or the wrong mark is permanent.
     */
    public function test_a_wrongly_marked_day_can_still_be_cleared_after_leave_is_applied(): void
    {
        $this->submit([
            'attendance' => [$this->salesperson->id => 'Absent'],
            'notes'      => [$this->salesperson->id => 'marked before the leave came in'],
        ]);
        $this->assertNotNull($this->savedRow(), 'precondition: the day was marked first');

        $this->leaveFor('approved');

        $this->submit(['attendance' => [$this->salesperson->id => '__clear']]);

        $this->assertNull($this->savedRow(), 'clearing a wrongly-marked day must still be allowed');
    }

    /** Someone with no leave at all is unaffected. */
    public function test_a_salesperson_without_leave_is_still_markable(): void
    {
        $this->submit([
            'attendance' => [$this->salesperson->id => 'Present'],
            'notes'      => [$this->salesperson->id => 'visited Acme'],
        ]);

        $this->assertNotNull($this->savedRow());
        $this->assertSame('present', $this->savedRow()->status);
    }
    // ══ the two defects behind the live report ═══════════════════════════

    /**
     * REGRESSION — this used to return "Attendance saved for the salespeople
     * you marked." while writing nothing, so a lost submission looked like a
     * completed one.
     */
    public function test_a_submission_that_writes_nothing_reports_an_error_not_success(): void
    {
        $this->submit([
            'attendance' => [$this->salesperson->id => ''],      // nothing ticked
            'notes'      => [$this->salesperson->id => 'visited Acme'],
        ]);

        $this->assertNull($this->savedRow(), 'nothing should have been written');
        $this->assertSame('', $this->flash('success'), 'it must NOT claim success');
        $this->assertStringContainsString('Nothing was saved', $this->flash('error'));
    }

    /**
     * REGRESSION — a payload for someone outside the caller's register is
     * filtered out, and the response then claimed success anyway.
     */
    public function test_marking_someone_outside_your_register_does_not_report_success(): void
    {
        $stranger = $this->makeUser();

        $this->submit([
            'attendance' => [$stranger->id => 'Present'],
            'notes'      => [$stranger->id => 'not mine to mark'],
        ]);

        $this->assertNull(
            Attendance::where('user_id', $stranger->id)->whereDate('check_in_time', $this->date)->first(),
            'a salesperson outside the register must never be written'
        );
        $this->assertSame('', $this->flash('success'));
        $this->assertStringContainsString('Nothing was saved', $this->flash('error'));
    }

    /** With no status field at all the server must still refuse, loudly. */
    public function test_a_payload_with_no_attendance_field_is_rejected(): void
    {
        try {
            $this->submit(['notes' => [$this->salesperson->id => 'visited Acme']]);
            $this->fail('an empty submission was accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attendance', $e->errors());
        }

        $this->assertNull($this->savedRow());
    }

    /** The reporting note stays mandatory, and refuses the whole submission. */
    public function test_a_status_without_a_reporting_note_saves_nothing(): void
    {
        $this->submit([
            'attendance' => [$this->salesperson->id => 'Present'],
            'notes'      => [$this->salesperson->id => '   '],
        ]);

        $this->assertNull($this->savedRow());
        $this->assertStringContainsString('Reporting/remarks is mandatory', $this->flash('error'));
    }

    // ══ work from home ═══════════════════════════════════════════════════

    public function test_work_from_home_without_an_approval_snapshot_saves_nothing(): void
    {
        $this->submit([
            'attendance' => [$this->salesperson->id => 'Work from home'],
            'notes'      => [$this->salesperson->id => 'approved by manager'],
        ]);

        $this->assertNull($this->savedRow());
        $this->assertStringContainsString('approval snapshot', $this->flash('error'));
    }

    public function test_work_from_home_with_an_approval_snapshot_is_saved(): void
    {
        $this->submit(
            [
                'attendance' => [$this->salesperson->id => 'Work from home'],
                'notes'      => [$this->salesperson->id => 'approved by manager'],
            ],
            ['wfh_proof' => [$this->salesperson->id => UploadedFile::fake()->image('approval.png')]]
        );

        $row = $this->savedRow();
        $this->assertNotNull($row);
        $this->assertSame('work_from_home', $row->status);
        $this->assertNotNull($row->attachment, 'the approval snapshot must be stored against the row');
    }

    /** A second WFH edit may reuse the snapshot already on file. */
    public function test_work_from_home_reuses_an_existing_snapshot_on_a_later_edit(): void
    {
        $this->submit(
            ['attendance' => [$this->salesperson->id => 'Work from home'],
             'notes'      => [$this->salesperson->id => 'first submission']],
            ['wfh_proof' => [$this->salesperson->id => UploadedFile::fake()->image('approval.png')]]
        );
        $this->assertNotNull($this->savedRow());

        // no file this time
        $this->submit([
            'attendance' => [$this->salesperson->id => 'Work from home'],
            'notes'      => [$this->salesperson->id => 'corrected reporting'],
        ]);

        $this->assertSame('corrected reporting', $this->savedRow()->notes);
        $this->assertStringContainsString('1 salesperson marked', $this->flash('success'));
    }

    // ══ correcting and clearing ══════════════════════════════════════════

    public function test_an_existing_mark_can_be_corrected_while_the_date_is_open(): void
    {
        $this->submit([
            'attendance' => [$this->salesperson->id => 'Present'],
            'notes'      => [$this->salesperson->id => 'first take'],
        ]);
        $this->submit([
            'attendance' => [$this->salesperson->id => 'Half day'],
            'notes'      => [$this->salesperson->id => 'corrected'],
        ]);

        $row = $this->savedRow();
        $this->assertSame('half-day', $row->status);
        $this->assertSame('corrected', $row->notes);
    }

    public function test_clearing_removes_the_row_and_says_so(): void
    {
        $this->submit([
            'attendance' => [$this->salesperson->id => 'Present'],
            'notes'      => [$this->salesperson->id => 'to be cleared'],
        ]);
        $this->assertNotNull($this->savedRow());

        $this->submit(['attendance' => [$this->salesperson->id => '__clear'], 'notes' => []]);

        $this->assertNull($this->savedRow(), 'the row should be soft-deleted');
        $this->assertStringContainsString('cleared', $this->flash('success'));
    }

    public function test_a_future_date_is_refused(): void
    {
        app('session.store')->flush();
        $r = Request::create('/cc-attendance', 'POST', [
            'date'       => now()->addDays(2)->format('Y-m-d'),
            'attendance' => [$this->salesperson->id => 'Present'],
            'notes'      => [$this->salesperson->id => 'future'],
        ]);
        $r->setLaravelSession(app('session.store'));
        app()->instance('request', $r);
        $this->controller->store($r);

        $this->assertStringContainsString('future date', $this->flash('error'));
    }

    // == WFH approval snapshots are private ==============================

    /**
     * REGRESSION — snapshots were written to the PUBLIC disk and served at
     * /storage/wfh_approvals/<name>, readable by anyone with the URL. On
     * production 10 real approval screenshots were fetchable with no login.
     */
    public function test_a_new_snapshot_is_not_written_to_public_storage(): void
    {
        $this->submit(
            ['attendance' => [$this->salesperson->id => 'Work from home'],
             'notes'      => [$this->salesperson->id => 'approved by manager']],
            ['wfh_proof' => [$this->salesperson->id => UploadedFile::fake()->image('approval.png')]]
        );

        $path = $this->savedRow()->attachment;
        $this->assertNotNull($path);
        $this->assertTrue(Storage::disk('local')->exists($path), 'it should be on the private disk');
        $this->assertFalse(Storage::disk('public')->exists($path), 'it must NOT be publicly served');
    }

    public function test_the_ccare_owner_and_the_salesperson_can_view_the_snapshot(): void
    {
        $this->submit(
            ['attendance' => [$this->salesperson->id => 'Work from home'],
             'notes'      => [$this->salesperson->id => 'approved by manager']],
            ['wfh_proof' => [$this->salesperson->id => UploadedFile::fake()->image('approval.png')]]
        );
        $id = $this->savedRow()->id;

        foreach ([$this->ccare, $this->salesperson] as $viewer) {
            Auth::login($viewer);
            $this->assertSame(200, $this->controller->proof($id)->getStatusCode());
        }
    }

    public function test_an_unrelated_employee_cannot_view_someone_elses_snapshot(): void
    {
        $this->submit(
            ['attendance' => [$this->salesperson->id => 'Work from home'],
             'notes'      => [$this->salesperson->id => 'approved by manager']],
            ['wfh_proof' => [$this->salesperson->id => UploadedFile::fake()->image('approval.png')]]
        );
        $id = $this->savedRow()->id;

        Auth::login($this->makeUser());          // not the owner, not their CCare

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->controller->proof($id);
    }

    /** Snapshots uploaded before the fix still resolve from the public disk. */
    public function test_a_legacy_snapshot_on_the_public_disk_is_still_served(): void
    {
        $this->submit([
            'attendance' => [$this->salesperson->id => 'Present'],
            'notes'      => [$this->salesperson->id => 'legacy row'],
        ]);
        $row = $this->savedRow();
        $row->forceFill(['attachment' => 'wfh_approvals/legacy.png'])->save();
        Storage::disk('public')->put('wfh_approvals/legacy.png', 'old snapshot');

        Auth::login($this->ccare);

        $this->assertSame(200, $this->controller->proof($row->id)->getStatusCode());
    }

    public function test_a_row_with_no_snapshot_returns_not_found(): void
    {
        $this->submit([
            'attendance' => [$this->salesperson->id => 'Present'],
            'notes'      => [$this->salesperson->id => 'no snapshot here'],
        ]);

        Auth::login($this->ccare);

        try {
            $this->controller->proof($this->savedRow()->id);
            $this->fail('a row with no snapshot should 404');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }
}
