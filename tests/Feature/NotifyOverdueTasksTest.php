<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Notifications\Task\TaskOverdue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The daily "your task is overdue" email — sent once per task, never a daily
 * nag, and only to tasks that are genuinely still open past their due date.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds real
 * imported production data.
 */
class NotifyOverdueTasksTest extends TestCase
{
    use DatabaseTransactions;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->employee = User::create([
            'first_name' => 'QA', 'last_name' => 'Overdue',
            'email' => 'ovd' . uniqid() . '@example.test',
            'code' => 'OVD' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'),
            'status' => 'active',
        ]);
    }

    private function task(array $overrides = []): Task
    {
        return Task::create(array_merge([
            'title' => 'QA Overdue Task', 'type' => 'open',
            'user_id' => $this->employee->id, 'assigned_by_id' => $this->employee->id,
            'for_date' => now(), 'status' => 'new',
        ], $overrides));
    }

    public function test_an_overdue_open_task_gets_notified_and_stamped(): void
    {
        $task = $this->task(['due_date' => now()->subDays(2)]);

        $this->artisan('tasks:notify-overdue')->assertExitCode(0);

        Notification::assertSentTo($this->employee, TaskOverdue::class);
        $this->assertNotNull($task->fresh()->delay_notified_at);
    }

    /** The discriminator: a completed task past its due date is not overdue at all. */
    public function test_a_completed_task_is_not_notified(): void
    {
        $task = $this->task(['due_date' => now()->subDays(2), 'status' => 'completed']);

        $this->artisan('tasks:notify-overdue')->assertExitCode(0);

        Notification::assertNotSentTo($this->employee, TaskOverdue::class);
        $this->assertNull($task->fresh()->delay_notified_at);
    }

    /** A task not yet due is left alone. */
    public function test_a_task_not_yet_due_is_not_notified(): void
    {
        $this->task(['due_date' => now()->addDays(3)]);

        $this->artisan('tasks:notify-overdue')->assertExitCode(0);

        Notification::assertNotSentTo($this->employee, TaskOverdue::class);
    }

    /** Once notified, a second run must not send it again — a nudge, not a nag. */
    public function test_a_task_already_notified_is_not_notified_twice(): void
    {
        $this->task(['due_date' => now()->subDays(2), 'delay_notified_at' => now()->subDay()]);

        $this->artisan('tasks:notify-overdue')->assertExitCode(0);

        Notification::assertNotSentTo($this->employee, TaskOverdue::class);
    }
}
