<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Step 3 of the mobile audit: 9 named pages measured wider than their
 * viewport on phone (390px target), two of them (Mind Speak) also on
 * laptop (1366px). Each page's overflow had a different root cause, so
 * each fix is page-specific rather than one shared rule -- these are
 * source-level checks (grepping the actual blade/CSS each page ships)
 * that the specific markup/CSS responsible for each measured overflow
 * was actually changed, not a description of the fix.
 */
class Step3PhoneOverflowFixesTest extends TestCase
{
    private function readView(string $relative): string
    {
        return file_get_contents(resource_path('views/' . $relative));
    }

    public function test_team_attendance_tab_bar_gets_its_own_scroll_box_on_phone(): void
    {
        // Measured 928px: the filter row and both tables already scrolled on
        // their own (prior art); the tab+action bar was the one gap -- it had
        // no overflow box, just flex-wrap, so its nowrap children leaked into
        // the page's own width.
        $css = $this->readView('tenant/attendance/index.blade.php');
        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*\.attendance-tabbar \{\s*flex-wrap: nowrap !important;\s*overflow-x: auto;/', $css);
    }

    public function test_task_management_tabs_scroll_and_filters_stack_on_phone(): void
    {
        // Measured Admin 816 / Quality 649: the 4-tab segmented control was
        // inline-flex with no wrap/scroll (identical for every role), and the
        // filter row used plain col-lg-*/col-md-6 with no phone breakpoint.
        $view = $this->readView('tenant/task-management/index.blade.php');
        $this->assertStringContainsString('#tmTabs {', $view);
        $this->assertStringContainsString('overflow-x: auto;', $view);
        $this->assertMatchesRegularExpression('/#taskFilterCard \.row\.g-4 > \[class\*="col-"\],\s*\n\s*#tmTabsContent \.row\.g-4 > \[class\*="col-"\] \{\s*flex: 0 0 100%;/', $view);
    }

    public function test_my_leaves_header_row_wraps_instead_of_squeezing_outdoor_duty_offscreen(): void
    {
        // Measured 444: both the title/button row and the inner button group
        // were .d-flex with no flex-wrap (Bootstrap's default is nowrap), so
        // "Apply for Outdoor Duty" was pushed past the viewport edge.
        $view = $this->readView('tenant/users/leaves/index.blade.php');
        $this->assertStringContainsString('d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4', $view);
        $this->assertStringContainsString('<div class="d-flex flex-wrap gap-2">', $view);
    }

    public function test_roles_stat_row_wraps_and_permission_grids_collapse_to_one_column_on_phone(): void
    {
        // Measured 820: .rp-shell-stats (3 stat pills + 3 action controls)
        // had no flex-wrap, and the permission/channel grids used
        // minmax(170-250px, 1fr) floors that never fit under ~390px.
        $view = $this->readView('roles/index.blade.php');
        $this->assertStringContainsString('.rp-shell-stats { display: flex; align-items: center; flex-wrap: wrap; gap: .75rem; }', $view);
        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*\n\s*\.rp-detail-col \.hitech-card,\s*\n\s*\.rp-panel-wrap \{ padding: 1rem; \}\s*\n\s*\.rp-perm-grid,\s*\n\s*\.rp-reach-grid,\s*\n\s*\.rp-tile-grid-channels \{ grid-template-columns: 1fr; \}/', $view);
    }

    public function test_mind_speak_stat_strip_no_longer_forces_exactly_seven_columns_on_laptop(): void
    {
        // Measured 579 phone AND 1389 laptop -- the one Step-3 page broken on
        // BOTH sizes. grid-template-columns: repeat(7, minmax(0,1fr)) applied
        // unconditionally above 1199px, giving a 1366px laptop the same
        // column width as a 1920px desktop, which is why labels like
        // "COMPETITOR INSIGHTS" / "360 FEEDBACK" visibly collided there too.
        $view = $this->readView('tenant/mind-speak/admin-dashboard.blade.php');
        $this->assertStringNotContainsString('grid-template-columns: repeat(7, minmax(0, 1fr));', $view, 'the fixed 7-column grid (broken on both phone and laptop) must be gone, not just supplemented');
        $this->assertStringContainsString('grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));', $view);
        // The Filter/Reset/Export toolbar cell had no flex-wrap, squeezing a
        // growing Filter button plus two fixed 40px icon buttons into a
        // col-md-2 cell -- the reported "Export pushed out" on laptop.
        $this->assertStringContainsString('d-flex gap-2 flex-wrap', $view);
    }

    public function test_training_activity_tables_get_horizontal_scroll_boxes_on_phone(): void
    {
        // Measured 445: "Department Wise Performance" only had overflow-y
        // (not x), and "Modules Requiring Attention" had no scroll wrapper
        // at all -- both are 5-6 column nowrap-header tables.
        $view = $this->readView('tenant/training/activity.blade.php');
        $this->assertStringContainsString('.ta-fill > .ta-scroll { position: absolute; inset: 0; overflow-y: auto; overflow-x: auto;', $view);
        $this->assertStringContainsString('.ta-panel-body > .ta-table-scroll { overflow-x: auto; }', $view);
        $this->assertStringContainsString('<div class="ta-table-scroll">', $view, 'Modules Requiring Attention must actually be wrapped in the new scroll box, not just have the CSS rule defined unused');
    }

    public function test_ai_training_header_and_filter_pills_wrap_on_phone(): void
    {
        // Measured 432: a fixed 50px hero padding plus a non-wrapping
        // 3-pill filter strip (d-inline-flex, no wrap) pushed the page wider
        // than the viewport before any real content was laid out.
        $view = $this->readView('tenant/ai-training/index.blade.php');
        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*\.training-header \{\s*padding: 24px;/', $view);
        $this->assertStringContainsString('.nav.glass-nav {', $view);
        $this->assertStringContainsString('flex-wrap: wrap;', $view);
    }

    public function test_sales_visit_reports_hero_and_date_inputs_shrink_on_phone(): void
    {
        // Measured 398 for what the audit calls "/reports": the actual
        // filters+KPIs+charts page is sales-visits.reports (the real /reports
        // route is unrelated, a plain report-download grid). The hero's own
        // 40/32px padding plus two hardcoded 160px date inputs were the
        // overflow, even though the filter form itself already wrapped.
        $view = $this->readView('tenant/sales-visits/reports.blade.php');
        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*\.rpt-hero \{ padding: 1\.25rem 1rem; \}/', $view);
        $this->assertStringContainsString('.date-filter-form input[type=date] { width: 100%;', $view);
    }

    public function test_dashboard_task_widget_tabs_scroll_within_their_own_card_on_phone(): void
    {
        // Contributes to the dashboard overflow (Admin ~393, Sales/Production
        // manager 444): 4 non-shrinking, nowrap tab pills in .dtw-tabs forced
        // wider than the card.
        $view = $this->readView('tenant/users/dashboard/_adc-task-widget.blade.php');
        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*#dashTaskWidget \.dtw-tabs\{max-width:100%;overflow-x:auto;/', $view);
    }

    public function test_dashboard_sales_target_card_header_wraps_instead_of_clipping_the_segmented_control(): void
    {
        // The Sales Target card is shown unconditionally to Admin and, with
        // an extra scorecard row, to Sales/Production managers (explaining
        // why managers measured a larger 444 vs Admin's 393) -- in both
        // cases .adc-card-head had no flex-wrap, clipping the Bingo/Kingo/
        // Super K segmented control off the card's right edge.
        $view = $this->readView('tenant/users/dashboard/_adc-styles.blade.php');
        $this->assertStringContainsString('.adc-card-head{margin-bottom:10px;flex-wrap:wrap;gap:6px;}', $view);
    }
}
