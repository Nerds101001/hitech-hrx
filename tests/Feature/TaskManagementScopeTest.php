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

    // ══ who can be assigned ═══════════════════════════════════════════════
    // Task Management was opened to every signed-in user: anyone can assign a
    // task to anyone (TaskManagementController::store - "No reach
    // restriction"). What each person SEES stays scoped (scopeVisible()).
    // These tests used to assert the earlier manager-team-only rule.

    /** A manager assigning to a whole department reaches everyone in it, not just their own reports. */
    public function test_bulk_assign_by_department_reaches_everyone_in_it_even_for_a_manager(): void
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
        $this->assertEquals(2, $data['assigned'], 'everyone in the department');
        $this->assertTrue(Task::where('user_id', $this->reportA->id)->where('title', 'QA Bulk Task')->exists());
        $this->assertTrue(Task::where('user_id', $this->reportB->id)->where('title', 'QA Bulk Task')->exists(), 'another manager\'s report is assigned too');
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

    /** Hand-picking someone outside your team assigns them, the same as a department pick. */
    public function test_bulk_assign_by_employee_id_outside_team_is_assigned(): void
    {
        Auth::login($this->managerA);
        $response = $this->mgmt->store($this->jsonReq([
            'title' => 'QA Direct Pick',
            'employee_ids' => [$this->reportB->id],
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertTrue(Task::where('user_id', $this->reportB->id)->where('title', 'QA Direct Pick')->exists());
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

    // ══ the portal is open to every signed-in user (it used to be admin/HR
    //    only); what each person sees inside it is scoped ════════════════

    public function test_a_manager_can_reach_the_task_management_route(): void
    {
        $this->actingAs($this->managerA)->get('/task-management')->assertOk();
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

    // ══ Edit ═══════════════════════════════════════════════════════════

    /** Same ownership rule update() already had - edit() must honour it too. */
    public function test_a_manager_cannot_edit_a_task_belonging_to_another_managers_report(): void
    {
        $task = Task::create([
            'title' => 'Original', 'type' => 'open', 'user_id' => $this->reportB->id,
            'assigned_by_id' => $this->managerB->id, 'for_date' => now(), 'status' => 'new',
        ]);

        Auth::login($this->managerA);
        try {
            $this->mgmt->update(Request::create('/task-management/' . $task->id, 'PUT', [
                'title' => 'Hijacked',
            ]), $task->id);
            $this->fail('edited a task outside the managers team');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
        $this->assertEquals('Original', $task->fresh()->title);
    }

    public function test_editing_a_task_updates_title_due_date_and_priority(): void
    {
        $task = Task::create([
            'title' => 'Before', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
            'priority' => 'low',
        ]);

        Auth::login($this->admin);
        $due = now()->addDays(3)->format('Y-m-d H:i:s');
        $response = $this->mgmt->update(Request::create('/task-management/' . $task->id, 'PUT', [
            'title' => 'After', 'priority' => 'high', 'due_date' => $due,
        ]), $task->id);

        $this->assertEquals(200, $response->getStatusCode());
        $fresh = $task->fresh();
        $this->assertEquals('After', $fresh->title);
        $this->assertEquals('high', $fresh->priority);
        $this->assertEquals($due, $fresh->due_date->format('Y-m-d H:i:s'));
    }

    // ══ Objection / resubmit ═══════════════════════════════════════════

    /** THE CORE OBJECTION FLOW: admin sends a completed task back with a reason. */
    public function test_raising_an_objection_moves_the_task_to_objection_status_with_a_reason(): void
    {
        $task = Task::create([
            'title' => 'Needs Rework', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'completed',
            'completed_at' => now(),
        ]);

        Auth::login($this->admin);
        $response = $this->mgmt->objection(Request::create('/task-management/' . $task->id . '/objection', 'POST', [
            'reason' => 'The attached report is missing Q3 numbers.',
        ]), $task->id);

        $this->assertEquals(200, $response->getStatusCode());
        $fresh = $task->fresh();
        $this->assertEquals('objection', $fresh->status);
        $this->assertEquals('The attached report is missing Q3 numbers.', $fresh->objection_notes);
        $this->assertEquals($this->admin->id, $fresh->objected_by_id);
        $this->assertNotNull($fresh->objected_at);
        $this->assertEquals('objection', $fresh->taskUpdates()->latest()->first()->update_type);
    }

    /** A manager cannot raise an objection on another manager's report's task - same TaskAccess gate as everything else. */
    public function test_a_manager_cannot_raise_an_objection_outside_their_team(): void
    {
        $task = Task::create([
            'title' => 'Not Mine To Object To', 'type' => 'open', 'user_id' => $this->reportB->id,
            'assigned_by_id' => $this->managerB->id, 'for_date' => now(), 'status' => 'completed',
        ]);

        Auth::login($this->managerA);
        try {
            $this->mgmt->objection(Request::create('/task-management/' . $task->id . '/objection', 'POST', [
                'reason' => 'Trying to interfere.',
            ]), $task->id);
            $this->fail('raised an objection on a task outside the managers team');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
        $this->assertEquals('completed', $task->fresh()->status);
    }

    /** The only way out of 'objection' - resubmitting goes straight back to completed, no separate Mark Complete needed. */
    public function test_resubmitting_an_objected_task_marks_it_completed_again(): void
    {
        $task = Task::create([
            'title' => 'Reworked', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'objection',
            'objection_notes' => 'Fix the numbers.', 'objected_by_id' => $this->admin->id, 'objected_at' => now(),
        ]);

        Auth::login($this->reportA);
        $request = Request::create('/my-tasks/' . $task->id . '/resubmit', 'POST', ['remarks' => 'Fixed, please recheck.']);
        $request->setLaravelSession(app('session.store'));
        $response = $this->myTasks->resubmit($request, $task);

        $this->assertEquals(200, $response->getStatusCode());
        $fresh = $task->fresh();
        $this->assertEquals('completed', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertEquals('resubmit', $fresh->taskUpdates()->latest()->first()->update_type);
    }

    /** Ownership check mirrors complete() - one person can never resubmit someone else's objected task. */
    public function test_a_user_cannot_resubmit_someone_elses_objected_task(): void
    {
        $task = Task::create([
            'title' => 'Not Yours Either', 'type' => 'open', 'user_id' => $this->reportB->id,
            'assigned_by_id' => $this->managerB->id, 'for_date' => now(), 'status' => 'objection',
            'objection_notes' => 'x',
        ]);

        Auth::login($this->reportA);
        $request = Request::create('/my-tasks/' . $task->id . '/resubmit', 'POST', []);
        $request->setLaravelSession(app('session.store'));

        try {
            $this->myTasks->resubmit($request, $task);
            $this->fail('resubmitted a task that did not belong to the caller');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
        $this->assertEquals('objection', $task->fresh()->status);
    }

    /** resubmit() only means anything from 'objection' - guard against calling it on an ordinary task. */
    public function test_resubmit_rejects_a_task_that_has_no_objection(): void
    {
        $task = Task::create([
            'title' => 'Never Objected', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
        ]);

        Auth::login($this->reportA);
        $request = Request::create('/my-tasks/' . $task->id . '/resubmit', 'POST', []);
        $request->setLaravelSession(app('session.store'));

        try {
            $this->myTasks->resubmit($request, $task);
            $this->fail('resubmitted a task with no objection to resubmit against');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }
        $this->assertEquals('new', $task->fresh()->status);
    }

    // ══ Bulk delete ════════════════════════════════════════════════════

    /** Deletes only what the caller is allowed to manage, silently skipping the rest - same as single destroy(). */
    public function test_bulk_destroy_only_deletes_tasks_the_caller_can_manage(): void
    {
        $mine = Task::create([
            'title' => 'Mine To Delete', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
        ]);
        $notMine = Task::create([
            'title' => 'Not Mine To Delete', 'type' => 'open', 'user_id' => $this->reportB->id,
            'assigned_by_id' => $this->managerB->id, 'for_date' => now(), 'status' => 'new',
        ]);

        Auth::login($this->managerA);
        $response = $this->mgmt->bulkDestroy($this->jsonReq2(['ids' => [$mine->id, $notMine->id]]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['deleted']);
        $this->assertNull(Task::find($mine->id), 'the allowed task should have been deleted');
        $this->assertNotNull(Task::find($notMine->id), 'a task outside the managers team was deleted');
    }

    public function test_admin_bulk_destroy_deletes_everything_selected(): void
    {
        $a = Task::create([
            'title' => 'A', 'type' => 'open', 'user_id' => $this->reportA->id,
            'assigned_by_id' => $this->managerA->id, 'for_date' => now(), 'status' => 'new',
        ]);
        $b = Task::create([
            'title' => 'B', 'type' => 'open', 'user_id' => $this->reportB->id,
            'assigned_by_id' => $this->managerB->id, 'for_date' => now(), 'status' => 'new',
        ]);

        Auth::login($this->admin);
        $response = $this->mgmt->bulkDestroy($this->jsonReq2(['ids' => [$a->id, $b->id]]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['deleted']);
        $this->assertNull(Task::find($a->id));
        $this->assertNull(Task::find($b->id));
    }

    /** jsonReq() above hardcodes the '/task-management' POST path used by store()'s tests - bulk-destroy needs its own path, same JSON-body shape. */
    private function jsonReq2(array $payload): Request
    {
        $r = Request::create('/task-management/bulk-destroy', 'POST', [], [], [], [], json_encode($payload));
        $r->headers->set('Content-Type', 'application/json');
        $r->setLaravelSession(app('session.store'));
        return $r;
    }
}
