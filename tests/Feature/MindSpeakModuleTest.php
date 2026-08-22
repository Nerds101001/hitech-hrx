<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\MindSpeakController;
use App\Models\MindSpeak;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Mind Speak — employee submissions, the admin review loop, and above all
 * ANONYMITY.
 *
 * The module invites people to file complaints anonymously. If a submitter can
 * be identified from anything the reviewer sees or exports, the feature is not
 * merely buggy — it has misled an employee into exposing themselves. Three
 * such leaks were found in this module (the CSV export and two places in the
 * admin dashboard, one of them directly under a label promising anonymity);
 * the tests below exist so they cannot come back.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds real
 * imported production data.
 */
class MindSpeakModuleTest extends TestCase
{
    use DatabaseTransactions;

    private MindSpeakController $controller;
    private User $employee;
    private User $other;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->controller = app(MindSpeakController::class);
        $this->employee   = $this->makeUser();
        $this->other      = $this->makeUser();
        $this->admin      = $this->makeUser([], 'admin');
        Auth::login($this->employee);

        // Normally shared by ShareErrorsFromSession; the views are rendered
        // here without going through the middleware stack.
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    }

    private function makeUser(array $overrides = [], ?string $role = null): User
    {
        $user = User::create(array_merge([
            'first_name' => 'QA', 'last_name' => 'Speaker',
            'email'      => 'ms' . uniqid() . '@example.test',
            'code'       => 'MS' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ], $overrides));

        if ($role && ($r = Role::where('name', $role)->first())) {
            $user->assignRole($r);
        }

        return $user;
    }

    /** A POST as the submit form sends it. */
    private function postReq(array $payload, array $files = []): Request
    {
        $r = Request::create('/mind-speak', 'POST', $payload, [], $files);
        $r->setLaravelSession(app('session.store'));
        app()->instance('request', $r);
        return $r;
    }

    private function getReq(array $query = []): Request
    {
        $r = Request::create('/mind-speak', 'GET', $query);
        $r->setLaravelSession(app('session.store'));
        app()->instance('request', $r);
        return $r;
    }

    private function entry(array $overrides = []): MindSpeak
    {
        return MindSpeak::create(array_merge([
            'user_id'  => $this->employee->id,
            'category' => 'Idea',
            'content'  => 'QA entry ' . uniqid(),
            'status'   => MindSpeak::STATUS_PENDING,
        ], $overrides));
    }

    private function exportCsv(): string
    {
        $response = $this->controller->export($this->getReq());
        ob_start();
        $response->sendContent();
        return (string) ob_get_clean();
    }

    /** The line of the export that carries this submission. */
    private function csvRowFor(MindSpeak $entry): string
    {
        foreach (explode("\n", $this->exportCsv()) as $line) {
            if (str_starts_with($line, '"' . $entry->id . '",')) {
                return $line;
            }
        }
        return '';
    }

    // ══ anonymity ════════════════════════════════════════════════════════

    /**
     * REGRESSION — the export wrote the submitter's employee code on every row,
     * anonymous or not, while blanking their name, email and department. One
     * column undid the entire feature.
     */
    public function test_export_does_not_carry_the_employee_code_of_an_anonymous_submission(): void
    {
        $entry = $this->entry(['category' => 'Complaint', 'is_anonymous' => true]);
        Auth::login($this->admin);

        $row = $this->csvRowFor($entry);

        $this->assertNotSame('', $row, 'the submission was missing from the export');
        $this->assertStringNotContainsString($this->employee->code, $row);
        $this->assertSame(4, substr_count($row, '[Anonymous]'),
            'code, name, email and department must all be masked');
    }

    public function test_export_still_identifies_a_submitter_who_did_not_ask_for_anonymity(): void
    {
        $entry = $this->entry(['is_anonymous' => false]);
        Auth::login($this->admin);

        $row = $this->csvRowFor($entry);

        $this->assertStringContainsString($this->employee->code, $row);
        $this->assertStringContainsString($this->employee->email, $row);
    }

    /**
     * REGRESSION — the dashboard printed the employee code in the table and
     * again in the detail modal, the second one directly beneath the words
     * "Anonymous (Name & Department hidden)".
     */
    public function test_admin_dashboard_never_renders_the_code_of_an_anonymous_submitter(): void
    {
        $this->entry(['category' => 'Complaint', 'is_anonymous' => true]);
        Auth::login($this->admin);

        $html = $this->controller->index($this->getReq(['is_anonymous' => '1']))->render();

        $this->assertStringNotContainsString($this->employee->code, $html);
        $this->assertStringNotContainsString($this->employee->email, $html);
    }

    /**
     * REGRESSION — filtering the table by department used to return anonymous
     * rows. Six live departments have exactly one active member, so
     * "Department = Legal" + "Anonymous = Yes" named the complainant outright.
     * (The department breakdown card leaked the same way and has since been
     * removed from the dashboard entirely.)
     */
    public function test_filtering_by_department_never_returns_anonymous_submissions(): void
    {
        $department = \App\Models\Department::first();
        $this->assertNotNull($department, 'the fixture needs at least one department');

        $loner = $this->makeUser(['department_id' => $department->id]);
        $anon = MindSpeak::create([
            'user_id' => $loner->id, 'category' => 'Complaint',
            'content' => 'anonymous grievance', 'is_anonymous' => true,
            'status'  => MindSpeak::STATUS_PENDING,
        ]);

        Auth::login($this->admin);
        $ids = $this->controller->index($this->getReq([
            'department_id' => $department->id, 'is_anonymous' => '1',
        ]))->getData()['submissions']->pluck('id');

        $this->assertFalse($ids->contains($anon->id));
    }

    /** A proposal is owned work — it cannot be filed anonymously. */
    public function test_an_approvable_category_is_forced_to_be_identified(): void
    {
        foreach (MindSpeak::APPROVABLE as $category) {
            $files = $category === 'Seminar'
                ? ['attachment' => UploadedFile::fake()->create('deck.pdf', 10, 'application/pdf')]
                : [];

            $this->controller->store($this->postReq([
                'category' => $category, 'content' => 'anonymity attempt', 'is_anonymous' => '1',
            ], $files));

            $this->assertFalse(
                (bool) MindSpeak::where('category', $category)->latest('id')->first()->is_anonymous,
                "$category must always name its author"
            );
        }
    }

    // ══ validation ═══════════════════════════════════════════════════════

    /**
     * REGRESSION — category was free text, so a hand-rolled POST could invent
     * one. "Sales Pitch " with a trailing space slipped past the rule above
     * and past the approve/acknowledge split, and appeared in none of the
     * dashboard counts.
     */
    public function test_a_category_outside_the_published_list_is_refused(): void
    {
        foreach (['Bogus', 'Sales Pitch ', '<script>alert(1)</script>', ''] as $category) {
            try {
                $this->controller->store($this->postReq([
                    'category' => $category, 'content' => 'category probe',
                ]));
                $this->fail("category '$category' was accepted");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('category', $e->errors());
            }
        }
    }

    /**
     * Mind Speak is a plain-text channel. Blade escapes output, so pasted
     * markup was never executable — but a submission reading
     * `<script>alert(1)</script>` has no place on an HR record, and neither
     * does a row of emoji on a professional platform.
     */
    public function test_markup_and_emoji_are_refused_in_a_submission(): void
    {
        $rejected = [
            '<script>alert(1)</script> please fix',
            '<b>shouting</b> at the reader',
            '<img src=x onerror=alert(1)>',
            'Great idea 🙂 lets do it',
            '👍👍👍👍👍',
        ];

        foreach ($rejected as $content) {
            try {
                $this->controller->store($this->postReq(['category' => 'Idea', 'content' => $content]));
                $this->fail('accepted: ' . $content);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('content', $e->errors());
            }
        }
    }

    /** The rule must not swallow ordinary business writing. */
    public function test_ordinary_text_is_not_caught_by_the_plain_text_rule(): void
    {
        $allowed = [
            'Cost is < 5 lakhs per unit, we should proceed',
            'Margin dropped 8% — worth reviewing before Q3',
            'Raised with José in Pre Sales; quote was ₹1,20,000',
            'Use the a < b comparison in the report filter',
        ];

        foreach ($allowed as $content) {
            $this->controller->store($this->postReq(['category' => 'Idea', 'content' => $content]));
            $this->assertNotNull(MindSpeak::where('content', $content)->first(), "rejected: $content");
        }
    }

    /** The same rule applies to what the reviewer writes back. */
    public function test_markup_and_emoji_are_refused_in_reviewer_remarks(): void
    {
        Auth::login($this->admin);
        $entry = $this->entry();

        foreach (['<script>alert(1)</script>', 'Noted 👍'] as $remarks) {
            try {
                $r = Request::create('/mind-speak/' . $entry->id . '/review', 'POST',
                    ['action' => 'acknowledge', 'remarks' => $remarks]);
                $r->setLaravelSession(app('session.store'));
                $this->controller->review($r, $entry->id);
                $this->fail('accepted: ' . $remarks);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('remarks', $e->errors());
            }
        }

        $this->assertSame(MindSpeak::STATUS_PENDING, $entry->fresh()->status);
    }

    public function test_content_must_be_between_five_and_two_thousand_characters(): void
    {
        foreach (['abcd', str_repeat('A', 2001)] as $content) {
            try {
                $this->controller->store($this->postReq(['category' => 'Idea', 'content' => $content]));
                $this->fail('content of length ' . strlen($content) . ' was accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('content', $e->errors());
            }
        }
    }

    public function test_a_seminar_cannot_be_filed_without_its_presentation(): void
    {
        $this->expectException(ValidationException::class);

        $this->controller->store($this->postReq(['category' => 'Seminar', 'content' => 'seminar with no deck']));
    }

    /**
     * Executable and script-bearing uploads must not reach storage.
     *
     * Real UploadedFile objects, not UploadedFile::fake() — the fake reports a
     * MIME type derived from the FILENAME, so a php script called shell.pdf
     * sails through `mimes:` in a test while being rejected in production.
     * That would have been a green test proving nothing.
     */
    public function test_dangerous_uploads_are_rejected(): void
    {
        $cases = [
            'shell.php'   => '<?php system($_GET[1]);',
            'shell.pdf'   => '<?php system($_GET[1]);',   // php body behind a safe name
            'payload.svg' => '<svg onload=alert(1)></svg>',
            'a.pdf.php'   => '<?php echo 1;',
        ];

        foreach ($cases as $name => $body) {
            $path = tempnam(sys_get_temp_dir(), 'ms') . '_' . $name;
            file_put_contents($path, $body);
            $file = new UploadedFile($path, $name, mime_content_type($path), null, true);
            try {
                $this->controller->store($this->postReq([
                    'category' => 'Idea', 'content' => 'upload probe ' . $name,
                ], ['attachment' => $file]));
                $this->fail("$name was accepted");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('attachment', $e->errors());
            }
        }
    }

    // ══ attachments ══════════════════════════════════════════════════════

    /**
     * REGRESSION — attachments were written to the PUBLIC disk, so a complaint
     * recording sat at /storage/mind_speaks/… readable by anyone with the URL,
     * logged in or not.
     */
    public function test_a_new_attachment_is_not_written_to_public_storage(): void
    {
        $this->controller->store($this->postReq([
            'category' => 'Idea', 'content' => 'attachment placement',
        ], ['attachment' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]));

        $path = MindSpeak::latest('id')->first()->attachment;

        $this->assertNotNull($path);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    public function test_an_unrelated_employee_cannot_fetch_someone_elses_attachment(): void
    {
        $entry = $this->entry(['category' => 'Complaint', 'is_anonymous' => true, 'attachment' => 'mind_speaks/x.txt']);
        Storage::disk('local')->put('mind_speaks/x.txt', 'evidence');

        Auth::login($this->other);

        $this->expectException(HttpException::class);
        $this->controller->attachment($entry->id);
    }

    public function test_the_submitter_and_a_reviewer_can_fetch_the_attachment(): void
    {
        $entry = $this->entry(['attachment' => 'mind_speaks/y.txt']);
        Storage::disk('local')->put('mind_speaks/y.txt', 'evidence');

        foreach ([$this->employee, $this->admin] as $viewer) {
            Auth::login($viewer);
            $this->assertSame(200, $this->controller->attachment($entry->id)->getStatusCode());
        }
    }

    // ══ review workflow ══════════════════════════════════════════════════

    private function review(MindSpeak $entry, string $action, string $remarks = ''): void
    {
        $r = Request::create('/mind-speak/' . $entry->id . '/review', 'POST',
            ['action' => $action, 'remarks' => $remarks]);
        $r->setLaravelSession(app('session.store'));
        $this->controller->review($r, $entry->id);
    }

    public function test_an_ordinary_employee_cannot_review_a_submission(): void
    {
        $entry = $this->entry(['category' => 'Sales Pitch']);
        Auth::login($this->other);

        $this->review($entry, 'approve', 'looks good to me');

        $this->assertSame(MindSpeak::STATUS_PENDING, $entry->fresh()->status);
    }

    public function test_a_proposal_is_approved_and_feedback_is_acknowledged(): void
    {
        Auth::login($this->admin);

        $pitch = $this->entry(['category' => 'Sales Pitch']);
        $this->review($pitch, 'approve', 'go ahead');
        $this->assertSame(MindSpeak::STATUS_APPROVED, $pitch->fresh()->status);

        $idea = $this->entry(['category' => 'Idea']);
        $this->review($idea, 'acknowledge', 'noted, thank you');
        $this->assertSame(MindSpeak::STATUS_ACKNOWLEDGED, $idea->fresh()->status);
    }

    public function test_an_action_that_does_not_fit_the_category_is_refused(): void
    {
        Auth::login($this->admin);

        $pitch = $this->entry(['category' => 'Sales Pitch']);
        $this->review($pitch, 'acknowledge', 'wrong verb');
        $this->assertSame(MindSpeak::STATUS_PENDING, $pitch->fresh()->status);

        $complaint = $this->entry(['category' => 'Complaint']);
        $this->review($complaint, 'approve', 'wrong verb');
        $this->assertSame(MindSpeak::STATUS_PENDING, $complaint->fresh()->status);
    }

    /** Turning something down without saying why leaves the employee stuck. */
    public function test_a_rejection_without_a_reason_does_not_take_effect(): void
    {
        Auth::login($this->admin);
        $entry = $this->entry();

        $this->review($entry, 'reject', '   ');

        $this->assertSame(MindSpeak::STATUS_PENDING, $entry->fresh()->status);
        $this->assertNull($entry->fresh()->admin_remarks);
    }

    public function test_a_rejected_entry_cannot_be_quietly_flipped_by_a_second_reviewer(): void
    {
        Auth::login($this->admin);
        $entry = $this->entry(['category' => 'Sales Pitch']);
        $this->review($entry, 'reject', 'needs the numbers');

        $this->review($entry, 'approve', 'changed my mind');

        $this->assertSame(MindSpeak::STATUS_REJECTED, $entry->fresh()->status);
        $this->assertSame('needs the numbers', $entry->fresh()->admin_remarks);
    }

    public function test_a_review_records_who_acted_and_when(): void
    {
        Auth::login($this->admin);
        $entry = $this->entry();

        $this->review($entry, 'acknowledge', 'seen');

        $this->assertSame($this->admin->id, $entry->fresh()->reviewed_by_id);
        $this->assertNotNull($entry->fresh()->reviewed_at);
    }

    // ══ resubmission ═════════════════════════════════════════════════════

    public function test_an_employee_may_resubmit_their_own_rejected_entry(): void
    {
        Auth::login($this->admin);
        $rejected = $this->entry();
        $this->review($rejected, 'reject', 'add detail');

        Auth::login($this->employee);
        $this->controller->store($this->postReq([
            'category' => 'Idea', 'content' => 'now with detail',
            'resubmitted_from_id' => $rejected->id,
        ]));

        $new = MindSpeak::where('content', 'now with detail')->first();
        $this->assertSame($rejected->id, $new->resubmitted_from_id);
        $this->assertSame(MindSpeak::STATUS_PENDING, $new->status);
        $this->assertSame(MindSpeak::STATUS_REJECTED, $rejected->fresh()->status,
            'the original must survive as the audit trail');
    }

    public function test_one_employee_cannot_attach_their_entry_to_another_persons_rejection(): void
    {
        Auth::login($this->admin);
        $rejected = $this->entry();                    // belongs to $this->employee
        $this->review($rejected, 'reject', 'add detail');

        Auth::login($this->other);
        $this->controller->store($this->postReq([
            'category' => 'Idea', 'content' => 'hijack attempt',
            'resubmitted_from_id' => $rejected->id,
        ]));

        $this->assertNull(MindSpeak::where('content', 'hijack attempt')->first()->resubmitted_from_id);
    }

    public function test_an_entry_that_was_not_rejected_cannot_be_used_as_a_resubmission_target(): void
    {
        $pending = $this->entry();

        $this->controller->store($this->postReq([
            'category' => 'Idea', 'content' => 'premature resubmission',
            'resubmitted_from_id' => $pending->id,
        ]));

        $this->assertNull(MindSpeak::where('content', 'premature resubmission')->first()->resubmitted_from_id);
    }

    // ══ export authorisation and CSV safety ══════════════════════════════

    public function test_an_ordinary_employee_cannot_export_the_submissions(): void
    {
        $this->expectException(HttpException::class);

        $this->controller->export($this->getReq());
    }

    /**
     * A cell that opens with = + - or @ is run as a formula by Excel. Submitted
     * text goes straight into the export, so it has to be defused.
     */
    public function test_a_formula_in_submitted_text_is_neutralised_in_the_export(): void
    {
        $entry = $this->entry(['content' => '=HYPERLINK("http://evil.test","Payslip")']);
        Auth::login($this->admin);

        $row = $this->csvRowFor($entry);

        $this->assertStringContainsString('"\'=HYPERLINK', $row);
    }

    public function test_a_quote_in_submitted_text_does_not_break_the_row(): void
    {
        $entry = $this->entry(['content' => 'he said "no", then left']);
        Auth::login($this->admin);

        $row = $this->csvRowFor($entry);

        $this->assertStringContainsString('""no""', $row);
        $this->assertSame(9, count(str_getcsv($row)), 'the row must still hold exactly nine columns');
    }

    // ══ visibility ═══════════════════════════════════════════════════════

    public function test_an_employee_sees_only_their_own_submissions(): void
    {
        $mine   = $this->entry(['content' => 'mine to see']);
        $theirs = $this->entry(['user_id' => $this->other->id, 'content' => 'not mine to see']);

        $ids = $this->controller->index($this->getReq())->getData()['submissions']->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    /**
     * REGRESSION — admin and HR were routed to the dashboard, which has no
     * submit form, so they could not post an entry of their own at all.
     */
    public function test_an_admin_can_reach_the_submission_form(): void
    {
        Auth::login($this->admin);

        $this->assertSame('tenant.mind-speak.admin-dashboard', $this->controller->index($this->getReq())->name());
        $this->assertSame('tenant.mind-speak.employee-index',
            $this->controller->index($this->getReq(['compose' => '1']))->name());
    }

    public function test_an_admin_submission_is_stored_like_any_other(): void
    {
        Auth::login($this->admin);

        $this->controller->store($this->postReq(['category' => 'Idea', 'content' => 'an idea from the admin']));

        $entry = MindSpeak::where('content', 'an idea from the admin')->first();
        $this->assertNotNull($entry);
        $this->assertSame($this->admin->id, $entry->user_id);
    }
}
