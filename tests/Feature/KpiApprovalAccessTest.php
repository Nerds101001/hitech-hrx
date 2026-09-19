<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\KpiController;
use App\Models\Department;
use App\Models\KpiAssignment;
use App\Models\KpiEntry;
use App\Models\KpiTemplate;
use App\Models\User;
use App\Services\KpiEntryService;
use App\Services\KpiTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Who may sign off a manually reported KPI number.
 *
 * The "manager" role is held by dozens of people in every department, and the
 * Approvals page used to hand every one of them the whole company's queue.
 * The rule now: admin/hr review everything; anyone else reviews only the KPIs
 * that name them as approver, and never their own.
 */
class KpiApprovalAccessTest extends TestCase
{
    use DatabaseTransactions;

    private Department $production;
    private Department $newBiz;
    private User $gm;
    private User $randomManager;
    private User $hr;
    private User $admin;
    private KpiEntry $productionEntry;
    private KpiEntry $newBizEntry;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();

        $this->production = Department::create(['name' => 'ZZ Production ' . uniqid(), 'code' => 'ZZP' . random_int(1000, 9999)]);
        $this->newBiz     = Department::create(['name' => 'ZZ NewBiz ' . uniqid(), 'code' => 'ZZN' . random_int(1000, 9999)]);

        $this->gm            = $this->person('manager', $this->production);
        $this->randomManager = $this->person('manager', $this->newBiz);
        $this->hr            = $this->person('hr', $this->newBiz);
        $this->admin         = $this->person('admin', $this->newBiz);

        $prodStaff = $this->person(null, $this->production);
        $nbStaff   = $this->person(null, $this->newBiz);

        // Production names the GM as approver; New Biz names nobody.
        $this->productionEntry = $this->submitted($prodStaff, $this->production, $this->gm->id);
        $this->newBizEntry     = $this->submitted($nbStaff, $this->newBiz, null);
    }

    private function person(?string $role, Department $dept): User
    {
        static $n = 0;
        $n++;
        $u = User::create([
            'first_name' => 'QA', 'last_name' => "Approval {$n}",
            'email' => 'qaap' . uniqid() . '@example.test',
            'code' => 'QAAP' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'),
            'status' => 'active', 'department_id' => $dept->id,
        ]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u;
    }

    private function submitted(User $employee, Department $dept, ?int $approverId): KpiEntry
    {
        $t = KpiTemplate::create([
            'title' => 'ZZ manual KPI', 'category' => 'KPI', 'department_id' => $dept->id,
            'frequency' => 'monthly', 'scoring_type' => 'target', 'max_marks' => 10, 'target_value' => 100,
            'data_source' => 'approval', 'approver_id' => $approverId, 'is_active' => true,
        ]);
        KpiTemplateService::apply($t);
        $a = KpiAssignment::where('kpi_template_id', $t->id)->where('user_id', $employee->id)->firstOrFail();

        return KpiEntryService::submit($a, 50, 'qa');
    }

    private function queueFor(User $reviewer): array
    {
        return KpiEntryService::queue(null, KpiEntry::SUBMITTED, null, $reviewer)->pluck('id')->all();
    }

    private function review(User $reviewer, KpiEntry $entry, string $action = 'approve')
    {
        Auth::setUser($reviewer);
        $req = Request::create('/kpi/entries/' . $entry->id . '/review', 'POST', ['action' => $action]);
        $req->setUserResolver(fn () => $reviewer);

        return app(KpiController::class)->reviewEntry($req, $entry->id);
    }

    public function test_an_ordinary_manager_sees_nothing_and_cannot_approve(): void
    {
        $this->assertSame([], $this->queueFor($this->randomManager));

        foreach ([$this->productionEntry, $this->newBizEntry] as $entry) {
            $res = $this->review($this->randomManager, $entry);
            $this->assertSame(403, $res->getStatusCode());
            $this->assertSame(KpiEntry::SUBMITTED, $entry->fresh()->status, 'the number must not have moved');
        }
    }

    public function test_the_named_approver_sees_and_approves_only_their_own_kpis(): void
    {
        $this->assertSame([$this->productionEntry->id], $this->queueFor($this->gm));

        $this->assertSame(403, $this->review($this->gm, $this->newBizEntry)->getStatusCode());
        $this->assertSame(200, $this->review($this->gm, $this->productionEntry)->getStatusCode());
        $this->assertSame(KpiEntry::APPROVED, $this->productionEntry->fresh()->status);
        $this->assertSame(KpiEntry::SUBMITTED, $this->newBizEntry->fresh()->status);
    }

    public function test_hr_and_admin_review_every_department(): void
    {
        foreach ([$this->hr, $this->admin] as $who) {
            $ids = $this->queueFor($who);
            $this->assertContains($this->productionEntry->id, $ids);
            $this->assertContains($this->newBizEntry->id, $ids);
        }
        $this->assertSame(200, $this->review($this->hr, $this->newBizEntry)->getStatusCode());
        $this->assertSame(200, $this->review($this->admin, $this->productionEntry, 'reject')->getStatusCode());
        $this->assertSame(KpiEntry::REJECTED, $this->productionEntry->fresh()->status);
    }

    public function test_a_named_approver_cannot_approve_their_own_numbers(): void
    {
        $t = KpiTemplate::create([
            'title' => 'ZZ self KPI', 'category' => 'KPI', 'department_id' => $this->production->id,
            'frequency' => 'monthly', 'scoring_type' => 'target', 'max_marks' => 10, 'target_value' => 100,
            'data_source' => 'approval', 'approver_id' => $this->gm->id, 'is_active' => true,
        ]);
        KpiTemplateService::apply($t);
        $mine = KpiAssignment::where('kpi_template_id', $t->id)->where('user_id', $this->gm->id)->first();
        $this->assertNotNull($mine, 'the approver is in the department, so they hold the KPI too');
        $own = KpiEntryService::submit($mine, 10, 'my own');

        $this->assertNotContains($own->id, $this->queueFor($this->gm));
        $this->assertSame(403, $this->review($this->gm, $own)->getStatusCode());
    }

    private function openApprovals(User $who)
    {
        Auth::setUser($who);
        $req = Request::create('/kpi/approvals', 'GET');
        $req->setUserResolver(fn () => $who);
        \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag());

        return app(KpiController::class)->approvals($req);
    }

    public function test_the_page_is_closed_to_managers_with_nothing_to_approve(): void
    {
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $this->openApprovals($this->randomManager));
    }

    public function test_a_named_approvers_screen_shows_only_their_own_numbers(): void
    {
        $data = $this->openApprovals($this->gm)->getData();

        $this->assertCount(1, $data['entries']);
        $this->assertSame(1, (int) collect($data['tabCounts'])->sum(), 'tab counts must not reveal how much is waiting elsewhere');
        $this->assertSame([$this->productionEntry->user_id], $data['employees']->pluck('id')->all(), 'no staff directory beyond what they review');
    }

    public function test_the_menu_link_shows_only_for_people_who_can_review_something(): void
    {
        \App\Support\Nav::flush();
        $this->assertTrue(\App\Support\Nav::allows('/kpi/approvals', $this->admin));
        $this->assertTrue(\App\Support\Nav::allows('/kpi/approvals', $this->hr));
        $this->assertTrue(\App\Support\Nav::allows('/kpi/approvals', $this->gm), 'named approver');
        $this->assertFalse(\App\Support\Nav::allows('/kpi/approvals', $this->randomManager), 'manager role alone is not enough');
    }
}
