<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Step 2 of the mobile audit: global rules meant to fix dozens of listed forms
 * (employee, holidays, policies, leave policy, assets, KPI, Apply Leave,
 * Outdoor Duty, Assign Task, document review, Attachment View, Salary
 * Breakdown, confirm-delete...) in ONE shared stylesheet change, rather than
 * editing each view file individually -- and the 24-40px-per-side page
 * padding waste, traced to this app's own .container-fluid/.container-xxl
 * override forcing a desktop-sized 1.5rem gutter unconditionally.
 */
class GlobalPhoneResponsivenessTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(resource_path('assets/vendor/scss/pages/hitech-portal.scss'));
    }

    public function test_the_container_gutter_is_reduced_on_phone_only(): void
    {
        $css = $this->css();
        // Desktop/laptop value (1.5rem, !important) must still be the unconditional
        // rule outside any media query -- "laptop stays as is".
        $this->assertMatchesRegularExpression('/^\.container-xxl,\s*\n\.container-fluid\s*\{\s*padding-left:\s*1\.5rem\s*!important;/m', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width:\s*480px\)\s*\{\s*\.container-xxl,\s*\n\s*\.container-fluid\s*\{\s*padding-left:\s*14px\s*!important;/', $css);
    }

    public function test_every_modal_gets_a_full_screen_sheet_layout_on_phone(): void
    {
        $css = $this->css();
        $this->assertMatchesRegularExpression('/@media \(max-width: 480px\) \{\s*\n\s*\.modal-dialog:not\(\.modal-sm\) \{\s*\n\s*width: 100%;\s*\n\s*max-width: 100%;\s*\n\s*height: 100%;/', $css, 'the modal rule is generic (plain .modal-dialog), not scoped to any one modal, so it reaches every form without editing each view');
        $this->assertStringContainsString('height: 100dvh;', $css);
        $this->assertMatchesRegularExpression('/\.modal-dialog:not\(\.modal-sm\) \.modal-footer \{[^}]*position:\s*sticky;[^}]*bottom:\s*0;/', $css);
        $this->assertStringContainsString('env(safe-area-inset-bottom', $css);
        // Uses `height`, the SAME property any modal's own more specific override
        // (e.g. #forcePolicyModal) also sets -- not a separate max-height -- so
        // CSS specificity alone resolves the conflict with no :not() exclusion
        // needed and no risk of this generic rule fighting a modal's own rule.
        $this->assertStringNotContainsString('.modal-dialog:not(.modal-sm) .modal-content {' . "\n" . '        max-height:', $css);
    }

    public function test_the_policy_modals_own_rule_still_wins_over_the_generic_one(): void
    {
        // The generic rule lives in the shared stylesheet; the policy modal's
        // own CSS lives inline in its own blade file with an ID-scoped
        // selector (#forcePolicyModal ...), which has strictly higher
        // specificity than the generic .modal-dialog one for every property
        // they share, so the two can never actually conflict.
        $genericPos = strpos($this->css(), '.modal-dialog:not(.modal-sm) {');
        $policyCss = file_get_contents(resource_path('views/layouts/sections/policy_enforcement.blade.php'));
        $policyPos = strpos($policyCss, '#forcePolicyModal .policy-modal-content');
        $this->assertNotFalse($genericPos);
        $this->assertNotFalse($policyPos, 'the policy modal must still have its own higher-specificity CSS');
    }
}
