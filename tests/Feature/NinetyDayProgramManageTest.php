<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NinetyDayGoal;
use App\Models\NinetyDayGoalCheckin;
use App\Models\NinetyDayProgram;
use App\Models\NinetyDayRound;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NinetyDayGoal\ProgramInvite;
use App\Services\NinetyDayGoalService as Goals;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Program Details > Manage program: an admin-created program could not be changed after
 * creation - no rename, no new dates, nobody added or removed, no way to reopen the
 * 48-hour window to add customers - and could not be deleted at all.
 */
class NinetyDayProgramManageTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $hr;
    private User $rep;
    private User $rep2;
    private NinetyDayProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $dept = Department::create(['name' => 'ZZ Sales ' . uniqid(), 'code' => 'ZZN' . random_int(1000, 9999)]);
        $mk = function (string $n, ?string $role = null, ?int $deptId = null) {
            $u = User::create(['first_name' => 'QA', 'last_name' => "$n " . uniqid(), 'email' => 'qand' . uniqid() . '@example.test',
                'code' => 'QN' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active', 'department_id' => $deptId]);
            if ($role) { $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])); }
            return $u;
        };
        $this->admin = $mk('Admin', 'admin');
        $this->hr = $mk('HR', 'hr');
        $this->rep = $mk('Rep', null, $dept->id);
        $this->rep2 = $mk('Rep2', null, $dept->id);
        $this->program = Goals::createProgram($this->admin, 'Phase QA', Carbon::parse('2027-01-04'), Carbon::parse('2027-04-05'), [$this->rep->id])['program'];
    }

    private function url(string $tail = ''): string
    {
        return '/performance/ninety-day-goals/program/' . $this->program->id . $tail;
    }

    public function test_rename_and_change_dates_moves_every_round(): void
    {
        $this->actingAs($this->hr)->putJson($this->url(), ['name' => 'Phase QA renamed', 'starts_on' => '2027-01-13', 'ends_on' => '2027-05-03'])->assertOk();

        $this->program->refresh();
        $this->assertSame('Phase QA renamed', $this->program->name);
        $this->assertSame('2027-01-11', $this->program->starts_on->toDateString(), 'a Wednesday start goes back to its Monday');
        $round = NinetyDayRound::where('program_id', $this->program->id)->first();
        $this->assertSame('2027-01-11', $round->starts_on->toDateString());
        $this->assertSame('2027-05-03', $round->ends_on->toDateString());
    }

    public function test_the_start_cannot_move_once_a_weekly_status_is_in_but_the_end_can(): void
    {
        $round = NinetyDayRound::where('program_id', $this->program->id)->first();
        $pid = DB::table('sales_pipelines')->insertGetId(['party_name' => 'QA Co ' . uniqid(), 'salesperson_id' => $this->rep->id, 'created_at' => now(), 'updated_at' => now()]);
        $goal = NinetyDayGoal::create(['round_id' => $round->id, 'user_id' => $this->rep->id, 'sales_pipeline_id' => $pid, 'client_name' => 'QA Co', 'goal' => 'Trial', 'monthly_potential' => 1]);
        NinetyDayGoalCheckin::create(['round_id' => $round->id, 'goal_id' => $goal->id, 'week_no' => 0, 'due_on' => '2027-01-11', 'status' => 'On Track']);

        $this->actingAs($this->admin)->putJson($this->url(), ['name' => 'Phase QA', 'starts_on' => '2027-01-11', 'ends_on' => '2027-04-05'])->assertStatus(422);
        $this->actingAs($this->admin)->putJson($this->url(), ['name' => 'Phase QA', 'starts_on' => '2027-01-04', 'ends_on' => '2027-04-26'])->assertOk();
        $this->assertSame('2027-04-26', $this->program->fresh()->ends_on->toDateString());
    }

    public function test_add_then_remove_a_person_with_their_task_and_notification(): void
    {
        $this->actingAs($this->admin)->postJson($this->url('/participants'), ['user_ids' => [$this->rep2->id]])->assertOk();
        $round = NinetyDayRound::where('program_id', $this->program->id)->where('user_id', $this->rep2->id)->first();
        $this->assertNotNull($round);
        $this->rep2->notify(new ProgramInvite($this->program, $round));
        $this->assertTrue(Task::where('user_id', $this->rep2->id)->where('title', 'like', '90-Day Goals: add your target customers%')->exists());

        $this->actingAs($this->admin)->deleteJson($this->url('/participants/' . $this->rep2->id))->assertOk();

        $this->assertFalse(NinetyDayRound::where('program_id', $this->program->id)->where('user_id', $this->rep2->id)->exists());
        $this->assertFalse(Task::where('user_id', $this->rep2->id)->where('title', 'like', '90-Day Goals:%')->exists(), 'their program tasks go');
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->rep2->id)->where('type', ProgramInvite::class)->count(), 'their invite goes');
        $this->assertTrue(NinetyDayRound::where('program_id', $this->program->id)->where('user_id', $this->rep->id)->exists(), 'the other participant stays');
    }

    public function test_reopening_the_window_lets_people_add_customers_again(): void
    {
        $round = NinetyDayRound::where('program_id', $this->program->id)->first();
        DB::table('ninety_day_rounds')->where('id', $round->id)->update(['created_at' => now()->subDays(5)]);
        $this->assertFalse(Goals::customerWindowOpen($round->fresh()));

        $this->actingAs($this->hr)->postJson($this->url('/window'), ['until' => now()->addDay()->format('Y-m-d H:i')])->assertOk();

        $this->assertTrue(Goals::customerWindowOpen($round->fresh()));
    }

    public function test_only_an_admin_can_delete_and_it_takes_everything_the_program_created(): void
    {
        $round = NinetyDayRound::where('program_id', $this->program->id)->first();
        $this->rep->notify(new ProgramInvite($this->program, $round));

        $this->actingAs($this->hr)->deleteJson($this->url(), ['confirm_name' => 'Phase QA'])->assertForbidden();
        $this->actingAs($this->admin)->deleteJson($this->url(), ['confirm_name' => 'wrong'])->assertStatus(422);
        $this->actingAs($this->admin)->deleteJson($this->url(), ['confirm_name' => 'Phase QA'])->assertOk();

        $this->assertNull(NinetyDayProgram::find($this->program->id));
        $this->assertFalse(NinetyDayRound::where('program_id', $this->program->id)->exists());
        $this->assertFalse(Task::where('user_id', $this->rep->id)->where('title', 'like', '90-Day Goals:%')->exists());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->rep->id)->where('type', ProgramInvite::class)->count());
    }

    public function test_the_programs_list_shows_every_program_with_edit_for_hr_and_delete_only_for_an_admin(): void
    {
        $hr = $this->actingAs($this->hr)->get('/performance/ninety-day-goals/programs')->assertOk()->getContent();
        $this->assertStringContainsString('Phase QA', $hr);
        $this->assertStringContainsString('manage=1', $hr, 'Edit opens the program with its Manage panel');
        $this->assertStringNotContainsString('ndg-prog-del"', $hr);

        $admin = $this->actingAs($this->admin)->get('/performance/ninety-day-goals/programs?status=upcoming')->assertOk()->getContent();
        $this->assertStringContainsString('Phase QA', $admin, 'a program starting in 2027 is upcoming');
        $this->assertStringContainsString('ndg-prog-del"', $admin);
        $this->assertStringNotContainsString('Phase QA', $this->actingAs($this->admin)->get('/performance/ninety-day-goals/programs?status=ended')->getContent());

        $this->actingAs($this->rep)->get('/performance/ninety-day-goals/programs')->assertRedirect();
    }

    public function test_the_retired_pages_send_people_to_the_page_that_replaced_them(): void
    {
        $this->actingAs($this->admin)->get('/performance/ninety-day-goals')->assertRedirect(route('performance.ninety-day-goals.program-dashboard'));
        $this->actingAs($this->rep)->get('/performance/ninety-day-goals')->assertRedirect(route('performance.ninety-day-goals.my-program'));
        $this->actingAs($this->rep)->get('/performance/ninety-day-goals/past')->assertRedirect(route('performance.ninety-day-goals.my-program'));
        // Admin keeps the finished-rounds record, now reached from the Programs list.
        $this->actingAs($this->admin)->get('/performance/ninety-day-goals/past')->assertOk();

        // With nothing finished, My Programs goes straight into the current program.
        $round = NinetyDayRound::where('program_id', $this->program->id)->where('user_id', $this->rep->id)->first();
        $this->actingAs($this->rep)->get('/performance/ninety-day-goals/my-program')->assertRedirect(route('performance.ninety-day-goals.round', $round->id));
    }

    public function test_my_programs_lists_finished_rounds_once_there_are_some(): void
    {
        NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 0, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);

        $html = $this->actingAs($this->rep)->get('/performance/ninety-day-goals/my-program')->assertOk()->getContent();

        $this->assertStringContainsString('Open my program', $html);
        $this->assertStringContainsString('Finished programs', $html);
        $this->assertStringContainsString('View report', $html);
    }

    public function test_every_weekly_comment_can_be_read_on_the_program_page_and_the_page_size_can_change(): void
    {
        $round = NinetyDayRound::where('program_id', $this->program->id)->first();
        DB::table('ninety_day_rounds')->where('id', $round->id)->update(['starts_on' => now()->subWeeks(3)->startOfWeek()->toDateString()]);
        DB::table('ninety_day_programs')->where('id', $this->program->id)->update(['starts_on' => now()->subWeeks(3)->startOfWeek()->toDateString()]);
        $pid = DB::table('sales_pipelines')->insertGetId(['party_name' => 'Comment Co ' . uniqid(), 'salesperson_id' => $this->rep->id, 'created_at' => now(), 'updated_at' => now()]);
        $goal = NinetyDayGoal::create(['round_id' => $round->id, 'user_id' => $this->rep->id, 'sales_pipeline_id' => $pid, 'client_name' => 'Comment Co', 'goal' => 'Trial', 'monthly_potential' => 1]);
        $w0 = NinetyDayGoalCheckin::create(['round_id' => $round->id, 'goal_id' => $goal->id, 'week_no' => 0, 'due_on' => now()->subWeeks(3)->startOfWeek(), 'status' => 'On Track', 'comment' => 'Met the buyer']);
        NinetyDayGoalCheckin::create(['round_id' => $round->id, 'goal_id' => $goal->id, 'week_no' => 1, 'due_on' => now()->subWeeks(2)->startOfWeek(), 'status' => 'Off Track', 'comment' => 'Buyer on leave']);
        \App\Models\NinetyDayGoalNote::create(['goal_id' => $goal->id, 'checkin_id' => $w0->id, 'week_no' => 0, 'user_id' => $this->admin->id, 'body' => 'Push for the trial']);

        $html = $this->actingAs($this->admin)->get($this->url('?per_page=25'))->assertOk()->getContent();

        $this->assertStringContainsString('Met the buyer', $html, 'week 0 comment, on hover and in History');
        $this->assertStringContainsString('Buyer on leave', $html, 'week 1 comment too - not just the last one');
        $this->assertStringContainsString('Push for the trial', $html, "the admin's comment shows as well");
        $this->assertStringContainsString('ndg-hist-btn', $html);
        $this->assertStringContainsString('id="ndgHistModal"', $html);
        $this->assertSame(1, substr_count($html, 'id="ndgHistModal"'), 'one History window, though the table appears on two tabs');
        $this->assertMatchesRegularExpression('/<option value="25"\s+selected/', $html, 'the Show picker remembers 25');
    }

    public function test_the_program_page_offers_manage_to_hr_and_delete_only_to_an_admin(): void
    {
        $hrPage = $this->actingAs($this->hr)->get($this->url())->assertOk()->getContent();
        $this->assertStringContainsString('ndgManageModal', $hrPage);
        $this->assertStringNotContainsString('id="ndgMgDelete"', $hrPage, 'no delete button for HR');

        $adminPage = $this->actingAs($this->admin)->get($this->url())->assertOk()->getContent();
        $this->assertStringContainsString('ndgManageModal', $adminPage);
        $this->assertStringContainsString('id="ndgMgDelete"', $adminPage);
    }
}
