<?php

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\UserTrainingProgress;
use App\Support\Nav;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Managers take training too, and results are visible beyond admin/HR:
 *
 *  - opening the Training Portal sent every manager (and accounts) to the
 *    company-wide Activity Dashboard, so they never got their own tests - and
 *    a manager held for training could never finish it;
 *  - results: everyone sees their own, a manager their direct reports', admin/HR
 *    everyone's (a manager used to see the whole company, an employee not even
 *    their own);
 *  - training content (modules, questions and answers) is admin/HR or "Manage
 *    Training Portal" only - a manager who now takes the tests must not be
 *    able to open the answer keys;
 *  - a Training section in the sidebar: My Training, Training Results.
 */
class TrainingResultsAccessTest extends TestCase
{
    use DatabaseTransactions;

    private User $manager;
    private User $report;
    private User $stranger;
    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->manager  = $this->person('manager', 'Manny');
        $this->report   = $this->person('employee', 'Reporty');
        $this->stranger = $this->person('employee', 'Strangey');
        $this->hr       = $this->person('hr', 'Harriet');
        $this->report->forceFill(['reporting_to_id' => $this->manager->id])->save();

        $module = TrainingModule::orderBy('id')->firstOrFail();
        foreach ([$this->report, $this->stranger] as $u) {
            UserTrainingProgress::create(['user_id' => $u->id, 'module_id' => $module->id, 'status' => 'completed', 'assessment_score' => 90, 'completed_at' => now()]);
        }
    }

    private function person(string $role, string $first): User
    {
        $u = User::create([
            'first_name' => $first . uniqid(), 'last_name' => 'QA', 'email' => 'qatrn' . uniqid() . '@example.test',
            'code' => 'QT' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'),
            'status' => UserAccountStatus::ACTIVE->value,
        ]);
        $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $u->fresh();
    }

    private function links(User $u): array
    {
        Nav::flush();
        $out = collect(Nav::previewMenuFor($u))->flatMap(fn ($e) => collect($e['submenu'] ?? [])
            ->where('visible', true)->map(fn ($c) => $e['name'] . ' > ' . $c['name']))->all();
        Nav::flush();

        return $out;
    }

    // ── managers take their own training ─────────────────────────────────

    public function test_a_manager_opens_their_own_portal_not_the_dashboard(): void
    {
        $html = $this->actingAs($this->manager)->get('/training')->assertOk()->getContent();
        $this->assertStringContainsString('My Results', $html);
        $this->assertStringContainsString('Team Results', $html);
    }

    public function test_admin_hr_still_land_on_the_activity_dashboard(): void
    {
        $this->actingAs($this->hr)->get('/training')->assertRedirect(route('training.activity'));
    }

    // ── who sees whose results ───────────────────────────────────────────

    public function test_a_manager_sees_only_their_team_on_the_dashboard(): void
    {
        $html = $this->actingAs($this->manager)->get('/training/activity')->assertOk()->getContent();
        $this->assertStringContainsString($this->report->first_name, $html);
        $this->assertStringNotContainsString($this->stranger->first_name, $html);
        $this->assertStringNotContainsString('Manage Content', $html);
    }

    public function test_hr_sees_everyone_and_can_manage_content(): void
    {
        $html = $this->actingAs($this->hr)->get('/training/activity')->assertOk()->getContent();
        $this->assertStringContainsString($this->report->first_name, $html);
        $this->assertStringContainsString($this->stranger->first_name, $html);
        $this->assertStringContainsString('Manage Content', $html);
    }

    public function test_report_cards_follow_the_same_rule(): void
    {
        $this->actingAs($this->manager)->get('/training/report-card/' . $this->report->id)->assertOk();
        $this->actingAs($this->manager)->get('/training/report-card/' . $this->stranger->id)->assertForbidden();
        $this->actingAs($this->hr)->get('/training/report-card/' . $this->stranger->id)->assertOk();
    }

    public function test_an_employee_sees_their_own_results_only(): void
    {
        $html = $this->actingAs($this->report)->get('/training/report-card/' . $this->report->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('id="approve-btn"', $html, 'no one approves their own training');
        $this->actingAs($this->report)->get('/training/report-card/' . $this->stranger->id)->assertForbidden();
        $this->actingAs($this->report)->get('/training/activity')->assertForbidden();
        $this->actingAs($this->report)->get('/training?user_id=' . $this->stranger->id)->assertForbidden();
    }

    public function test_approving_training_is_admin_hr_or_their_own_manager(): void
    {
        $this->actingAs($this->manager)->postJson('/training/approve/' . $this->stranger->id)->assertForbidden();
        $this->actingAs($this->report)->postJson('/training/approve/' . $this->report->id)->assertForbidden();
        $this->actingAs($this->manager)->postJson('/training/approve/' . $this->report->id)->assertOk();
        $this->assertSame('completed', $this->report->fresh()->training_status);
    }

    // ── content (and its answer keys) is admin/HR only ───────────────────

    public function test_a_manager_cannot_open_or_change_training_content(): void
    {
        $this->actingAs($this->manager)->get('/training/manage')->assertForbidden();
        $this->actingAs($this->manager)->post('/training/manage/phase', ['title' => 'X'])->assertForbidden();
        $this->actingAs($this->hr)->get('/training/manage')->assertOk();
    }

    // ── sidebar ──────────────────────────────────────────────────────────

    public function test_the_training_section_in_the_sidebar(): void
    {
        $emp = $this->links($this->report);
        $this->assertContains('Training > My Training', $emp);
        $this->assertNotContains('Training > Training Results', $emp);

        $mgr = $this->links($this->manager);
        $this->assertContains('Training > My Training', $mgr);
        $this->assertContains('Training > Training Results', $mgr);
        $this->assertNotContains('Recruitment & Onboarding > Manage Training', $mgr);

        $hr = $this->links($this->hr);
        $this->assertNotContains('Training > My Training', $hr);
        $this->assertContains('Training > Training Results', $hr);
    }
}
