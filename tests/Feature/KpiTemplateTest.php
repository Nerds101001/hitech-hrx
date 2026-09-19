<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\KpiAssignment;
use App\Models\KpiTemplate;
use App\Models\User;
use App\Services\KpiTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The template layer: one KPI defined for a department, stamped onto everyone
 * in it. The risk here is silent and plural — a bug does not break one record,
 * it mis-scores a whole department, and nobody notices until appraisals.
 *
 * DatabaseTransactions, never RefreshDatabase: the local database holds real
 * imported production data and must survive a test run.
 */
class KpiTemplateTest extends TestCase
{
    use DatabaseTransactions;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        // Nothing in this module should ever email anyone.
        Mail::fake();

        $this->department = Department::create([
            'name' => 'ZZ QA Department ' . uniqid(),
            'code' => 'ZZQA' . random_int(1000, 9999),
        ]);
    }

    private function employee(array $overrides = []): User
    {
        static $n = 0;
        $n++;
        return User::create(array_merge([
            'first_name'    => 'QA',
            'last_name'     => "Employee {$n}",
            'email'         => 'qa' . uniqid() . '@example.test',
            'code'          => 'QA' . random_int(100000, 999999),
            'password'      => Hash::make('secret-not-used'),
            'status'        => 'active',
            'department_id' => $this->department->id,
        ], $overrides));
    }

    /** The smallest template a careless admin could save. */
    private function template(array $overrides = []): KpiTemplate
    {
        return KpiTemplate::create(array_merge([
            'title'         => 'ZZ QA KPI',
            'category'      => 'KPI',
            'department_id' => $this->department->id,
            'frequency'     => 'monthly',
            'scoring_type'  => 'target',
            'max_marks'     => 10,
            'target_value'  => 100,
            'is_active'     => true,
        ], $overrides));
    }

    private function designation(string $name): Designation
    {
        return Designation::create(['name' => $name . ' ' . uniqid(), 'code' => 'ZZ' . random_int(10000, 99999)]);
    }

    public function test_a_template_restricted_to_designations_only_reaches_them(): void
    {
        $manager = $this->designation('ZZ Manager');
        $gm      = $this->designation('ZZ General Manager');
        $staff   = $this->designation('ZZ Operator');
        // People first, template after: apply() is what is under test here,
        // not the joiner hook (covered separately below).
        $a = $this->employee(['designation_id' => $manager->id]);
        $b = $this->employee(['designation_id' => $gm->id]);
        $c = $this->employee(['designation_id' => $staff->id]);
        $d = $this->employee(['designation_id' => null]);
        $template = $this->template(['designation_ids' => [$manager->id]]);

        $res = KpiTemplateService::apply($template);

        $this->assertSame(1, KpiAssignment::where('kpi_template_id', $template->id)->count());
        $this->assertSame(1, KpiAssignment::where('kpi_template_id', $template->id)->where('user_id', $a->id)->count());
        foreach ([$b, $c, $d] as $excluded) {
            $this->assertSame(0, KpiAssignment::where('user_id', $excluded->id)->count(), 'outside the designation list');
        }
        $this->assertSame(1, $res['created']);
    }

    public function test_the_joiner_hook_respects_the_designation_list(): void
    {
        $manager = $this->designation('ZZ Manager');
        $gm      = $this->designation('ZZ General Manager');
        $this->template(['designation_ids' => [$manager->id]]);

        $newManager = $this->employee(['designation_id' => $manager->id]);
        $newGm      = $this->employee(['designation_id' => $gm->id]);

        $this->assertSame(1, KpiAssignment::where('user_id', $newManager->id)->count());
        $this->assertSame(0, KpiAssignment::where('user_id', $newGm->id)->count(), 'the General Manager must not get it');
    }

    public function test_being_promoted_into_a_listed_designation_assigns_it(): void
    {
        $manager = $this->designation('ZZ Manager');
        $staff   = $this->designation('ZZ Operator');
        $this->template(['designation_ids' => [$manager->id]]);

        $person = $this->employee(['designation_id' => $staff->id]);
        $this->assertSame(0, KpiAssignment::where('user_id', $person->id)->count());

        $person->update(['designation_id' => $manager->id]);

        $this->assertSame(1, KpiAssignment::where('user_id', $person->id)->count());
    }

    public function test_a_template_with_no_designation_list_still_reaches_the_whole_department(): void
    {
        $this->employee(['designation_id' => $this->designation('ZZ Manager')->id]);
        $this->employee(['designation_id' => null]);
        $template = $this->template();

        $this->assertSame(2, KpiTemplateService::apply($template)['created']);
    }

    public function test_apply_creates_one_assignment_per_active_employee_in_the_department(): void
    {
        $this->employee();
        $this->employee();
        $this->employee(['status' => 'terminated']);   // must be skipped

        $result = KpiTemplateService::apply($this->template());

        $this->assertSame(2, $result['created'], 'terminated employees must not receive KPIs');
    }

    public function test_employees_in_other_departments_are_not_touched(): void
    {
        $this->employee();
        $other = Department::create(['name' => 'ZZ Other ' . uniqid(), 'code' => 'ZZO' . random_int(1000, 9999)]);
        $this->employee(['department_id' => $other->id]);

        $result = KpiTemplateService::apply($this->template());

        $this->assertSame(1, $result['created']);
    }

    /**
     * REGRESSION. The first version wrote the assignment straight from the
     * template, but a freshly created template holds null for any column the
     * caller omitted — the default lives in the database, not the model — so
     * every insert died on NOT NULL columns like late_score.
     */
    public function test_a_minimally_configured_template_still_produces_valid_assignments(): void
    {
        $this->employee();

        // No late_score, no grace_days, no data_source: all null in memory.
        $result = KpiTemplateService::apply($this->template());

        $this->assertSame(1, $result['created']);
        $assignment = KpiAssignment::where('kpi_template_id', '!=', null)->latest('id')->first();
        $this->assertNotNull($assignment->late_score);
        $this->assertNotNull($assignment->grace_days);
        $this->assertNotNull($assignment->data_source);
    }

    /** REGRESSION. Pressing Apply twice must not give anyone the KPI twice. */
    public function test_apply_is_idempotent(): void
    {
        $this->employee();
        $this->employee();
        $template = $this->template();

        KpiTemplateService::apply($template);
        $second = KpiTemplateService::apply($template);

        $this->assertSame(0, $second['created'], 're-applying must not duplicate');
        $this->assertSame(2, KpiAssignment::where('kpi_template_id', $template->id)->count());
    }

    /**
     * REGRESSION. $refreshExisting was not passed into the transaction closure,
     * so editing a template reported success while every existing assignment
     * kept the old weight — half a department scored on stale numbers.
     */
    public function test_editing_a_template_propagates_to_assignments_already_issued(): void
    {
        $this->employee();
        $this->employee();
        $template = $this->template(['max_marks' => 10, 'target_value' => 100]);
        KpiTemplateService::apply($template);

        $template->update(['max_marks' => 25, 'target_value' => 200]);
        $result = KpiTemplateService::apply($template);

        $this->assertSame(2, $result['updated']);
        $this->assertSame(0, $result['created']);
        foreach (KpiAssignment::where('kpi_template_id', $template->id)->get() as $assignment) {
            $this->assertEquals(25.0,  $assignment->max_marks);
            // A target is individual: it is seeded from the template once and
            // then belongs to the person (KpiTemplate::SEEDED_ONCE), so editing
            // the template must NOT drag it to the new shared number.
            $this->assertEquals(100.0, $assignment->target_value);
        }
    }

    /**
     * REGRESSION. A KRA is a heading worth zero marks. Assigning it put an
     * unscoreable row on every scorecard.
     */
    public function test_a_kra_heading_never_becomes_an_assignment(): void
    {
        $this->employee();

        $result = KpiTemplateService::apply($this->template(['category' => 'KRA', 'max_marks' => 0]));

        $this->assertSame(0, $result['created']);
    }

    public function test_an_inactive_template_assigns_nothing(): void
    {
        $this->employee();

        $result = KpiTemplateService::apply($this->template(['is_active' => false]));

        $this->assertSame(0, $result['created']);
    }

    public function test_a_kpi_inherits_the_name_of_the_kra_it_sits_under(): void
    {
        $this->employee();
        $kra = $this->template(['title' => 'ZZ Revenue Area', 'category' => 'KRA', 'max_marks' => 0]);
        $kpi = $this->template(['title' => 'ZZ Revenue', 'parent_template_id' => $kra->id]);

        KpiTemplateService::apply($kpi);

        $assignment = KpiAssignment::where('kpi_template_id', $kpi->id)->first();
        $this->assertSame('ZZ Revenue Area', $assignment->kra_group);
    }

    /**
     * Someone who leaves the department stops being measured, but their past
     * scores are evidence used in appraisals and must survive.
     */
    public function test_leaving_the_department_deactivates_rather_than_deletes(): void
    {
        $employee = $this->employee();
        $template = $this->template();
        KpiTemplateService::apply($template);

        $employee->update(['department_id' => null]);
        $pruned = KpiTemplateService::prune($template);

        $this->assertSame(1, $pruned);
        $assignment = KpiAssignment::where('kpi_template_id', $template->id)
            ->where('user_id', $employee->id)->first();
        $this->assertNotNull($assignment, 'the row must still exist so its scores survive');
        $this->assertFalse((bool) $assignment->is_active);
    }

    public function test_a_new_joiner_receives_every_template_covering_their_department(): void
    {
        $this->template(['title' => 'ZZ KPI One']);
        $this->template(['title' => 'ZZ KPI Two']);
        $this->template(['title' => 'ZZ Heading', 'category' => 'KRA', 'max_marks' => 0]);

        // Creating an active employee in the department is enough: User's
        // saved() hook hands them the department's templates on the spot.
        $joiner = $this->employee();

        $this->assertSame(2, KpiAssignment::where('user_id', $joiner->id)->count(), 'the KRA heading must not be assigned');
        $this->assertSame(0, KpiTemplateService::applyAllForUser($joiner), 'nothing left to give — the hook already did');
    }

    public function test_moving_into_a_department_assigns_its_templates(): void
    {
        $this->template(['title' => 'ZZ KPI One']);
        $this->template(['title' => 'ZZ KPI Two']);

        // Started somewhere with no KPIs, transferred in later — the case that
        // left Rabina Parkar (New Biz) with none while her peers had 13.
        $person = $this->employee(['department_id' => null]);
        $this->assertSame(0, KpiAssignment::where('user_id', $person->id)->count());

        $person->update(['department_id' => $this->department->id]);

        $this->assertSame(2, KpiAssignment::where('user_id', $person->id)->count());
    }

    public function test_an_unrelated_save_does_not_reassign_or_duplicate(): void
    {
        $this->template();
        $person = $this->employee();
        $before = KpiAssignment::where('user_id', $person->id)->count();

        $person->update(['first_name' => 'Renamed']);
        $person->update(['first_name' => 'Renamed Again']);

        $this->assertSame($before, KpiAssignment::where('user_id', $person->id)->count());
    }

    public function test_someone_not_yet_active_is_not_assigned_until_they_are(): void
    {
        $this->template();
        $person = $this->employee(['status' => 'onboarding']);

        $this->assertSame(0, KpiAssignment::where('user_id', $person->id)->count());
    }

    public function test_applying_for_a_user_twice_does_not_duplicate(): void
    {
        $this->template();
        $joiner = $this->employee();

        KpiTemplateService::applyAllForUser($joiner);
        $second = KpiTemplateService::applyAllForUser($joiner);

        $this->assertSame(0, $second);
    }

    /** A template with no department is a company-wide KPI. */
    public function test_a_template_without_a_department_covers_employees_of_any_department(): void
    {
        $this->employee();
        $other = Department::create(['name' => 'ZZ Other ' . uniqid(), 'code' => 'ZZO' . random_int(1000, 9999)]);
        $mine = $this->employee(['department_id' => $other->id]);

        $template = $this->template(['department_id' => null]);
        KpiTemplateService::apply($template);

        $this->assertTrue(
            KpiAssignment::where('kpi_template_id', $template->id)->where('user_id', $mine->id)->exists(),
            'a company-wide template must reach every department'
        );
    }
}
