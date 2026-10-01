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
 * Managers take training too; results stay HR's and admin's:
 *
 *  - opening the Training Portal sent every manager (and accounts) to the
 *    company-wide Activity Dashboard, so they never got their own tests - and
 *    a manager held for training could never finish it;
 *  - results: everyone sees their own; only admin/HR (or "Manage Training
 *    Portal") see anyone else's - not a manager's team (a manager used to see
 *    the whole company, an employee not even their own);
 *  - training content (modules, questions and answers) is admin/HR or "Manage
 *    Training Portal" only - a manager who now takes the tests must not be
 *    able to open the answer keys;
 *  - a Training section in the sidebar: My Training for everyone, Training
 *    Results for admin/HR.
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
        $out = collect(Nav::previewMenuFor($u))->where('visible', true)->flatMap(fn ($e) => empty($e['submenu'])
            ? [$e['name']]
            : collect($e['submenu'])->where('visible', true)->map(fn ($c) => $e['name'] . ' > ' . $c['name']))->all();
        Nav::flush();

        return $out;
    }

    // ── managers take their own training ─────────────────────────────────

    public function test_a_manager_opens_their_own_portal_not_the_dashboard(): void
    {
        $html = $this->actingAs($this->manager)->get('/training')->assertOk()->getContent();
        $this->assertStringContainsString('My Results', $html);
        $this->assertStringNotContainsString('Team Results', $html);
        $this->assertStringNotContainsString(route('training.activity'), $html, 'no link to the results dashboard');
    }

    public function test_admin_hr_still_land_on_the_activity_dashboard(): void
    {
        $this->actingAs($this->hr)->get('/training')->assertRedirect(route('training.activity'));
    }

    // ── who sees whose results ───────────────────────────────────────────

    public function test_a_manager_does_not_get_the_results_dashboard_not_even_for_their_team(): void
    {
        $this->actingAs($this->manager)->get('/training/activity')->assertForbidden();
    }

    public function test_hr_sees_everyone_and_can_manage_content(): void
    {
        $html = $this->actingAs($this->hr)->get('/training/activity')->assertOk()->getContent();
        $this->assertStringContainsString($this->report->first_name, $html);
        $this->assertStringContainsString($this->stranger->first_name, $html);
        $this->assertStringContainsString('Manage Programs', $html);
    }

    public function test_report_cards_follow_the_same_rule(): void
    {
        $this->actingAs($this->manager)->get('/training/report-card/' . $this->manager->id)->assertOk();
        $this->actingAs($this->manager)->get('/training/report-card/' . $this->report->id)->assertForbidden();
        $this->actingAs($this->manager)->get('/training?user_id=' . $this->report->id)->assertForbidden();
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

    public function test_approving_training_is_admin_hr_only(): void
    {
        $this->actingAs($this->manager)->postJson('/training/approve/' . $this->report->id)->assertForbidden();
        $this->actingAs($this->report)->postJson('/training/approve/' . $this->report->id)->assertForbidden();
        $this->actingAs($this->hr)->postJson('/training/approve/' . $this->report->id)->assertOk();
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

    public function test_one_my_training_link_and_no_duplicate_for_admin_hr(): void
    {
        foreach ([$this->report, $this->manager] as $u) {
            $links = $this->links($u);
            $this->assertContains('My Training', $links);
            $this->assertEmpty(preg_grep('/^Training( >|$)/', $links), 'no separate Training section');
        }
        $this->assertNotContains('Recruitment & Onboarding > Manage Training', $this->links($this->manager));

        // Admin/HR reach training through Recruitment & Onboarding, as before - no second menu.
        $hr = $this->links($this->hr);
        $this->assertNotContains('My Training', $hr);
        $this->assertEmpty(preg_grep('/^Training( >|$)/', $hr));
        $this->assertContains('Recruitment & Onboarding > Training Portal', $hr);
        $this->assertContains('Recruitment & Onboarding > Manage Training', $hr);
    }

    // ── the results page: filters, sorting, export ───────────────────────

    public function test_results_page_filters_by_department_status_and_search(): void
    {
        $dept = \App\Models\Department::create(['name' => 'ZZ Filter Dept ' . uniqid(), 'code' => 'ZF' . random_int(10000, 99999)]);
        $this->report->forceFill(['department_id' => $dept->id])->save();

        $html = $this->actingAs($this->hr)->get('/training/activity?department=' . $dept->id)->assertOk()->getContent();
        $this->assertStringContainsString($this->report->first_name, $html);
        $this->assertStringNotContainsString($this->stranger->first_name, $html);
        $this->assertStringContainsString('&middot; filtered', $html);
        $this->assertMatchesRegularExpression('/Showing <strong>1<\/strong> of/', $html);

        $html = $this->actingAs($this->hr)->get('/training/activity?q=' . urlencode($this->stranger->first_name))->getContent();
        $this->assertStringContainsString($this->stranger->first_name, $html);
        $this->assertStringNotContainsString($this->report->first_name, $html);

        // Both have one compulsory module done, so neither is "not started".
        $html = $this->actingAs($this->hr)->get('/training/activity?status=not_started&q=' . urlencode($this->report->first_name))->getContent();
        $this->assertStringContainsString('No one matches these filters', $html);
    }

    public function test_results_page_sorts_by_any_column(): void
    {
        $pos = fn (string $html, User $u) => strpos($html, $u->first_name, strpos($html, 'id="empTable"'));   // in the table, not the activity feed above it
        $asc  = $this->actingAs($this->hr)->get('/training/activity?sort=name&dir=asc')->assertOk()->getContent();
        $desc = $this->actingAs($this->hr)->get('/training/activity?sort=name&dir=desc')->assertOk()->getContent();
        // "Reporty..." sorts before "Strangey..." ascending, after it descending.
        $this->assertLessThan($pos($asc, $this->stranger), $pos($asc, $this->report));
        $this->assertGreaterThan($pos($desc, $this->stranger), $pos($desc, $this->report));
        foreach (['department', 'completed', 'pct', 'avg_score', 'failed', 'last_activity', 'state'] as $col) {
            $this->actingAs($this->hr)->get('/training/activity?sort=' . $col . '&dir=asc')->assertOk();
        }
    }

    public function test_results_page_has_export_and_results_buttons(): void
    {
        $html = $this->actingAs($this->hr)->get('/training/activity')->getContent();
        $this->assertStringContainsString('id="taExport"', $html);
        $this->assertStringContainsString(route('training.report-card', $this->report->id), $html);
        $this->assertStringContainsString('data-row=', $html);
    }
}
