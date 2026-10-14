<?php

namespace Tests\Feature;

use App\Models\HRPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The mandatory-policy popup, reported unreadable on a phone: Android Chrome has no
 * built-in PDF renderer for an iframe pointed straight at a PDF, so instead of the
 * document it showed a bare "PDF / Open" card. Fixed by routing phones through
 * Google's viewer wrapper and giving the modal a mobile layout + a fallback link.
 *
 * DatabaseTransactions, never RefreshDatabase -- the local database holds real
 * imported production data.
 */
class PolicyEnforcementMobileTest extends TestCase
{
    use DatabaseTransactions;

    private function activeUser(): User
    {
        $user = User::create([
            'first_name' => 'QA', 'last_name' => 'Policy', 'email' => 'pz' . uniqid() . '@example.test',
            'code' => 'PZ' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'),
            'status' => 'active',
        ])->fresh();
        // admin.guard (on the '/' dashboard route) requires a role; 'employee' is the
        // ordinary one every real user has.
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']));

        return $user->fresh();
    }

    private function pendingPolicy(): HRPolicy
    {
        $admin = User::where('status', 'active')->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();

        return HRPolicy::create([
            'tenant_id' => 1, 'title' => 'QA Confidentiality Policy ' . uniqid(), 'file_path' => 'policies/qa.pdf',
            'is_mandatory' => true, 'show_as_popup' => true, 'is_active' => true,
            'created_by_id' => $admin?->id ?? 1,
        ]);
    }

    public function test_the_popup_renders_the_mobile_fallback_link_and_breakpoint_matching_the_scripts_own_check(): void
    {
        $user = $this->activeUser();
        $this->pendingPolicy();

        $html = $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('forcePolicyModal', $html);
        $this->assertStringContainsString('id="policyMobileOpen"', $html, 'the phone-only fallback link exists');
        // The JS decides "narrow" at 767px; the two Bootstrap breakpoint utilities on
        // the two links must switch at the SAME point (768px) or there is a width
        // range where neither the top link nor the bottom fallback is visible.
        $this->assertStringContainsString('d-none d-md-inline', $html, 'desktop link hidden below 768px');
        $this->assertStringContainsString('d-md-none', $html, 'phone fallback hidden from 768px up');
        $this->assertStringContainsString('max-width: 767px', $html, "JS narrow check matches Bootstrap's md breakpoint");
        $this->assertStringContainsString('docs.google.com/gview', $html, 'phones get the renderable viewer wrapper, not a raw PDF link');
    }

    public function test_desktop_still_gets_a_real_modal_height(): void
    {
        // Regression: the fixed height used to be an inline style="height: 85vh" on
        // .modal-content; moving it into a class dropped it entirely for anyone NOT on
        // a phone, so modal-body (and the PDF viewer inside it, sized via flex-grow)
        // had nothing to size against and collapsed toward the iframe's tiny browser
        // default. Desktop/tablet (min-width: 768px) must still get a real height.
        $user = $this->activeUser();
        $this->pendingPolicy();

        $html = $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/@media\s*\(min-width:\s*768px\)\s*\{\s*#forcePolicyModal\s*\.policy-modal-content\s*\{[^}]*height:\s*85vh/',
            $html,
            'desktop/tablet keeps a real modal height outside the phone-only media query'
        );
    }

    public function test_the_modal_body_can_actually_scroll_on_a_phone(): void
    {
        $user = $this->activeUser();
        $this->pendingPolicy();

        $html = $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();

        // .modal-content clips overflow for its rounded corners, so pinning it to a fixed
        // phone-screen height with nothing below set to scroll leaves the checkbox and the
        // acknowledge button unreachable -- exactly what was reported ("no scroll option").
        $this->assertMatchesRegularExpression('/\.modal-body\s*\{[^}]*overflow-y:\s*auto/', $html, 'the body scrolls when its content is taller than the screen');
        $this->assertMatchesRegularExpression('/\.modal-body\s*\{[^}]*min-height:\s*0/', $html, "a flex child needs min-height:0 or overflow-y:auto never kicks in (the classic flexbox trap)");
        $this->assertMatchesRegularExpression('/\.policy-modal-viewer\s*\{[^}]*flex:\s*0 0 /', $html, 'the viewer gets a real fixed height, not height:100% of an unbounded scrolling parent');
    }

    public function test_the_landscape_phone_breakpoint_matches_between_css_and_js(): void
    {
        // A phone turned sideways (e.g. 844x390) has a desktop-sized WIDTH but nowhere
        // near desktop-sized height: a width-only query would hand it the centered,
        // fixed-85vh desktop modal with no room for the content, and the JS would pick
        // the raw (unreadable in that context) PDF instead of the renderable viewer.
        $user = $this->activeUser();
        $this->pendingPolicy();

        $html = $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();

        $cssQuery = '(max-width: 767.98px), (max-height: 500px) and (orientation: landscape)';
        $jsQuery = '(max-width: 767px), (max-height: 500px) and (orientation: landscape)';
        $this->assertStringContainsString($cssQuery, $html, 'the phone/landscape CSS media query');
        $this->assertStringContainsString($jsQuery, $html, "the JS isNarrow check, matching the CSS query's landscape half exactly");
    }

    public function test_the_footer_is_sticky_with_safe_area_padding_and_real_tap_targets(): void
    {
        // The audit's core complaint: on a phone, the checkbox and the Acknowledge
        // button must be visible THE WHOLE TIME, not just reachable by scrolling down
        // to them -- a sticky footer (pinned to the bottom of the scrolling area) does
        // that; plain overflow-y:auto on its own (the earlier fix) only makes them
        // reachable, which is weaker than what was asked for here.
        $user = $this->activeUser();
        $this->pendingPolicy();

        $html = $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\.policy-modal-footer\s*\{[^}]*position:\s*sticky/', $html, 'the footer is pinned, not merely scrollable-to');
        $this->assertMatchesRegularExpression('/\.policy-modal-footer\s*\{[^}]*bottom:\s*0/', $html);
        $this->assertStringContainsString('env(safe-area-inset-bottom', $html, 'clears the home-indicator area on notched phones');
        $this->assertMatchesRegularExpression('/#forceSignBtn\s*\{[^}]*min-height:\s*48px/', $html, 'the button meets the 48px minimum tap-target height');
        $this->assertMatchesRegularExpression('/\.policy-modal-checkrow\s*\{[^}]*min-height:\s*44px/', $html, 'the whole checkbox row is a real 44px tap target, not just the small checkbox square');
    }

    public function test_the_header_stays_one_line_and_the_instruction_text_is_shortened_on_phone(): void
    {
        $user = $this->activeUser();
        $this->pendingPolicy();

        $html = $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();

        // The title and the "Pending: N" badge used to wrap independently -- "Pending: 2"
        // could split so the "2" landed on its own line. Both must now refuse to wrap.
        $this->assertMatchesRegularExpression('/\.policy-modal-title\s*\{[^}]*white-space:\s*nowrap/', $html);
        $this->assertMatchesRegularExpression('/\.policy-modal-badge\s*\{[^}]*white-space:\s*nowrap/', $html);
        $this->assertMatchesRegularExpression('/\.policy-modal-header\s*\{[^}]*flex-wrap:\s*nowrap/', $html, 'title and badge are kept on the same row, not stacked');
        // Full instruction text on desktop, a short version on phone -- same pair of
        // Bootstrap breakpoint classes already used for the "open in a new tab" links.
        $this->assertStringContainsString('d-none d-md-inline"><strong>', $html, 'the long instruction text is desktop-only');
        $this->assertStringContainsString('d-md-none"><strong>', $html, 'a short instruction text replaces it on phone');
        $this->assertStringContainsString('read below, then confirm', $html, 'the phone-only short wording');
    }

    public function test_no_sideways_scroll_guards_are_present(): void
    {
        $user = $this->activeUser();
        $this->pendingPolicy();

        $html = $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\.policy-modal-content\s*\{[^}]*overflow-x:\s*hidden/', $html);
        $this->assertMatchesRegularExpression('/\.modal-body\s*\{[^}]*overflow-x:\s*hidden/', $html);
        // Bootstrap pads <body> for the scrollbar it hides while a modal is open; on a
        // phone there was never a visible scrollbar to compensate for, so that padding
        // alone could push the page a few pixels past the viewport width.
        $this->assertMatchesRegularExpression('/body\.modal-open\s*\{[^}]*padding-right:\s*0/', $html);
    }

    public function test_the_push_notification_banner_defers_while_a_policy_is_pending(): void
    {
        // Can't execute the browser JS from PHPUnit, so this asserts on the actual
        // source that ships to the browser -- the static file under public/, not a
        // copy or a description of it.
        $js = file_get_contents(public_path('assets/js/webpush-subscribe.js'));

        $this->assertNotFalse($js, 'the live webpush-subscribe.js file must exist and be readable');
        $this->assertMatchesRegularExpression(
            "/if\s*\(document\.getElementById\('forcePolicyModal'\)\)\s*return;/",
            $js,
            'showBanner() must bail out while the mandatory-policy modal exists in the page'
        );
        // Full page reload happens on every single acknowledgement (see
        // policy_enforcement.blade.php's signBtn click handler), so this check alone is
        // enough -- the banner always gets a fresh, correct read on the very next load.
        $this->assertStringContainsString("location.reload()", file_get_contents(resource_path('views/layouts/sections/policy_enforcement.blade.php')));
        // Second safety: even if that guard were ever bypassed, the banner must not be
        // able to render ABOVE the modal (1055) -- it used to be 99999.
        $this->assertStringContainsString('z-index:1040', $js, 'banner z-index kept below the modal as a second safety');
        $this->assertStringNotContainsString('z-index:99999', $js);
    }

    public function test_the_delegate_exit_controls_stay_above_the_policy_modal(): void
    {
        // The navbar "strip" (hidden below 768px) and the always-visible delegate-hub
        // dropdown icon are two SEPARATE controls; both must out-rank the modal
        // (z-index 1055/1050 in this app's Bootstrap 5.3.3) or an admin working as a
        // delegate, with a pending policy on screen, has no way back to their own
        // account -- on a phone, the dropdown is the ONLY one of the two even visible.
        $strip = file_get_contents(resource_path('views/layouts/sections/delegate_strip.blade.php'));
        $hub = file_get_contents(resource_path('views/layouts/sections/navbar/delegate_hub.blade.php'));

        $this->assertMatchesRegularExpression('/\.hrx-delegate-wrap\s*\{[^}]*position:\s*relative[^}]*z-index:\s*1100/', $strip, 'z-index alone does nothing on a position:static element, so position must be set too');
        $this->assertStringContainsString('z-index:1100', $hub, 'the always-visible dropdown (not just the desktop-only strip) is elevated too');
    }

    public function test_the_popup_is_absent_with_no_pending_policy(): void
    {
        // The local database already carries several mandatory, company-wide policies
        // (target_departments is null, so they apply to everyone), so a freshly made
        // user is never actually policy-free: acknowledge every one of them first.
        $user = $this->activeUser();
        foreach (\App\Models\HRPolicy::where('is_active', true)->where('show_as_popup', true)->get() as $policy) {
            $policy->acknowledgments()->create(['tenant_id' => $policy->tenant_id, 'user_id' => $user->id, 'acknowledged_at' => now()]);
        }

        $html = $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('forcePolicyModal', $html);
    }
}
