<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Step 5 (medium) and Step 6 (low) of the mobile audit: a long tail of
 * pages with a specific measured problem each (overflow, tiny text, small
 * tap targets, or clipped controls). Source-level checks on the actual
 * blade/CSS each page ships, not a description of the fix.
 */
class Step5Step6PhoneFixesTest extends TestCase
{
    private function readView(string $relative): string
    {
        return file_get_contents(resource_path('views/' . $relative));
    }

    public function test_bulk_kpi_entry_scrolls_horizontally_with_a_sticky_employee_column_and_48px_inputs(): void
    {
        // Measured 17 past-edge + 33 tiny inputs: width:100% squeezed every
        // KPI column instead of letting the table scroll; no sticky column.
        $view = $this->readView('tenant/kpi/bulk-entry.blade.php');
        $this->assertStringContainsString('min-width:max-content', $view);
        $this->assertStringContainsString('table.bk thead th:nth-child(2),', $view);
        $this->assertStringContainsString('min-height: 48px;', $view);
        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{[\s\S]*?\.bk-cell input \{\s*min-height: 48px;/', $view);
    }

    public function test_kingo_bingo_title_gets_its_own_row_and_table_text_is_readable_on_phone(): void
    {
        // Measured: heading wrapped 3 lines, ~4,000 elements under 11px.
        $view = $this->readView('tenant/kingo-bingo/index.blade.php');
        $this->assertStringContainsString('.kb-page-title { flex: 1 1 100%; }', $view);
        $this->assertStringContainsString('table.kb-table,', $view);
    }

    public function test_fill_targets_inputs_meet_the_48px_tap_target_on_phone(): void
    {
        // Measured ~5,100 tiny inputs (58-88px cells, 11.5px text, no
        // phone override) despite the scroll wrapper and sticky columns
        // already existing structurally.
        $view = $this->readView('tenant/kingo-bingo/fill-targets.blade.php');
        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*td\.v, td\.v-qtr \{ min-width:60px; \}/', $view);
        $this->assertStringContainsString('min-height:48px; font-size:14px;', $view);
    }

    public function test_digital_library_heading_tabs_dropdown_and_badge_overlap_are_fixed(): void
    {
        // Measured: heading/tabs overlap (phone AND laptop, no breakpoint
        // existed above 767.98px), brand dropdown clipped by the card's
        // overflow:hidden, file-type badge overlapping the card title.
        $view = $this->readView('tenant/digital-library/index.blade.php');
        $this->assertStringContainsString('.vault-header-card > :first-child { border-radius', $view);
        $this->assertStringNotContainsString('overflow: hidden; margin-bottom: 2rem; }', $view, 'overflow:hidden must be removed from .vault-header-card, not just supplemented, since that is what clipped the dropdown');
        $this->assertStringContainsString('.nav-filters { flex-wrap: wrap; }', $view);
        $this->assertStringContainsString('.product-title-area { flex: 1; min-width: 0; }', $view);
        $this->assertStringContainsString('padding: 20px 86px 14px 16px;', $view);
    }

    public function test_sales_visits_header_wraps_instead_of_squeezing_book_visit(): void
    {
        // Measured: heading squeezed by "Book Visit" -- no flex-wrap existed
        // anywhere in this file.
        $view = $this->readView('tenant/sales-visits/index.blade.php');
        $this->assertStringContainsString('d-flex flex-wrap justify-content-between align-items-center gap-2', $view);
    }

    public function test_sales_report_and_pipeline_filters_stack_full_width_on_phone(): void
    {
        // Measured 19 (report) / count unspecified (pipeline) past-edge:
        // every filter field was a hardcoded style="width:Npx" with no
        // phone breakpoint in either file.
        $report = $this->readView('tenant/sales-pipeline/index.blade.php');
        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*\.sales-filter > \.form-select,/', $report);
        $this->assertStringContainsString('flex-wrap sp-toolbar', $report, 'the crowded 1366px toolbar must wrap');

        $pipeline = $this->readView('tenant/sales-pipeline/actual-pipeline.blade.php');
        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*\.sales-filter \.form-select,/', $pipeline);
    }

    public function test_org_hierarchy_toolbar_and_node_text_are_fixed(): void
    {
        // Measured: toolbar cut off at the right edge, 271 node-text
        // elements under 11px.
        $view = $this->readView('tenant/organisation-hierarchy/index.blade.php');
        $this->assertMatchesRegularExpression('/@media \(max-width:480px\)\{ \.oc-search\{min-width:0;flex:1 1 100%\} \}/', $view);
        $this->assertMatchesRegularExpression('/@media \(max-width:480px\)\{\s*\.oc-box i, \.oc-box u \{ font-size:\.7rem; \}/', $view);
    }

    public function test_table_responsive_gets_a_shared_scroll_hint_shadow(): void
    {
        // Several pages (Work Control, Travel Claims) had Status scroll out
        // of view with no visual cue that the table scrolls further --
        // fixed once, app-wide, rather than per page.
        $css = file_get_contents(resource_path('assets/vendor/scss/pages/hitech-portal.scss'));
        $this->assertStringContainsString('.table-responsive {', $css);
        $this->assertStringContainsString('background-attachment: local, local, scroll, scroll;', $css);
    }

    public function test_btn_icon_meets_the_48px_tap_target_on_phone_only(): void
    {
        // Sales Report (903) / Sales Pipeline (353) small-target counts
        // traced to the shared .btn-icon class, fixed at 36x36px app-wide.
        // Phone-only override so desktop density elsewhere is untouched.
        $css = file_get_contents(resource_path('assets/vendor/scss/pages/hitech-portal.scss'));
        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*\.btn-icon \{\s*width: 48px !important;\s*height: 48px !important;/', $css);
    }

    public function test_tiny_text_pages_are_raised_to_12px_or_more(): void
    {
        // Step 6.2: Roles Special Permissions (294), Sales Clients (152),
        // Audit Logs (70), Onboarding Review Center (96).
        $this->assertStringContainsString('.usp-recon-table thead th {', $this->readView('roles/special-permissions.blade.php'));
        $this->assertStringContainsString('font-size: .75rem;', $this->readView('roles/special-permissions.blade.php'));

        $this->assertStringContainsString('.stage-badge, .role-pill, .emp-avatar,', $this->readView('tenant/crm-clients/index.blade.php'));

        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*\.al-stat \.lbl, \.al-table thead th, \.al-mail, \.al-ev, \.al-chip \{/', $this->readView('audit-logs/index.blade.php'));

        $this->assertStringContainsString('.step-label {' . "\n" . '        font-size: 12px;', $this->readView('tenant/onboarding/review_center.blade.php'));
    }

    public function test_my_profile_work_control_travel_claims_directory_tiny_text_raised(): void
    {
        // My Profile (877), Work Control (~1,550), Travel Claims (255),
        // Employee Directory badges.
        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*\.smallest \{ font-size: 11px !important; \}/', $this->readView('account/my-profile.blade.php'));

        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*\[style\*="font-size: 0\.65rem"\]/', $this->readView('tenant/work-control/admin-dashboard.blade.php'));

        $this->assertStringContainsString('style="font-size:.75rem;"', $this->readView('tenant/travel-claims/index.blade.php'));

        $directory = $this->readView('tenant/employees/index.blade.php');
        $this->assertStringContainsString('style="font-size: 0.75rem; letter-spacing: 0.03em;">{{ $displayLabel }}', $directory);
        $this->assertStringContainsString("style=\"font-size: 0.75rem;\">\${err}", $directory);
    }
}
