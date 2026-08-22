<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\SalesPipelineController;
use App\Models\SalesPipeline;
use App\Models\User;
use App\Support\SalesScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The My Data / Team switch on the Sales Pipeline.
 *
 * Reported live: a manager with 20 direct reports saw 1,457 prospects with
 * "My Data" selected, instead of the 136 she is personally tagged on. The
 * cause was that the pipeline scopes rows through personalIds(), which merged
 * every direct report unconditionally and never looked at the request — so the
 * switch was decorative. viewIds() already behaved correctly; personalIds()
 * simply never got the same treatment.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds real
 * imported production data.
 */
class SalesScopeManagerViewTest extends TestCase
{
    use DatabaseTransactions;

    private User $manager;
    private User $report;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->manager  = $this->makeUser('manager');
        $this->report   = $this->makeUser(null, $this->manager->id);
        $this->outsider = $this->makeUser();
    }

    private function makeUser(?string $role = null, ?int $reportsTo = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Scope',
            'email'      => 'sc' . uniqid() . '@example.test',
            'code'       => 'SC' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
        // reporting_to_id is not mass-assignable on User
        if ($reportsTo) {
            $u->forceFill(['reporting_to_id' => $reportsTo])->save();
        }
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u;
    }

    private function row(array $overrides): SalesPipeline
    {
        return SalesPipeline::forceCreate(array_merge([
            'party_name' => 'QA SCOPE ' . uniqid(),
            'is_locked'  => 0,
        ], $overrides));
    }

    /** Row ids the pipeline page would show this user for the given query. */
    private function visibleIds(User $user, array $query): array
    {
        $request = Request::create('/sales-visits/sales-pipeline', 'GET', $query);
        $q = SalesPipeline::where('is_locked', false);
        SalesScope::applyScope($q, $user, $request, [
            'sp' => 'salesperson_id', 'rep' => ['ccare_id', 'new_biz_id'],
            'member' => 'sp', 'extra' => true, 'personal' => true,
        ]);
        return $q->pluck('sales_pipelines.id')->all();
    }

    /**
     * REGRESSION — the reported bug. "My Data" must mean the manager's own
     * rows, not the whole team's book.
     */
    public function test_my_data_shows_only_the_managers_own_rows(): void
    {
        $mine   = $this->row(['new_biz_id' => $this->manager->id]);
        $theirs = $this->row(['salesperson_id' => $this->report->id]);

        $ids = $this->visibleIds($this->manager, []);

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids, "a direct report's row is not 'My Data'");
    }

    public function test_team_shows_the_managers_own_rows_and_their_reports(): void
    {
        $mine   = $this->row(['new_biz_id' => $this->manager->id]);
        $theirs = $this->row(['salesperson_id' => $this->report->id]);

        $ids = $this->visibleIds($this->manager, ['team' => 1]);

        $this->assertContains($mine->id, $ids);
        $this->assertContains($theirs->id, $ids);
    }

    public function test_choosing_a_teammate_shows_only_that_teammate(): void
    {
        $mine   = $this->row(['new_biz_id' => $this->manager->id]);
        $theirs = $this->row(['salesperson_id' => $this->report->id]);

        $ids = $this->visibleIds($this->manager, ['sp' => $this->report->id]);

        $this->assertContains($theirs->id, $ids);
        $this->assertNotContains($mine->id, $ids);
    }

    /** The switch must not become a way to read somebody else's book. */
    public function test_a_manager_cannot_focus_on_someone_outside_their_team(): void
    {
        $mine     = $this->row(['new_biz_id' => $this->manager->id]);
        $outsider = $this->row(['salesperson_id' => $this->outsider->id]);

        $ids = $this->visibleIds($this->manager, ['sp' => $this->outsider->id]);

        $this->assertNotContains($outsider->id, $ids,
            'picking a non-teammate must fall back to the manager, never expose them');
        $this->assertContains($mine->id, $ids);
    }

    public function test_team_equals_zero_is_treated_as_my_data(): void
    {
        $mine   = $this->row(['new_biz_id' => $this->manager->id]);
        $theirs = $this->row(['salesperson_id' => $this->report->id]);

        $ids = $this->visibleIds($this->manager, ['team' => 0]);

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    /** A non-manager is unaffected by any of this. */
    public function test_a_plain_user_sees_only_their_own_rows_regardless_of_the_flag(): void
    {
        $theirs = $this->row(['salesperson_id' => $this->outsider->id]);
        $others = $this->row(['salesperson_id' => $this->report->id]);

        foreach ([[], ['team' => 1]] as $query) {
            $ids = $this->visibleIds($this->outsider, $query);
            $this->assertContains($theirs->id, $ids);
            $this->assertNotContains($others->id, $ids,
                'the team flag must not widen a non-manager');
        }
    }

    /** Called without a request, the old wider behaviour is preserved. */
    public function test_personal_ids_without_a_request_still_includes_the_team(): void
    {
        $ids = SalesScope::personalIds($this->manager);

        $this->assertContains($this->manager->id, $ids);
        $this->assertContains($this->report->id, $ids);
    }

    // == the Team context must survive using the teammate picker ==========

    /**
     * REGRESSION — the teammate dropdown's form carried only `fy`, so choosing
     * a team member dropped ?team=1 and the page fell back to My Data. It
     * looked like the filter reset itself the moment it was used.
     */
    public function test_the_team_switch_markup_carries_the_team_flag(): void
    {
        $partial = file_get_contents(resource_path('views/tenant/partials/manager-view-switch.blade.php'));

        $this->assertStringContainsString(
            "<input type=\"hidden\" name=\"team\" value=\"1\">",
            $partial,
            'the teammate form must preserve ?team=1'
        );
    }

    /**
     * REGRESSION — the page re-filtered on salesperson_id on top of the scope,
     * so a CCare/New Biz teammate (tagged via ccare_id / new_biz_id, never as
     * salesperson) returned zero rows when picked.
     */
    public function test_picking_a_teammate_finds_rows_they_hold_in_any_role(): void
    {
        $this->row(['salesperson_id' => $this->report->id]);
        $this->row(['ccare_id'       => $this->report->id]);
        $this->row(['new_biz_id'     => $this->report->id]);

        // Through the CONTROLLER, not SalesScope directly: the defect was the
        // page re-filtering on salesperson_id AFTER the scope had already
        // narrowed to the teammate. A scope-level assertion would pass while
        // the page still showed zero.
        $shown = $this->pageCount($this->manager, ['team' => 1, 'sp' => $this->report->id]);

        $this->assertSame(3, $shown,
            'every row belongs to the teammate, whichever role column holds them');
    }

    /** What the Sales Pipeline page itself reports, filters included. */
    private function pageCount(User $user, array $query): int
    {
        \Illuminate\Support\Facades\Auth::login($user);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $r = Request::create('/sales-visits/sales-pipeline', 'GET', $query);
        $r->setLaravelSession(app('session.store'));
        app()->instance('request', $r);
        return (int) app(SalesPipelineController::class)
            ->actualPipelineIndex($r)->getData()['totalNewClients'];
    }

    // == the same defect existed on the Sales Report and Sales Visits ======

    /**
     * REGRESSION — the Sales Report page re-filtered on salesperson_id on top
     * of the scope, exactly as the Pipeline did. On production 16 of Shallu's
     * 17 teammates who hold rows showed ZERO when picked.
     */
    public function test_the_sales_report_shows_a_picked_teammates_rows_in_any_role(): void
    {
        $this->reportRow(['salesperson_id' => $this->report->id]);
        $this->reportRow(['ccare_id'       => $this->report->id]);
        $this->reportRow(['new_biz_id'     => $this->report->id]);

        $shown = $this->reportCount($this->manager, ['team' => 1, 'sp' => $this->report->id]);

        $this->assertSame(3, $shown,
            'a teammate holds these rows as CCare / New Biz, not as salesperson');
    }

    public function test_the_sales_report_my_data_and_team_still_differ(): void
    {
        $this->reportRow(['new_biz_id'     => $this->manager->id]);
        $this->reportRow(['salesperson_id' => $this->report->id]);

        $mine = $this->reportCount($this->manager, []);
        $team = $this->reportCount($this->manager, ['team' => 1]);

        $this->assertLessThan($team, $mine, 'My Data must be narrower than Team');
    }

    /** A Sales Report row (is_locked = 1 is the Report book). */
    private function reportRow(array $overrides): SalesPipeline
    {
        return SalesPipeline::forceCreate(array_merge([
            'party_name' => 'QA REPORT ' . uniqid(),
            'is_locked'  => 1,
        ], $overrides));
    }

    private function reportCount(User $user, array $query): int
    {
        \Illuminate\Support\Facades\Auth::login($user);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $r = Request::create('/sales-visits/pipeline', 'GET', $query);
        $r->setLaravelSession(app('session.store'));
        app()->instance('request', $r);
        return (int) app(SalesPipelineController::class)->index($r)->getData()['pipelines']->total();
    }
}
