<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\MyTasksController;
use App\Http\Controllers\tenant\TaskController;
use App\Http\Controllers\tenant\TaskManagementController;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Task Management — bulk assignment, department fan-out, and the RBAC that
 * did not exist before this module: originally TaskController::update /
 * updateStatus / destroy had NO ownership check at all — any admin, hr,
 * accounts, manager or tasks.manage user could edit or delete ANY task by
 * guessing its id, including one belonging to a completely different
 * manager's team. And "My Tasks" showed nothing from the tasks table, so
 * there was no employee-facing view or completion flow at all.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds real
 * imported production data.
 */
class TaskManagementScopeTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $managerA;
    private User $managerB;
    private User $reportA;
    private User $reportB;
    private TaskManagementController $mgmt;
    private TaskController $legacy;
    private MyTasksController $myTasks;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin    = $this->makeUser('admin');
        $this->managerA = $this->makeUser('manager');
        $this->managerB = $this->makeUser('manager');
        $this->reportA  = $this->makeUser(null, $this->managerA->id);
        $this->reportB  = $this->makeUser(null, $this->managerB->id);

        $this->mgmt    = app(TaskManagementController::class);
        $this->legacy  = app(TaskController::class);
        $this->myTasks = app(MyTasksController::class);
    }

    private function makeUser(?string $role = null, ?int $reportsTo = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Task',
            'email'      => 'tsk' . uniqid() . '@example.test',
            'code'       => 'TSK' . random_int(100000, 999999),
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

    // ══ manager cannot reach outside their own team ═══════════════════════

    /** THE CORE BUG. A manager assigning to a whole department must not pick up someone else's report. */
    public function test_bulk_assign_by_department_silently_drops_people_outside_the_managers_team(): void
    {
        $dept = Department::create(['name' => 'QA Dept ' . uniqid(), 'code' => 'QAD' . random_int(100000, 999999), 'status' => 'active']);
        $this->reportA->forceFill(['department_id' => $dept->id])->save();
        $this->reportB->forceFill(['department_id' => $dept->id])->save();

        Auth::login($this->managerA);
        $response = $this->mgmt->store($this->jsonReq([
            'title' => 'QA Bulk Task',
            'department_ids' => [$dept->id],
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['assigned'], 'only reportA should have been assigned');
        $this->assertTrue(Task::where('user_id', $this->reportA->id)->where('title', 'QA Bulk Task')->exists());
        $this->assertFalse(Task::where('user_id', $this->reportB->id)->where('title', 'QA Bulk Task')->exists(), 'a task leaked to a different manager\'s report');
    }

    /** The discriminator: admin assigning to the SAME department reaches everyone. */
    public function test_admin_bulk_assign_by_department_reaches_everyone_in_it(): void
    {
        $dept = Department::create(['name' => 'QA Dept ' . uniqid(), 'code' => 'QAD' . random_int(100000, 999999), 'status' => 'active']);
        $this->reportA->forceFill(['department_id' => $dept->id])->save();
        $this->reportB->forceFill(['department_id' => $dept->id])->save();

        Auth::login($this->admin);
        $response = $this->mgmt->store($this->jsonReq([
            'title' => 'QA Admin Bulk',
            'department_ids' => [$dept->id],
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(2, $data['assigned']);
    }

    /** Explicitly hand-picking someone outside your team is dropped the same way as a department pick. */
    public function test_bulk_assign_by_employee_id_outside_team_is_dropped(): void
    {
        Auth::login($this->managerA);
        $response = $this->mgmt->store($this->jsonReq([
            'title' => 'QA Direct Pick',
            'employee_ids' => [$this->reportB->id],
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertFalse(Task::where('user_id', $this->reportB->id)->where('title', 'QA Direct Pick')->exists());
    }

    // ══ the legacy single-target endpoints, now with real ownership checks ═

    /** THE ORIGINAL BUG. Before this fix update() had no ownership check at all. */
    public function test_manager_cannot_update_a_task_belonging_to_another_managers_report(): void
    {
        $task = Task::create([
            'title' => 'Original', 'type' => 'open', 'user_id' => $this->reportB->id,
            'assigned_by_id' => $this->managerB->id, 'for_date' => now(), 'status' => 'new',
        ]);

        Auth::login($this->managerA);
        $response = $this->legacy->update(Request::create('/tasks/update/' . $task->id, 'POST', [
            'title' => 'Hijacked', 'type' => 'open',
        ]), $task->id);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Original', $task->fresh()->title);
    }

    /** Same for destroy — no other manager can delete a report's task that isn't theirs. */
    public function test_manager_cannot_delete_a_task_belonging_to_another_managers_report(): void
    {
        $task = Task::create([
            'title' => 'Keep Me', 'type' => 'open', 'user_id' => $this->reportB->id,
            'assigned_by_id' => $this->managerB->id, 'for_date' => now(), 'status' => 'new',
        ]);

        Auth::login($this->managerA);
        $response = $this->legacy->destroy($task->id);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertNotNull($task->fresh(), 'the task was deleted by a manager outside its team');
    }

    /** The discriminator: a manager CAN manage their own report's task. */
    public function test_manager_can_update_their_own_reports_task(): void
    {
        $task = Task::create([
            'title' => 'Mine', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
        ]);

        Auth::login($this->managerA);
        $response = $this->legacy->update(Request::create('/tasks/update/' . $task->id, 'POST', [
            'title' => 'Updated', 'type' => 'open',
        ]), $task->id);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Updated', $task->fresh()->title);
    }

    // ══ "My Tasks" — a person sees and completes only their own ═══════════

    /** THE CORE PRIVACY BUG. One person must never be able to complete someone else's task. */
    public function test_a_user_cannot_complete_someone_elses_task(): void
    {
        $task = Task::create([
            'title' => 'Not Yours', 'type' => 'open', 'user_id' => $this->reportB->id,
            'assigned_by_id' => $this->managerB->id, 'for_date' => now(), 'status' => 'new',
        ]);

        Auth::login($this->reportA);
        $request = Request::create('/my-tasks/' . $task->id . '/complete', 'POST', ['remarks' => 'sneaky']);
        $request->setLaravelSession(app('session.store'));

        try {
            $this->myTasks->complete($request, $task);
            $this->fail('completed a task that did not belong to the caller');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        $this->assertEquals('new', $task->fresh()->status);
    }

    /** The discriminator: a person CAN complete their own task, with a remark recorded. */
    public function test_a_user_can_complete_their_own_task_with_a_remark(): void
    {
        $task = Task::create([
            'title' => 'Mine To Finish', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
        ]);

        Auth::login($this->reportA);
        $request = Request::create('/my-tasks/' . $task->id . '/complete', 'POST', ['remarks' => 'Done, filed the report.']);
        $request->setLaravelSession(app('session.store'));
        $response = $this->myTasks->complete($request, $task);

        $this->assertEquals(200, $response->getStatusCode());
        $fresh = $task->fresh();
        $this->assertEquals('completed', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertEquals('Done, filed the report.', $fresh->taskUpdates()->latest()->first()->comment);
    }

    /** "My Tasks" only ever returns the signed-in person's own rows, never a teammate's. */
    public function test_assigned_task_buckets_never_include_another_users_task(): void
    {
        Task::create([
            'title' => 'A-only', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
        ]);
        Task::create([
            'title' => 'B-only', 'type' => 'open', 'user_id' => $this->reportB->id,
            'assigned_by_id' => $this->managerB->id, 'for_date' => now(), 'status' => 'new',
        ]);

        $buckets = MyTasksController::assignedTaskBuckets($this->reportA);
        $allTitles = collect($buckets)->flatten(1)->pluck('title')->all();

        $this->assertContains('A-only', $allTitles);
        $this->assertNotContains('B-only', $allTitles);
    }

    // ══ TaskAccess helper itself ═══════════════════════════════════════════

    public function test_task_access_allowed_ids_is_unrestricted_for_admin(): void
    {
        $this->assertNull(TaskAccess::allowedUserIds($this->admin));
    }

    public function test_task_access_allowed_ids_is_scoped_for_manager(): void
    {
        $ids = TaskAccess::allowedUserIds($this->managerA);
        $this->assertContains($this->reportA->id, $ids);
        $this->assertNotContains($this->reportB->id, $ids);
    }

    // ══ the list defaults to nearest-due-first, with a Due Today filter ════

    /** Reported live: the table needs to lead with what actually needs doing next, not newest-first. */
    public function test_the_list_sorts_by_nearest_due_date_with_undated_tasks_last(): void
    {
        Task::create([
            'title' => 'Far out', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
            'due_date' => now()->addDays(10),
        ]);
        Task::create([
            'title' => 'No due date', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
        ]);
        Task::create([
            'title' => 'Due soonest', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
            'due_date' => now()->addDay(),
        ]);

        Auth::login($this->managerA);
        $response = $this->mgmt->index(Request::create('/task-management', 'GET'));
        $titles = $response->getData()['tasks']->pluck('title')->all();

        $this->assertEquals(['Due soonest', 'Far out', 'No due date'], $titles);
    }

    /** The discriminator: a task due tomorrow must NOT show under "Due today". */
    public function test_due_today_filter_excludes_a_task_due_tomorrow(): void
    {
        Task::create([
            'title' => 'Due today', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
            'due_date' => now(),
        ]);
        Task::create([
            'title' => 'Due tomorrow', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
            'due_date' => now()->addDay(),
        ]);

        Auth::login($this->managerA);
        $response = $this->mgmt->index(Request::create('/task-management', 'GET', ['status' => 'due_today']));
        $titles = $response->getData()['tasks']->pluck('title')->all();

        $this->assertEquals(['Due today'], $titles);
    }

    // ══ the portal itself is admin/HR only — a manager keeps the single-
    //    target "Add Task" modal, but not this bulk portal ═══════════════

    public function test_a_manager_is_blocked_from_the_task_management_route(): void
    {
        $this->actingAs($this->managerA)->get('/task-management')->assertForbidden();
    }

    public function test_admin_and_hr_can_reach_the_task_management_route(): void
    {
        $this->actingAs($this->admin)->get('/task-management')->assertOk();

        $hr = $this->makeUser('hr');
        $this->actingAs($hr)->get('/task-management')->assertOk();
    }

    /** The legacy single-target endpoint is untouched — a manager still assigns via the profile modal. */
    public function test_a_manager_can_still_reach_the_legacy_single_task_store_route(): void
    {
        $this->actingAs($this->managerA)->post('/tasks/store', [
            'userId' => $this->reportA->id, 'title' => 'Via modal', 'type' => 'open',
        ])->assertOk();
    }

    // ══ priority ════════════════════════════════════════════════════════

    public function test_a_chosen_priority_is_saved_on_the_created_task(): void
    {
        Auth::login($this->admin);
        $this->mgmt->store($this->jsonReq([
            'title' => 'QA Priority', 'priority' => 'high', 'employee_ids' => [$this->reportA->id],
        ]));

        $this->assertEquals('high', Task::where('title', 'QA Priority')->first()->priority);
    }

    /** No priority sent still saves something sane rather than a blank/invalid value. */
    public function test_priority_defaults_to_medium_when_not_specified(): void
    {
        Auth::login($this->admin);
        $this->mgmt->store($this->jsonReq([
            'title' => 'QA No Priority', 'employee_ids' => [$this->reportA->id],
        ]));

        $this->assertEquals('medium', Task::where('title', 'QA No Priority')->first()->priority);
    }

    public function test_the_priority_filter_only_returns_matching_tasks(): void
    {
        Task::create([
            'title' => 'Low one', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new', 'priority' => 'low',
        ]);
        Task::create([
            'title' => 'High one', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new', 'priority' => 'high',
        ]);

        Auth::login($this->managerA);
        $response = $this->mgmt->index(Request::create('/task-management', 'GET', ['priority' => 'high']));
        $titles = $response->getData()['tasks']->pluck('title')->all();

        $this->assertEquals(['High one'], $titles);
    }

    // ══ completion timing (early / on time / late) ═════════════════════════

    public function test_completing_on_the_due_date_is_on_time(): void
    {
        $task = Task::create([
            'title' => 'On time', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'completed',
            'due_date' => '2026-08-20', 'completed_at' => '2026-08-20 23:50:00',
        ]);

        $this->assertEquals('on_time', $task->completion_timing);
    }

    public function test_completing_after_the_due_date_is_late(): void
    {
        $task = Task::create([
            'title' => 'Late', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'completed',
            'due_date' => '2026-08-20', 'completed_at' => '2026-08-21 00:05:00',
        ]);

        $this->assertEquals('late', $task->completion_timing);
    }

    public function test_completing_before_the_due_date_is_early(): void
    {
        $task = Task::create([
            'title' => 'Early', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'completed',
            'due_date' => '2026-08-20', 'completed_at' => '2026-08-18 10:00:00',
        ]);

        $this->assertEquals('early', $task->completion_timing);
    }

    /** No due date means there is nothing to judge the completion against. */
    public function test_completion_timing_is_null_without_a_due_date(): void
    {
        $task = Task::create([
            'title' => 'No due date', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->assertNull($task->completion_timing);
    }

    /** A still-open task has no completion timing either — nothing happened yet to judge. */
    public function test_completion_timing_is_null_when_not_yet_completed(): void
    {
        $task = Task::create([
            'title' => 'Still open', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
            'due_date' => now()->addDay(),
        ]);

        $this->assertNull($task->completion_timing);
    }

    // ══ "All" bucket on My Tasks ═══════════════════════════════════════════

    public function test_the_all_bucket_contains_every_task_regardless_of_status(): void
    {
        Task::create([
            'title' => 'Open one', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
        ]);
        Task::create([
            'title' => 'Done one', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'completed',
        ]);

        $buckets = MyTasksController::assignedTaskBuckets($this->reportA);

        $this->assertCount(2, $buckets['all']);
        $this->assertEqualsCanonicalizing(['Open one', 'Done one'], $buckets['all']->pluck('title')->all());
    }

    /** High priority leads, even over a nearer-but-lower-priority due date. */
    public function test_the_all_bucket_sorts_high_priority_before_a_nearer_low_priority_task(): void
    {
        Task::create([
            'title' => 'Low but sooner', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
            'priority' => 'low', 'due_date' => now()->addDay(),
        ]);
        Task::create([
            'title' => 'High but later', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
            'priority' => 'high', 'due_date' => now()->addDays(5),
        ]);

        $buckets = MyTasksController::assignedTaskBuckets($this->reportA);

        $this->assertEquals(['High but later', 'Low but sooner'], $buckets['all']->pluck('title')->all());
    }
}
