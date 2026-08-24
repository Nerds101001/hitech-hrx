<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\TaskManagementController;
use App\Models\Task;
use App\Models\TaskSchedule;
use App\Models\User;
use App\Services\TaskScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Recurring task schedules — "every Tuesday" / "every 7th of the month".
 *
 * Reported live: the interval picker only ever computed a single due date
 * for a single task, nothing actually recurred, and there was no way to
 * remove one person or stop it once created. This covers the real engine
 * that replaced it: TaskSchedule rows, the daily tasks:generate-recurring
 * command that fires them, and the immediate first-fire from the assign
 * form when the chosen day is today.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds
 * real imported production data.
 */
class TaskScheduleTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $managerA;
    private User $reportA;
    private User $reportB;
    private TaskManagementController $mgmt;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin    = $this->makeUser('admin');
        $this->managerA = $this->makeUser('manager');
        $this->reportA  = $this->makeUser(null, $this->managerA->id);
        $this->reportB  = $this->makeUser(null, $this->managerA->id);
        $this->mgmt     = app(TaskManagementController::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(?string $role = null, ?int $reportsTo = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Recur',
            'email'      => 'rec' . uniqid() . '@example.test',
            'code'       => 'REC' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
        if ($reportsTo) {
            $u->forceFill(['reporting_to_id' => $reportsTo])->save();
        }
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u;
    }

    private function jsonReq(array $payload): Request
    {
        $r = Request::create('/task-management', 'POST', [], [], [], [], json_encode($payload));
        $r->headers->set('Content-Type', 'application/json');
        $r->setLaravelSession(app('session.store'));
        return $r;
    }

    // ══ recurrence label ═══════════════════════════════════════════════════

    public function test_recurrence_label_for_weekly(): void
    {
        $s = TaskSchedule::make(['frequency' => 'weekly', 'weekday' => 2]); // Tuesday
        $this->assertEquals('Every Tuesday', $s->recurrenceLabel());
    }

    public function test_recurrence_label_for_monthly(): void
    {
        $s = TaskSchedule::make(['frequency' => 'monthly', 'day_of_month' => 7]);
        $this->assertEquals('Every 7th of the month', $s->recurrenceLabel());
        $s->day_of_month = 21;
        $this->assertEquals('Every 21st of the month', $s->recurrenceLabel());
        $s->day_of_month = 11;
        $this->assertEquals('Every 11th of the month', $s->recurrenceLabel());
    }

    // ══ matchesToday, including the short-month clamp ══════════════════════

    public function test_monthly_schedule_for_the_31st_fires_on_the_30th_in_a_shorter_month(): void
    {
        $s = TaskSchedule::make(['frequency' => 'monthly', 'day_of_month' => 31]);
        $this->assertTrue($s->matchesToday(Carbon::parse('2026-09-30'))); // September has 30 days
        $this->assertFalse($s->matchesToday(Carbon::parse('2026-09-29')));
        $this->assertTrue($s->matchesToday(Carbon::parse('2026-08-31'))); // August genuinely has 31
    }

    public function test_weekly_schedule_only_matches_its_own_weekday(): void
    {
        $s = TaskSchedule::make(['frequency' => 'weekly', 'weekday' => 1]); // Monday
        $this->assertTrue($s->matchesToday(Carbon::parse('2026-08-24')));  // a Monday
        $this->assertFalse($s->matchesToday(Carbon::parse('2026-08-25'))); // a Tuesday
    }

    // ══ assigning through Task Management creates a schedule and fires it ══

    /** THE CORE FEATURE. Picking Weekly with today's weekday assigns today, not just once in the abstract. */
    public function test_a_weekly_schedule_matching_today_fires_immediately(): void
    {
        Auth::login($this->admin);
        $response = $this->mgmt->store($this->jsonReq([
            'title' => 'QA Weekly', 'interval' => 'weekly', 'weekday' => now()->dayOfWeek,
            'employee_ids' => [$this->reportA->id, $this->reportB->id],
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $schedule = TaskSchedule::find($data['schedule_id']);
        $this->assertNotNull($schedule);
        $this->assertEquals(now()->toDateString(), $schedule->last_run_on->toDateString());
        $this->assertEquals(2, Task::where('task_schedule_id', $schedule->id)->count());
    }

    /** The discriminator: a weekday that is NOT today creates the schedule but assigns nothing yet. */
    public function test_a_weekly_schedule_not_matching_today_does_not_fire_yet(): void
    {
        Auth::login($this->admin);
        $offDay = (now()->dayOfWeek + 3) % 7;
        $response = $this->mgmt->store($this->jsonReq([
            'title' => 'QA Weekly Later', 'interval' => 'weekly', 'weekday' => $offDay,
            'employee_ids' => [$this->reportA->id],
        ]));
        $data = json_decode($response->getContent(), true);

        $schedule = TaskSchedule::find($data['schedule_id']);
        $this->assertNull($schedule->last_run_on);
        $this->assertEquals(0, Task::where('task_schedule_id', $schedule->id)->count());
    }

    public function test_a_one_time_assignment_does_not_create_a_schedule(): void
    {
        Auth::login($this->admin);
        $this->mgmt->store($this->jsonReq([
            'title' => 'QA One-Time', 'employee_ids' => [$this->reportA->id],
        ]));

        $this->assertFalse(TaskSchedule::where('title', 'QA One-Time')->exists());
    }

    // ══ removing one person / stopping the whole schedule ══════════════════

    public function test_removing_a_person_drops_them_from_future_firings_without_touching_others(): void
    {
        $schedule = TaskSchedule::create([
            'title' => 'QA Remove', 'frequency' => 'daily', 'priority' => 'medium',
            'target_user_ids' => [$this->reportA->id, $this->reportB->id],
            'assigned_by_id' => $this->admin->id, 'is_active' => true,
            'created_by_id' => $this->admin->id, 'updated_by_id' => $this->admin->id,
        ]);

        Auth::login($this->admin);
        $req = Request::create('/task-management/schedules/' . $schedule->id . '/remove-user', 'POST', ['user_id' => $this->reportA->id]);
        $req->setLaravelSession(app('session.store'));
        $this->mgmt->removeFromSchedule($req, $schedule->id);

        $fresh = $schedule->fresh();
        $this->assertNotContains($this->reportA->id, $fresh->target_user_ids);
        $this->assertContains($this->reportB->id, $fresh->target_user_ids);
    }

    public function test_stopping_a_schedule_prevents_any_further_firing(): void
    {
        $schedule = TaskSchedule::create([
            'title' => 'QA Stop', 'frequency' => 'daily', 'priority' => 'medium',
            'target_user_ids' => [$this->reportA->id],
            'assigned_by_id' => $this->admin->id, 'is_active' => true,
            'created_by_id' => $this->admin->id, 'updated_by_id' => $this->admin->id,
        ]);

        Auth::login($this->admin);
        $this->mgmt->stopSchedule($schedule->id);

        $this->assertFalse($schedule->fresh()->is_active);
        $fired = TaskScheduleService::fireIfDue($schedule->fresh());
        $this->assertTrue($fired->isEmpty());
    }

    /** A manager cannot stop or edit a recurring task belonging to someone else. */
    public function test_a_manager_cannot_stop_another_persons_schedule(): void
    {
        $schedule = TaskSchedule::create([
            'title' => 'QA Not Yours', 'frequency' => 'daily', 'priority' => 'medium',
            'target_user_ids' => [$this->reportA->id],
            'assigned_by_id' => $this->admin->id, 'is_active' => true,
            'created_by_id' => $this->admin->id, 'updated_by_id' => $this->admin->id,
        ]);

        Auth::login($this->managerA);
        try {
            $this->mgmt->stopSchedule($schedule->id);
            $this->fail('a manager stopped a schedule they did not create');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
        $this->assertTrue($schedule->fresh()->is_active);
    }

    // ══ the daily generator command ═════════════════════════════════════════

    public function test_the_daily_command_fires_a_matching_schedule_once(): void
    {
        $schedule = TaskSchedule::create([
            'title' => 'QA Cron', 'frequency' => 'daily', 'priority' => 'medium',
            'target_user_ids' => [$this->reportA->id],
            'assigned_by_id' => $this->admin->id, 'is_active' => true,
            'created_by_id' => $this->admin->id, 'updated_by_id' => $this->admin->id,
        ]);

        Artisan::call('tasks:generate-recurring');

        $this->assertEquals(1, Task::where('task_schedule_id', $schedule->id)->count());
    }

    /** Running the command twice in one day must not double-assign. */
    public function test_the_daily_command_does_not_fire_the_same_schedule_twice_in_one_day(): void
    {
        $schedule = TaskSchedule::create([
            'title' => 'QA No Double Fire', 'frequency' => 'daily', 'priority' => 'medium',
            'target_user_ids' => [$this->reportA->id],
            'assigned_by_id' => $this->admin->id, 'is_active' => true,
            'created_by_id' => $this->admin->id, 'updated_by_id' => $this->admin->id,
        ]);

        Artisan::call('tasks:generate-recurring');
        Artisan::call('tasks:generate-recurring');

        $this->assertEquals(1, Task::where('task_schedule_id', $schedule->id)->count());
    }

    public function test_the_daily_command_skips_an_inactive_schedule(): void
    {
        $schedule = TaskSchedule::create([
            'title' => 'QA Inactive', 'frequency' => 'daily', 'priority' => 'medium',
            'target_user_ids' => [$this->reportA->id],
            'assigned_by_id' => $this->admin->id, 'is_active' => false,
            'created_by_id' => $this->admin->id, 'updated_by_id' => $this->admin->id,
        ]);

        Artisan::call('tasks:generate-recurring');

        $this->assertEquals(0, Task::where('task_schedule_id', $schedule->id)->count());
    }
}
