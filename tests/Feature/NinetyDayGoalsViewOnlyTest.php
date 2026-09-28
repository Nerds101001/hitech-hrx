<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NinetyDayGoal;
use App\Models\NinetyDayProgram;
use App\Models\NinetyDayRound;
use App\Models\User;
use App\Services\NinetyDayGoalService as Goals;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "View 90-Day Goals (read-only)" -- goals.view.
 *
 * Until it existed the only grant was goals.manage, which made the holder the
 * equal of admin: create programs, send reminders, edit or delete anyone's
 * goals, remove comments. goals.view sees everything that does and changes
 * nothing. The module now asks two different questions -- seesEveryone() for
 * what a page shows, managesEveryone() for what it lets you do -- and these
 * tests pin which is which, for both permissions.
 */
class NinetyDayGoalsViewOnlyTest extends TestCase
{
    use DatabaseTransactions;

    private User $rep;
    private User $viewer;
    private User $manager;
    private NinetyDayProgram $program;
    private NinetyDayRound $round;
    private NinetyDayGoal $goal;
    private const MONDAY = '2026-10-05';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Carbon::setTestNow(Carbon::parse(self::MONDAY . ' 10:00:00')->addWeek());

        $sales = Department::create(['name' => 'ZZ Sales ' . uniqid(), 'code' => 'ZZS' . random_int(1000, 9999)]);
        $other = Department::create(['name' => 'ZZ Customer Excellence ' . uniqid(), 'code' => 'ZZC' . random_int(1000, 9999)]);
        $this->rep = $this->person($sales, 'employee');
        $this->viewer = $this->person($other, 'employee', 'goals.view');
        $this->manager = $this->person($other, 'employee', 'goals.manage');
        $admin = $this->person($other, 'admin');

        Goals::createProgram($admin, 'ZZ View-only test', Carbon::parse(self::MONDAY), Carbon::parse(self::MONDAY)->addDays(90), [$this->rep->id]);
        $this->program = NinetyDayProgram::latest('id')->firstOrFail();
        $this->round = NinetyDayRound::where('user_id', $this->rep->id)->latest('id')->firstOrFail();
        $this->goal = NinetyDayGoal::create(['user_id' => $this->rep->id, 'round_id' => $this->round->id, 'client_name' => 'ZZ Acme',
            'product' => 'P', 'brand' => 'KIF', 'goal' => 'Order', 'goal_type' => 'Order', 'monthly_potential' => 2.5, 'is_active' => true]);
        Goals::ensureCheckins($this->round, Carbon::now());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(Department $dept, string $role, ?string $permission = null): User
    {
        $u = User::create(['first_name' => 'QA', 'last_name' => 'View ' . uniqid(), 'email' => 'qaview' . uniqid() . '@example.test',
            'code' => 'QAV' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
            'department_id' => $dept->id]);
        $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        if ($permission) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    // ── the two questions ────────────────────────────────────────────────

    public function test_view_only_sees_everyone_but_manages_no_one(): void
    {
        $this->assertTrue(Goals::seesEveryone($this->viewer));
        $this->assertFalse(Goals::managesEveryone($this->viewer));
        $this->assertTrue(Goals::seesEveryone($this->manager));
        $this->assertTrue(Goals::managesEveryone($this->manager));
    }

    // ── what view-only may open ──────────────────────────────────────────

    public function test_view_only_can_open_every_page_that_only_shows(): void
    {
        $this->actingAs($this->viewer);
        foreach (['/performance/ninety-day-goals/program-dashboard', '/performance/ninety-day-goals/past',
                  '/performance/ninety-day-goals/program/' . $this->program->id, '/performance/ninety-day-goals/round/' . $this->round->id,
                  '/performance/ninety-day-goals/admin-view/export'] as $url) {
            $this->assertSame(200, $this->get($url)->getStatusCode(), $url);
        }
    }

    public function test_view_only_screens_draw_no_controls_that_change_anything(): void
    {
        $this->actingAs($this->viewer);
        $this->assertStringNotContainsString('Create New Program', $this->get('/performance/ninety-day-goals/program-dashboard')->getContent());
        $this->assertStringNotContainsString('id="ndgPrRemindBtn"', $this->get('/performance/ninety-day-goals/program/' . $this->program->id)->getContent());
    }

    // ── what view-only is refused ────────────────────────────────────────

    public function test_view_only_cannot_create_a_program(): void
    {
        $this->actingAs($this->viewer);
        $this->get('/performance/ninety-day-goals/programs/create')->assertRedirect(route('tenant.dashboard'));
        $this->assertSame(403, $this->post('/performance/ninety-day-goals/programs', ['name' => 'X'])->getStatusCode());
    }

    public function test_view_only_cannot_send_a_reminder(): void
    {
        $this->actingAs($this->viewer);
        $this->assertSame(403, $this->postJson('/performance/ninety-day-goals/program/' . $this->program->id . '/remind', ['week' => 1])->getStatusCode());
    }

    public function test_view_only_cannot_edit_or_delete_someone_elses_goal(): void
    {
        $this->actingAs($this->viewer);
        $this->assertSame(403, $this->putJson('/performance/ninety-day-goals/' . $this->goal->id, ['product' => 'Hacked'])->getStatusCode());
        $this->assertSame(403, $this->deleteJson('/performance/ninety-day-goals/' . $this->goal->id)->getStatusCode());
        $this->assertSame('P', $this->goal->fresh()->product);
        $this->assertNotNull(NinetyDayGoal::find($this->goal->id));
    }

    public function test_view_only_cannot_comment_on_or_remove_comments_from_someone_elses_goal(): void
    {
        $note = Goals::addNote($this->manager, $this->goal, 1, 'written by a goals.manage holder');
        $this->assertNotNull($note, 'setup: a manager can comment');

        $this->actingAs($this->viewer);
        $this->assertSame(403, $this->postJson('/performance/ninety-day-goals/notes', ['goal_id' => $this->goal->id, 'week_no' => 1, 'body' => 'x'])->getStatusCode());
        $this->assertSame(403, $this->deleteJson('/performance/ninety-day-goals/notes/' . $note->id)->getStatusCode());
    }

    public function test_view_only_cannot_fill_in_a_status_for_someone_else(): void
    {
        [$saved] = Goals::submit($this->rep, 1, [$this->goal->id => ['status' => 'On Track', 'comment' => 'x']], null, $this->viewer);
        $this->assertSame(0, $saved);
    }

    // ── and goals.manage still does all of it ────────────────────────────

    public function test_goals_manage_keeps_every_power_it_had(): void
    {
        $this->actingAs($this->manager);
        $this->assertSame(200, $this->get('/performance/ninety-day-goals/programs/create')->getStatusCode());
        $this->assertStringContainsString('Create New Program', $this->get('/performance/ninety-day-goals/program-dashboard')->getContent());
        $this->assertStringContainsString('id="ndgPrRemindBtn"', $this->get('/performance/ninety-day-goals/program/' . $this->program->id)->getContent());
        $this->assertNotNull(Goals::addNote($this->manager, $this->goal, 1, 'manager comment'));
    }
}
