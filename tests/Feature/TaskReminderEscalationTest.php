<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Notifications\Task\TaskDueSoon;
use App\Notifications\Task\TaskEscalated;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Due-soon reminders and manager escalation (the #3 "accountability" round).
 * "Today" is Wednesday 10 March 2032.
 */
class TaskReminderEscalationTest extends TestCase
{
    use DatabaseTransactions;

    private User $manager;
    private User $report;
    private User $assigner;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Carbon::setTestNow('2032-03-10 09:00:00');
        config(['tasks.escalate_after_days' => 2, 'tasks.escalate_from' => '2032-03-01', 'tasks.reminder_lead_hours' => 24]);
        $this->manager = $this->person('manager');
        $this->report = $this->person(null, $this->manager->id);
        $this->assigner = $this->person('admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(?string $role = null, ?int $reportsTo = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Rem ' . uniqid(), 'email' => 'rem' . uniqid() . '@example.test',
            'code' => 'REM' . random_int(100000, 999999), 'password' => Hash::make('x'), 'status' => 'active',
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
            'title' => 'QA REM ' . uniqid(), 'type' => 'open', 'status' => 'new', 'priority' => 'medium',
            'user_id' => $this->report->id, 'assigned_by_id' => $this->assigner->id, 'for_date' => now(),
            'created_by_id' => $this->assigner->id, 'updated_by_id' => $this->assigner->id,
        ], $over));
    }

    // ---- due soon ---------------------------------------------------------

    public function test_a_task_due_tomorrow_reminds_the_assignee_once(): void
    {
        $t = $this->task(['due_date' => '2032-03-11']);
        $this->artisan('tasks:notify-due-soon');
        Notification::assertSentTo($this->report, TaskDueSoon::class);
        $this->assertNotNull($t->fresh()->due_soon_notified_at);

        Notification::fake();
        $this->artisan('tasks:notify-due-soon');
        Notification::assertNothingSent();
    }

    public function test_a_task_due_today_or_far_off_is_not_due_soon(): void
    {
        $this->task(['due_date' => '2032-03-10']);           // day-only due today - not "tomorrow"
        $this->task(['due_date' => '2032-03-20']);           // far off
        $this->artisan('tasks:notify-due-soon');
        Notification::assertNothingSent();
    }

    public function test_a_timed_task_within_the_lead_window_is_due_soon_but_not_a_past_time(): void
    {
        $soon = $this->task(['due_date' => '2032-03-10 15:00:00']);   // later today
        $past = $this->task(['due_date' => '2032-03-10 08:00:00']);   // already passed
        $this->artisan('tasks:notify-due-soon');
        Notification::assertSentTo($this->report, TaskDueSoon::class, fn ($n, $ch, $notifiable) => true);
        $this->assertNotNull($soon->fresh()->due_soon_notified_at);
        $this->assertNull($past->fresh()->due_soon_notified_at);
    }

    public function test_a_completed_task_is_not_reminded(): void
    {
        $this->task(['due_date' => '2032-03-11', 'status' => 'completed', 'completed_at' => now()]);
        $this->artisan('tasks:notify-due-soon');
        Notification::assertNothingSent();
    }

    // ---- escalation -------------------------------------------------------

    public function test_a_task_overdue_past_the_threshold_escalates_to_the_manager_once(): void
    {
        $t = $this->task(['due_date' => '2032-03-07']);   // 3 days ago, day-only -> overdue since 7th 6pm
        $this->artisan('tasks:escalate-overdue');
        Notification::assertSentTo($this->manager, TaskEscalated::class);
        Notification::assertNotSentTo($this->report, TaskEscalated::class);
        $this->assertNotNull($t->fresh()->escalated_at);

        Notification::fake();
        $this->artisan('tasks:escalate-overdue');
        Notification::assertNothingSent();
    }

    public function test_a_task_only_just_overdue_is_not_escalated_yet(): void
    {
        $this->task(['due_date' => '2032-03-09']);   // overdue since 9th 6pm, < 2 days
        $this->artisan('tasks:escalate-overdue');
        Notification::assertNothingSent();
    }

    public function test_the_storm_guard_spares_the_pre_golive_backlog(): void
    {
        config(['tasks.escalate_from' => '2032-03-05']);
        $old = $this->task(['due_date' => '2032-03-01']);   // deadline before the cutoff
        $new = $this->task(['due_date' => '2032-03-06']);   // deadline after the cutoff, > 2 days overdue
        $this->artisan('tasks:escalate-overdue');
        Notification::assertSentTo($this->manager, TaskEscalated::class);
        $this->assertNull($old->fresh()->escalated_at, 'old backlog left alone');
        $this->assertNotNull($new->fresh()->escalated_at);
    }

    public function test_escalation_falls_back_to_the_assigner_when_there_is_no_manager(): void
    {
        $orphan = $this->person();   // no reporting_to_id
        $t = $this->task(['user_id' => $orphan->id, 'due_date' => '2032-03-06']);
        $this->artisan('tasks:escalate-overdue');
        Notification::assertSentTo($this->assigner, TaskEscalated::class);
        $this->assertNotNull($t->fresh()->escalated_at);
    }

    public function test_a_completed_task_is_not_escalated(): void
    {
        $this->task(['due_date' => '2032-03-06', 'status' => 'completed', 'completed_at' => now()]);
        $this->artisan('tasks:escalate-overdue');
        Notification::assertNothingSent();
    }
}
