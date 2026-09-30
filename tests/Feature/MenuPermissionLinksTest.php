<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Support\Nav;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A box ticked on the Roles page (or as a special permission) has to show the
 * link to the page it already opens. Before this, four sections hid pages the
 * permission let the person into:
 *
 *  - Sales Field Ops was drawn only for the Sales departments, while the Sales
 *    pages themselves (SalesAccessGuard) let in anyone holding one of the
 *    sales_* permissions -- "View Sales Report" given to someone in Finance
 *    opened the page and drew no link.
 *  - Analytics & Reports and Manage Training check their permission in the
 *    controller, not the route, so Nav::grantedByPermission never saw it; the
 *    new rolesOrPermission menu key names it instead.
 *  - Asset Management accepted only assets.view, although "Add New Asset" and
 *    the others open the Asset Manager on their own.
 *  - Recruitment & Onboarding accepted only recruitment.view, hiding Onboarding
 *    Review Center from onboarding.manage and Manage Training from
 *    training.manage.
 *
 * Every check reads the rendered sidebar, and Preview Access must agree.
 */
class MenuPermissionLinksTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
    }

    private function person(string $role, array $permissions = [], string $dept = 'ZZ Finance'): User
    {
        $d = Department::create(['name' => $dept . ' ' . uniqid(), 'code' => 'ZZ' . random_int(10000, 99999)]);
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Links ' . uniqid(),
            'email' => 'qalinks' . uniqid() . '@example.test', 'code' => 'QL' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'), 'status' => 'active', 'department_id' => $d->id,
        ]);
        $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        foreach ($permissions as $p) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    /** The links under $section in this person's real sidebar, or null when the section isn't drawn. */
    private function links(User $u, string $section): ?array
    {
        $this->actingAs($u);
        Nav::flush();
        $html = $this->get('/kpi/my')->getContent();
        Nav::flush();
        $p = strpos($html, '<span class="hitech-menu-text">' . e($section) . '</span>');
        if ($p === false) {
            return null;
        }
        $ul = strpos($html, '<ul class="hitech-submenu">', $p);
        $end = strpos($html, '</ul>', $ul);
        preg_match_all('/class="hitech-submenu-link"[^>]*>\s*([^<]+?)\s*<\/a>/', substr($html, $ul, $end - $ul), $m);

        return array_map(fn ($l) => html_entity_decode(trim($l)), $m[1]);
    }

    /** The same section as Preview Access reports it. */
    private function preview(User $u, string $section): ?array
    {
        Nav::flush();
        $entry = collect(Nav::previewMenuFor($u))->first(fn ($x) => $x['name'] === $section);
        Nav::flush();
        if (!$entry || !$entry['visible']) {
            return null;
        }

        return collect($entry['submenu'] ?? [])->where('visible', true)->pluck('name')->values()->all();
    }

    private function assertSection(?array $expected, User $u, string $section): void
    {
        $this->assertSame($expected, $this->links($u, $section), "sidebar: $section");
        $this->assertSame($expected, $this->preview($u, $section), "Preview Access: $section");
    }

    // ── Sales Field Ops ──────────────────────────────────────────────────

    public function test_view_sales_report_outside_sales_shows_the_sales_report_link_only(): void
    {
        $u = $this->person('employee', ['sales_report.view']);
        $this->assertSection(['Sales Report'], $u, 'Sales Field Ops');
        $this->assertNotSame(403, $this->actingAs($u)->get('/sales-visits/pipeline')->getStatusCode());
    }

    public function test_each_sales_permission_shows_its_own_link(): void
    {
        $this->assertSection(['Sales Pipeline'], $this->person('employee', ['sales_pipeline.view']), 'Sales Field Ops');
        $this->assertSection(['Book a Visit'], $this->person('employee', ['sales_visits.create']), 'Sales Field Ops');
        $this->assertSection(['Client Master'], $this->person('employee', ['client_master.view']), 'Sales Field Ops');
        $this->assertSection(['Kingo Bingo'], $this->person('employee', ['kingo_bingo.play']), 'Sales Field Ops');
        $this->assertSection(['Analytics & Reports'], $this->person('employee', ['sales_analytics.view']), 'Sales Field Ops');
    }

    public function test_view_all_sales_data_shows_the_viewing_pages(): void
    {
        // Client Master checks "View Client Master" itself, so it is not among them.
        $this->assertSection(['All Visits', 'Sales Report', 'Sales Pipeline', 'Kingo Bingo'],
            $this->person('employee', ['sales_ops.view_all']), 'Sales Field Ops');
    }

    public function test_outside_sales_with_no_sales_permission_there_is_still_no_section(): void
    {
        $this->assertSection(null, $this->person('employee'), 'Sales Field Ops');
        $this->assertSection(null, $this->person('manager'), 'Sales Field Ops');
    }

    public function test_attendance_only_delegate_still_sees_attendance_only(): void
    {
        $this->assertSection(['Attendance'], $this->person('employee', ['cc_attendance.manage']), 'Sales Field Ops');
    }

    // ── Asset Management ─────────────────────────────────────────────────

    public function test_add_new_asset_alone_shows_the_asset_manager(): void
    {
        $this->assertSection(['Asset Manager'], $this->person('manager', ['assets.create']), 'Asset Management');
    }

    public function test_asset_categories_permission_shows_asset_categories(): void
    {
        $this->assertSection(['Asset Categories'], $this->person('manager', ['assets.manage_categories']), 'Asset Management');
    }

    public function test_a_manager_without_asset_permissions_sees_no_asset_section(): void
    {
        $this->assertSection(null, $this->person('manager'), 'Asset Management');
    }

    // ── Recruitment & Onboarding ─────────────────────────────────────────

    public function test_manage_training_permission_shows_manage_training(): void
    {
        $links = $this->links($this->person('employee', ['training.manage']), 'Recruitment & Onboarding');
        $this->assertContains('Manage Training', $links ?? []);
        $this->assertNotContains('Jobs', $links ?? []);
    }

    public function test_onboarding_permission_shows_the_review_center(): void
    {
        $links = $this->links($this->person('manager', ['onboarding.manage']), 'Recruitment & Onboarding');
        $this->assertContains('Onboarding Review Center', $links ?? []);
        $this->assertNotContains('Jobs', $links ?? []);
    }

    public function test_a_manager_without_those_permissions_sees_no_recruitment_section(): void
    {
        $this->assertSection(null, $this->person('manager'), 'Recruitment & Onboarding');
    }
}
