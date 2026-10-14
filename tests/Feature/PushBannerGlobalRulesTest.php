<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * "Stay notified" (#hrxPushBanner): was shown on every single page load while
 * permission stayed undecided (deliberately, per the old code's own comment),
 * checked only #forcePolicyModal by id, and had no phone-specific layout --
 * the same ~140px floating card shrunk into whatever width was left. Step 2 of
 * the mobile audit asked for a remembered dismissal, a check against ANY open
 * modal (not just the one it happened to be built against), a slim <=64px
 * phone strip, and page padding reserved while it's shown.
 *
 * This is a static asset (public/assets/js/webpush-subscribe.js), not
 * server-rendered, so these are source-level checks on the file that actually
 * ships to the browser -- not a copy or a description of it.
 */
class PushBannerGlobalRulesTest extends TestCase
{
    private function js(): string
    {
        return file_get_contents(public_path('assets/js/webpush-subscribe.js'));
    }

    public function test_dismissal_is_remembered_for_30_days_via_localstorage(): void
    {
        $js = $this->js();
        $this->assertStringContainsString("localStorage.getItem(DISMISS_KEY", $js);
        $this->assertStringContainsString('DISMISS_DAYS = 30', $js);
        $this->assertStringContainsString('rememberDismissal()', $js, 'clicking "Not now" must actually persist the dismissal');
    }

    public function test_it_checks_for_any_open_modal_not_just_the_policy_one(): void
    {
        $js = $this->js();
        $this->assertStringContainsString("document.querySelector('.modal.show')", $js);
        // The old, narrower check must be gone, not just supplemented.
        $this->assertStringNotContainsString("document.getElementById('forcePolicyModal')", $js);
    }

    public function test_the_phone_layout_is_a_slim_strip_with_real_tap_targets(): void
    {
        $js = $this->js();
        $this->assertStringContainsString("matchMedia('(max-width: 480px)')", $js);
        $this->assertStringContainsString('padding:8px 12px', $js, 'the phone banner is a slim strip, not the desktop card shrunk down');
        $this->assertStringContainsString('env(safe-area-inset-bottom', $js);
        $this->assertMatchesRegularExpression('/min-height:48px;min-width:48px/', $js, 'the phone Enable/dismiss buttons meet the 48px minimum tap target');
    }

    public function test_page_bottom_padding_is_reserved_while_the_banner_is_shown_and_released_after(): void
    {
        $js = $this->js();
        $this->assertStringContainsString("classList.add(BODY_PAD_CLASS)", $js);
        $this->assertStringContainsString("classList.remove(BODY_PAD_CLASS)", $js, 'both the dismiss and the enable path must release the reserved space');

        $css = file_get_contents(resource_path('assets/vendor/scss/pages/hitech-portal.scss'));
        $this->assertStringContainsString('.hrx-push-banner-open', $css);
        $this->assertMatchesRegularExpression('/max-width:\s*480px\)\s*\{\s*\.hrx-push-banner-open\s*\{\s*padding-bottom:\s*calc\(64px/', $css, 'the phone padding matches the phone banner\'s own <=64px height');
    }
}
