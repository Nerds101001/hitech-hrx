<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Task;
use App\Models\TaskSchedule;
use App\Models\User;
use App\Notifications\Task\TaskAssigned;
use App\Services\TaskScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Task Management / My Tasks fixes of 5 Oct 2026 - deadlines, visibility of
 * remarks and CC, sorting/filters, group edits, objections, and the
 * recurring engine's holiday and empty-schedule behaviour.
 *
 * Fixed dates: "today" is Wednesday 10 March 2032 (no holidays in the data).
 */
class TaskManagementFixesTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $manager;
    private User $report;
    private User $other;
    private User $cc;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Carbon::setTestNow('2032-03-10 11:00:00');
        $this->admin = $this->person('admin');
        $this->manager = $this->person('manager');
        $this->report = $this->person(null, $this->manager->id);
        $this->other = $this->person();
        $this->cc = $this->person();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(?string $role = null, ?int $reportsTo = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Fix ' . uniqid(), 'email' => 'tmf' . uniqid() . '@example.test',
            'code' => 'TMF' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        if ($reportsTo) {
            $u->forceFill(['reporting_to_id' => $reportsTo])->save();
        }
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }

        return $u->fresh();
    }

    private function task(array $over = []): Task
    {
        return Task::create(array_merge([
            'title' => 'QA FIX ' . uniqid(), 'type' => 'open', 'status' => 'new', 'priority' => 'medium',
            'user_id' => $this->report->id, 'assigned_by_id' => $this->manager->id, 'for_date' => now(),
            'due_date' => '2032-03-10 00:00:00', 'created_by_id' => $this->manager->id, 'updated_by_id' => $this->manager->id,
        ], $over));
    }

    // ---- deadlines --------------------------------------------------------

    public function test_a_task_with_a_time_is_overdue_at_that_time_not_18_hours_later(): void
    {
        $t = $this->task(['due_date' => '2032-03-10 15:00:00']);
        Carbon::setTestNow('2032-03-10 15:01:00');
        $this->assertTrue($t->fresh()->is_overdue);
        $this->assertTrue(Task::overdue()->whereKey($t->id)->exists());
        $this->assertSame('10 Mar 2032, 03:00 PM', $t->fresh()->dueLabel());
    }

    public function test_a_day_only_task_runs_to_6pm_and_never_shows_midnight(): void
    {
        $t = $this->task(['due_date' => '2032-03-10']);
        Carbon::setTestNow('2032-03-10 17:59:00');
        $this->assertFalse($t->fresh()->is_overdue);
        $this->assertTrue(Task::dueToday()->whereKey($t->id)->exists());
        Carbon::setTestNow('2032-03-10 18:01:00');
        $this->assertTrue($t->fresh()->is_overdue);
        $this->assertFalse(Task::dueToday()->whereKey($t->id)->exists(), 'never Due Today and Overdue at once');
        $this->assertSame('10 Mar 2032', $t->fresh()->dueLabel());
    }

    public function test_a_task_with_a_time_is_late_once_that_time_has_passed(): void
    {
        $timed = fn ($at) => $this->task(['due_date' => '2032-03-10 15:00:00', 'status' => 'completed', 'completed_at' => $at])->fresh()->completion_timing;
        $this->assertSame('on_time', $timed('2032-03-10 14:30:00'));
        $this->assertSame('late', $timed('2032-03-10 16:00:00'), 'used to count as on time for the rest of the day');
        // day-only tasks keep the day rule
        $this->assertSame('on_time', $this->task(['due_date' => '2032-03-10', 'status' => 'completed', 'completed_at' => '2032-03-10 19:00:00'])->fresh()->completion_timing);
    }

    public function test_pendency_overdue_matches_the_tiles(): void
    {
        $this->task(['due_date' => '2032-03-10']);              // due today, not overdue at 11:00
        $this->task(['due_date' => '2032-03-09']);              // overdue
        $this->task(['due_date' => '2032-03-10 09:00:00']);     // timed, overdue
        $r = $this->actingAs($this->manager)->get('/task-management?mine=by');
        $r->assertOk();
        $stats = $r->viewData('stats');
        $pend = $r->viewData('pendency');
        $this->assertSame(2, $stats['overdue']);
        $this->assertSame(1, $stats['due_today']);
        $this->assertSame($stats['overdue'], $pend['totalOverdue']);
        $this->assertSame($stats['overdue'], (int) $pend['byPerson']->sum('overdue'));
    }

    public function test_a_past_time_today_is_refused_but_a_day_only_today_is_fine(): void
    {
        $base = ['title' => 'QA past', 'employee_ids' => [$this->report->id], 'priority' => 'low'];
        $this->actingAs($this->manager)->postJson('/task-management', $base + ['due_date' => '2032-03-10 09:00'])->assertStatus(422);
        $this->actingAs($this->manager)->postJson('/task-management', $base + ['due_date' => '2032-03-10'])->assertOk();
    }

    // ---- visibility -------------------------------------------------------

    public function test_admin_and_the_assignees_manager_can_read_the_remarks_others_cannot(): void
    {
        $t = $this->task(['assigned_by_id' => $this->other->id]);
        $t->taskUpdates()->create(['comment' => 'half done', 'update_type' => 'comment', 'created_by_id' => $this->report->id, 'updated_by_id' => $this->report->id]);

        $this->actingAs($this->admin)->getJson("/my-tasks/{$t->id}/thread")->assertOk()->assertJsonPath('updates.0.comment', 'half done');
        $this->actingAs($this->manager)->getJson("/my-tasks/{$t->id}/thread")->assertOk();
        $this->actingAs($this->cc)->getJson("/my-tasks/{$t->id}/thread")->assertForbidden();
        $this->actingAs($this->admin)->postJson("/my-tasks/{$t->id}/reply", ['comment' => 'following up'])->assertOk();
    }

    public function test_the_thread_carries_the_task_details_and_cc_list(): void
    {
        $t = $this->task(['due_date' => '2032-03-11 16:30:00']);
        $t->taskCcs()->create(['user_id' => $this->cc->id, 'created_by_id' => $this->manager->id]);
        $this->actingAs($this->cc)->getJson("/my-tasks/{$t->id}/thread")->assertOk()
            ->assertJsonPath('task.due_date', '11 Mar 2032, 04:30 PM')
            ->assertJsonPath('task.cc_users.0', $this->cc->getFullName());
    }

    public function test_a_ccd_person_can_open_the_attachment(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('ref.jpg')->store('task-attachments', 'public');
        $t = $this->task(['attachment_path' => $path]);
        $t->taskCcs()->create(['user_id' => $this->cc->id, 'created_by_id' => $this->manager->id]);
        $this->actingAs($this->cc)->get("/tasks/{$t->id}/attachment")->assertOk();
        $this->actingAs($this->report)->get("/tasks/{$t->id}/attachment")->assertOk();   // the assignee, a plain employee
        $this->actingAs($this->other)->get("/tasks/{$t->id}/attachment")->assertForbidden();
    }

    // ---- list: sorting, person filter, group rows -------------------------

    public function test_columns_sort_and_pendency_opens_one_persons_tasks(): void
    {
        $b = $this->task(['title' => 'QA SORT B']);
        $a = $this->task(['title' => 'QA SORT A']);
        $this->task(['title' => 'QA SORT C', 'user_id' => $this->other->id]);
        $titles = fn ($q) => $this->actingAs($this->manager)->get('/task-management?mine=by&search=QA+SORT&' . $q)->viewData('tasks')->getCollection()->pluck('title')->all();

        $this->assertSame(['QA SORT A', 'QA SORT B', 'QA SORT C'], $titles('sort=title&dir=asc'));
        $this->assertSame(['QA SORT C', 'QA SORT B', 'QA SORT A'], $titles('sort=title&dir=desc'));
        $this->assertSame(['QA SORT A', 'QA SORT B'], $titles('sort=title&dir=asc&user_id=' . $this->report->id . '&status=open'));
    }

    public function test_an_edit_on_a_group_row_reaches_everyone_and_says_what_changed(): void
    {
        $batch = (string) \Illuminate\Support\Str::uuid();
        $one = $this->task(['batch_id' => $batch]);
        $two = $this->task(['batch_id' => $batch, 'user_id' => $this->other->id]);
        $this->actingAs($this->manager)->putJson("/task-management/{$one->id}", [
            'title' => 'QA renamed', 'due_date' => '2032-03-12 10:00', 'priority' => 'high', 'apply_to_batch' => true,
        ])->assertOk();
        foreach ([$one, $two] as $t) {
            $t->refresh();
            $this->assertSame('QA renamed', $t->title);
            $this->assertSame('high', $t->priority);
            $this->assertStringContainsString('Due date changed from 10 Mar 2032 to 12 Mar 2032, 10:00 AM', $t->taskUpdates()->latest('id')->value('comment'));
        }
    }

    public function test_reassigning_tells_the_new_person_and_rearms_the_overdue_reminder(): void
    {
        $t = $this->task(['delay_notified_at' => now()]);
        $this->actingAs($this->manager)->putJson("/task-management/{$t->id}", ['title' => $t->title, 'due_date' => '2032-03-10', 'user_id' => $this->other->id])->assertOk();
        app()->terminate();
        $t->refresh();
        $this->assertSame($this->other->id, (int) $t->user_id);
        $this->assertNull($t->delay_notified_at);
        $this->assertStringContainsString('Reassigned from', $t->taskUpdates()->latest('id')->value('comment'));
        Notification::assertSentTo($this->other, TaskAssigned::class);
    }

    public function test_moving_the_deadline_rearms_the_overdue_reminder(): void
    {
        $t = $this->task(['due_date' => '2032-03-09', 'delay_notified_at' => now()]);
        $this->actingAs($this->manager)->putJson("/task-management/{$t->id}", ['title' => $t->title, 'due_date' => '2032-03-11'])->assertOk();
        $this->assertNull($t->fresh()->delay_notified_at);
    }

    // ---- workflow guards ---------------------------------------------------

    public function test_an_objection_needs_a_completed_task(): void
    {
        $open = $this->task();
        $this->actingAs($this->manager)->postJson("/task-management/{$open->id}/objection", ['reason' => 'redo'])->assertStatus(422);
        $done = $this->task(['status' => 'completed', 'completed_at' => now()]);
        $this->actingAs($this->manager)->postJson("/task-management/{$done->id}/objection", ['reason' => 'redo'])->assertOk();
    }

    public function test_complete_refuses_a_closed_or_objected_task(): void
    {
        $done = $this->task(['status' => 'completed', 'completed_at' => now()]);
        $this->actingAs($this->report)->postJson("/my-tasks/{$done->id}/complete")->assertStatus(422);
        $objected = $this->task(['status' => 'objection']);
        $this->actingAs($this->report)->postJson("/my-tasks/{$objected->id}/complete")->assertStatus(422);
        $open = $this->task();
        $this->actingAs($this->report)->postJson("/my-tasks/{$open->id}/complete", ['remarks' => 'done'])->assertOk();
    }

    // ---- recurring --------------------------------------------------------

    private function schedule(array $over = []): TaskSchedule
    {
        $s = TaskSchedule::create(array_merge([
            'title' => 'QA weekly ' . uniqid(), 'priority' => 'medium', 'frequency' => 'weekly', 'weekday' => 5,   // Friday
            'target_user_ids' => [$this->report->id], 'cc_user_ids' => [], 'assigned_by_id' => $this->manager->id, 'is_active' => true,
        ], $over));
        $s->forceFill(['created_at' => '2032-03-01 09:00:00'])->save();

        return $s->fresh();
    }

    public function test_an_occurrence_on_a_holiday_moves_to_the_next_working_day(): void
    {
        Holiday::create(['name' => 'QA holiday', 'date' => '2032-03-12', 'code' => 'QAH' . random_int(1000, 9999), 'status' => \App\Enums\Status::ACTIVE, 'holiday_type' => 'full']);
        $s = $this->schedule();

        $this->assertCount(0, TaskScheduleService::fireIfDue($s, Carbon::parse('2032-03-12')), 'not on the holiday');
        $this->assertSame('2032-03-13', $s->fresh()->nextOccurrence(Carbon::parse('2032-03-11'))->toDateString(), 'the preview shows Saturday');
        $this->assertCount(1, TaskScheduleService::fireIfDue($s->fresh(), Carbon::parse('2032-03-13')), 'fires Saturday instead');
        $this->assertCount(0, TaskScheduleService::fireIfDue($s->fresh(), Carbon::parse('2032-03-15')), 'Monday is an ordinary day');
    }

    public function test_a_schedule_whose_people_have_all_left_does_not_pretend_to_run(): void
    {
        $s = $this->schedule(['frequency' => 'daily', 'weekday' => null]);
        $this->report->forceFill(['status' => 'inactive'])->save();

        $this->assertCount(0, TaskScheduleService::fireIfDue($s, Carbon::parse('2032-03-10')));
        $this->assertNull($s->fresh()->last_run_on, 'not recorded as a run');
    }

    public function test_the_same_recurring_task_cannot_be_set_up_twice_for_the_same_people(): void
    {
        $payload = ['title' => 'QA Daily Report', 'employee_ids' => [$this->report->id], 'interval' => 'weekly', 'weekday' => 2];
        $this->actingAs($this->manager)->postJson('/task-management', $payload)->assertOk();
        $this->actingAs($this->admin)->postJson('/task-management', ['title' => ' qa daily report '] + $payload)->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
        $this->actingAs($this->admin)->postJson('/task-management', ['employee_ids' => [$this->other->id]] + $payload)->assertOk();
    }
}
