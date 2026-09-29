<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\KpiController;
use App\Models\Department;
use App\Models\KpiAssignment;
use App\Models\KpiTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The admin surface. Two things matter here and neither is about scoring:
 * who is allowed in, and whether a half-configured KPI can be saved. A KPI
 * saved with a scoring type it has no settings for is a silent zero for a
 * whole department, so the guards are as important as the maths.
 */
class KpiTemplateControllerTest extends TestCase
{
    use DatabaseTransactions;

    private Department $department;
    private KpiController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->controller = app(KpiController::class);
        $this->department = Department::create([
            'name' => 'ZZ QA Dept ' . uniqid(),
            'code' => 'ZZQ' . random_int(1000, 9999),
        ]);
    }

    private function userWithRole(?string $role): User
    {
        $user = User::create([
            'first_name' => 'QA', 'last_name' => 'Tester',
            'email'      => 'qa' . uniqid() . '@example.test',
            'code'       => 'QA' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
            'department_id' => $this->department->id,
        ]);

        if ($role && ($model = Role::where('name', $role)->first())) {
            $user->assignRole($model);
        }

        return $user;
    }

    /** A valid create payload; override to make it invalid on purpose. */
    private function payload(array $overrides = []): Request
    {
        $request = Request::create('/kpi/templates', 'POST', array_merge([
            'title'         => 'ZZ QA KPI',
            'category'      => 'KPI',
            'department_id' => $this->department->id,
            'frequency'     => 'monthly',
            'scoring_type'  => 'target',
            'max_marks'     => 10,
            'target_value'  => 100,
            'late_policy'   => 'fixed',
            'late_score'    => 0,
        ], $overrides));
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    // ── authorisation ────────────────────────────────────────────────────

    public function test_an_admin_can_open_the_setup_screen(): void
    {
        Auth::login($this->userWithRole('admin'));

        $response = $this->controller->templates($this->payload());

        $this->assertNotInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    /**
     * REGRESSION. The sidebar has always offered this to HR, but the route was
     * guarded by a permission that was never created, so the check collapsed to
     * admin-only and HR got a 403 on a link they could see.
     */
    public function test_hr_can_open_the_setup_screen(): void
    {
        Auth::login($this->userWithRole('hr'));

        $response = $this->controller->templates($this->payload());

        $this->assertNotInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    public function test_an_ordinary_employee_is_turned_away(): void
    {
        Auth::login($this->userWithRole(null));

        $response = $this->controller->templates($this->payload());

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    public function test_an_ordinary_employee_cannot_create_a_template(): void
    {
        Auth::login($this->userWithRole(null));

        $response = $this->controller->storeTemplate($this->payload());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(0, KpiTemplate::where('title', 'ZZ QA KPI')->count());
    }

    /**
     * The route guard is the only thing standing between HR and a 403. Assert
     * the middleware string itself so a future edit cannot quietly undo it.
     */
    public function test_the_kpi_routes_are_open_to_admin_and_hr(): void
    {
        $middleware = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'tenant.kpi.templates')
            ?->gatherMiddleware() ?? [];

        $guard = collect($middleware)->first(fn ($m) => is_string($m) && str_contains($m, 'role_or_permission'));

        $this->assertNotNull($guard, 'the setup screen must stay behind a role guard');
        $this->assertStringContainsString('admin', $guard);
        $this->assertStringContainsString('hr', $guard);
    }

    // ── validation ───────────────────────────────────────────────────────

    public function test_a_threshold_kpi_without_a_gate_is_rejected(): void
    {
        Auth::login($this->userWithRole('admin'));

        $response = $this->controller->storeTemplate($this->payload([
            'scoring_type' => 'threshold', 'threshold_percent' => null,
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('%', json_decode($response->getContent(), true)['message']);
    }

    public function test_a_banded_kpi_without_bands_is_rejected(): void
    {
        Auth::login($this->userWithRole('admin'));

        $response = $this->controller->storeTemplate($this->payload(['scoring_type' => 'banded']));

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_a_percent_late_policy_without_a_percentage_is_rejected(): void
    {
        Auth::login($this->userWithRole('admin'));

        $response = $this->controller->storeTemplate($this->payload([
            'late_policy' => 'percent', 'late_percent' => null,
        ]));

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_a_per_day_late_policy_without_a_daily_penalty_is_rejected(): void
    {
        Auth::login($this->userWithRole('admin'));

        $response = $this->controller->storeTemplate($this->payload([
            'late_policy' => 'per_day', 'per_day_penalty' => null,
        ]));

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_a_missing_title_is_rejected_by_validation(): void
    {
        Auth::login($this->userWithRole('admin'));

        $this->expectException(ValidationException::class);
        $this->controller->storeTemplate($this->payload(['title' => '']));
    }

    // ── behaviour ────────────────────────────────────────────────────────

    public function test_a_kra_is_forced_to_zero_marks_and_no_parent(): void
    {
        Auth::login($this->userWithRole('admin'));
        $parent = KpiTemplate::create([
            'title' => 'ZZ Parent', 'category' => 'KRA', 'frequency' => 'monthly',
            'scoring_type' => 'target', 'max_marks' => 0, 'is_active' => true,
        ]);

        $response = $this->controller->storeTemplate($this->payload([
            'category' => 'KRA', 'max_marks' => 99, 'parent_template_id' => $parent->id,
        ]));

        $created = KpiTemplate::find(json_decode($response->getContent(), true)['id']);
        $this->assertEquals(0.0, $created->max_marks, 'a heading cannot carry marks');
        $this->assertNull($created->parent_template_id, 'a heading cannot nest under another heading');
    }

    public function test_applying_a_kra_is_refused_with_an_explanation(): void
    {
        Auth::login($this->userWithRole('admin'));
        $kra = KpiTemplate::create([
            'title' => 'ZZ Heading', 'category' => 'KRA', 'department_id' => $this->department->id,
            'frequency' => 'monthly', 'scoring_type' => 'target', 'max_marks' => 0, 'is_active' => true,
        ]);

        $response = $this->controller->applyTemplate($this->payload(), $kra->id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('heading', json_decode($response->getContent(), true)['message']);
    }

    public function test_editing_a_template_refreshes_the_assignments_already_issued(): void
    {
        Auth::login($this->userWithRole('admin'));
        $employee = $this->userWithRole(null);

        $created = KpiTemplate::find(json_decode(
            $this->controller->storeTemplate($this->payload())->getContent(), true
        )['id']);
        $this->controller->applyTemplate($this->payload(), $created->id);

        $this->controller->updateTemplate($this->payload(['max_marks' => 40]), $created->id);

        $assignment = KpiAssignment::where('kpi_template_id', $created->id)
            ->where('user_id', $employee->id)->first();
        $this->assertNotNull($assignment);
        $this->assertEquals(40.0, $assignment->max_marks);
    }

    /**
     * REGRESSION. Calls Discipline was changed from 100 to 48 hours in the KPI
     * section, and 17 of 18 people went on being scored against 100: the target
     * is seeded once, and the edit never reached anyone already holding the KPI.
     * Everyone still on the old target now follows; a personalised target stays.
     */
    public function test_changing_the_target_moves_everyone_still_on_the_old_one_and_rescores_them(): void
    {
        Auth::login($this->userWithRole('admin'));
        $onOld = $this->userWithRole(null);
        $personal = $this->userWithRole(null);

        $created = KpiTemplate::find(json_decode(
            $this->controller->storeTemplate($this->payload(['target_value' => 100]))->getContent(), true
        )['id']);
        $this->controller->applyTemplate($this->payload(), $created->id);
        KpiAssignment::where('kpi_template_id', $created->id)->where('user_id', $personal->id)->update(['target_value' => 13]);

        // 24 hours reported this month by the person on the old target.
        $a = KpiAssignment::where('kpi_template_id', $created->id)->where('user_id', $onOld->id)->first();
        \App\Services\KpiEntryService::fillForTeam($a, 24, auth()->id());
        $this->assertEquals(2.4, (float) \App\Models\KpiCheck::where('kpi_assignment_id', $a->id)->latest('id')->value('score'), '24 of 100 = 2.4 of 10');

        $response = $this->controller->updateTemplate($this->payload(['target_value' => 48]), $created->id);

        $this->assertEquals(48.0, (float) $a->fresh()->target_value, 'still on the old target, so it follows the KPI section');
        $this->assertEquals(13.0, (float) KpiAssignment::where('kpi_template_id', $created->id)->where('user_id', $personal->id)->value('target_value'),
            'a personalised target is kept');
        $this->assertEquals(5.0, (float) \App\Models\KpiCheck::where('kpi_assignment_id', $a->id)->latest('id')->value('score'), 'rescored: 24 of 48 = 5 of 10');
        $this->assertStringContainsString('moved to the new target', json_decode($response->getContent(), true)['message']);
    }

    /**
     * Retiring must never delete: kpi_checks hold scores people have already
     * been measured against, and appraisal history cannot be rewritten.
     */
    public function test_retiring_a_template_deactivates_assignments_but_keeps_them(): void
    {
        Auth::login($this->userWithRole('admin'));
        $this->userWithRole(null);

        $created = KpiTemplate::find(json_decode(
            $this->controller->storeTemplate($this->payload())->getContent(), true
        )['id']);
        $this->controller->applyTemplate($this->payload(), $created->id);

        $this->controller->destroyTemplate($created->id);

        $this->assertFalse((bool) $created->fresh()->is_active);
        $this->assertGreaterThan(0, KpiAssignment::where('kpi_template_id', $created->id)->count());
        $this->assertSame(0, KpiAssignment::where('kpi_template_id', $created->id)
            ->where('is_active', true)->count());
    }

    /**
     * THE REPORTED BUG. Retiring a template deactivates it correctly (the
     * test above), but templates() never filtered by is_active, so a
     * "deleted" template stayed on the list looking exactly like an active
     * one — clicking Delete appeared to do nothing.
     */
    public function test_a_retired_template_no_longer_appears_on_the_templates_page(): void
    {
        Auth::login($this->userWithRole('admin'));

        $created = KpiTemplate::find(json_decode(
            $this->controller->storeTemplate($this->payload())->getContent(), true
        )['id']);
        $this->controller->destroyTemplate($created->id);

        $response = $this->controller->templates(Request::create('/kpi/templates', 'GET'));
        $titles = $response->getData()['templates']->pluck('id')->all();

        $this->assertNotContains($created->id, $titles);
    }

    /** The discriminator: a template that was NOT retired still shows up as normal. */
    public function test_an_active_template_still_appears_on_the_templates_page(): void
    {
        Auth::login($this->userWithRole('admin'));

        $created = KpiTemplate::find(json_decode(
            $this->controller->storeTemplate($this->payload())->getContent(), true
        )['id']);

        $response = $this->controller->templates(Request::create('/kpi/templates', 'GET'));
        $ids = $response->getData()['templates']->pluck('id')->all();

        $this->assertContains($created->id, $ids);
    }
}
