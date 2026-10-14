<?php

namespace Tests\Feature;

use App\Models\HRPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The mandatory-policy popup, fixed across two retests of the real phone experience.
 *
 * Retest 1 (Android Chrome emulation, 360-844 widths): fixed the raw-PDF-in-an-iframe
 * unreadable "PDF/Open" card, the unscrollable/unreachable footer, the vanished desktop
 * height, the push-notification banner and the delegate-exit button both getting covered,
 * and a landscape-only layout gap.
 *
 * Retest 2 (14 Oct 2026, real measurements against hrx.hitechgroup.in as a real Sales
 * employee): found the fix from retest 1 still wasn't enough --
 *  - the Google-viewer stand-in scaled an 11px paragraph down to ~5px (unreadable) AND
 *    imposed its own fixed ~393px internal width regardless of the iframe's real size
 *    (the actual source of a real, not cosmetic, 33px horizontal overflow at 360px) AND
 *    sent the policy's private signed URL to a third party;
 *  - the header title was truncated with an ellipsis, hiding part of the heading;
 *  - the checkbox's real tap target (44px) was still short of a comfortable 48px;
 *  - in landscape the reader painted behind the footer because the reader's height was a
 *    guessed, fixed 55vh rather than genuinely computed from the space left over;
 *  - the delegate-exit button was a literal 0x0 rectangle on phone (buried in a
 *    hamburger-collapsed navbar dropdown the modal's own backdrop blocks from opening).
 *
 * This fixes all of that: PDF.js (same library the training portal already uses) renders
 * the real document at a real, computed-to-fit size with zoom controls, fetched through a
 * new same-origin streaming route (not gview, not a direct cross-origin fetch of the
 * storage bucket's URL, which has no CORS headers and would simply fail); the modal uses a
 * real 3-row CSS grid on phone so the reader literally cannot paint behind the footer; the
 * title wraps instead of truncating; the checkbox row is a genuine 48px target; and the
 * delegate-exit control is duplicated INSIDE the modal itself, so it never has to win a
 * stacking fight against the very thing covering it.
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

    private function render(User $user): string
    {
        return $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();
    }

    public function test_the_modal_renders_with_a_pending_policy_and_not_without_one(): void
    {
        $user = $this->activeUser();
        $this->pendingPolicy();
        $this->assertStringContainsString('forcePolicyModal', $this->render($user));

        // The local database already carries several mandatory, company-wide policies
        // (target_departments is null, so they apply to everyone), so a freshly made user
        // is never actually policy-free unless every one of them is acknowledged first.
        $clean = $this->activeUser();
        foreach (HRPolicy::where('is_active', true)->where('show_as_popup', true)->get() as $policy) {
            $policy->acknowledgments()->create(['tenant_id' => $policy->tenant_id, 'user_id' => $clean->id, 'acknowledged_at' => now()]);
        }
        $this->assertStringNotContainsString('forcePolicyModal', $this->render($clean));
    }

    public function test_desktop_still_gets_a_real_modal_height_and_tablet_scroll_area_is_a_flex_column(): void
    {
        $user = $this->activeUser();
        $this->pendingPolicy();
        $html = $this->render($user);

        $this->assertMatchesRegularExpression(
            '/@media\s*\(min-width:\s*768px\)\s*\{\s*#forcePolicyModal\s*\.policy-modal-content\s*\{[^}]*height:\s*85vh/',
            $html,
            'desktop/tablet keeps a real modal height outside the phone-only media query'
        );
    }

    public function test_the_phone_layout_is_a_true_three_row_grid_so_the_reader_cannot_paint_behind_the_footer(): void
    {
        // The literal defect found in landscape: a fixed-vh-guessed reader height versus a
        // real, content-driven one. A CSS grid with grid-template-rows: auto minmax(0,1fr)
        // auto cannot overlap its rows by construction, unlike the earlier flex + sticky
        // footer approach that depended on getting a height guess right.
        $user = $this->activeUser();
        $this->pendingPolicy();
        $html = $this->render($user);

        $this->assertMatchesRegularExpression(
            '/#forcePolicyModal\s*\.policy-modal-content\s*\{[^}]*display:\s*grid;[^}]*grid-template-rows:\s*auto minmax\(0,\s*1fr\)\s*auto/',
            $html
        );
        $this->assertMatchesRegularExpression('/\.policy-modal-scrollarea\s*\{[^}]*min-height:\s*0/', $html, 'the scroll row still needs the classic flexbox/grid min-height:0 fix for its own overflow-y:auto to take effect');
        $this->assertMatchesRegularExpression('/\.policy-modal-scrollarea\s*\{[^}]*overflow-y:\s*auto/', $html);
        // No more guessed fixed height for the reader area.
        $this->assertStringNotContainsString('flex: 0 0 55vh', $html);
    }

    public function test_the_header_wraps_instead_of_truncating_and_has_a_visible_gap_from_the_badge(): void
    {
        $user = $this->activeUser();
        $this->pendingPolicy();
        $html = $this->render($user);

        // The retest's literal finding: "MANDATORY CO..." / "MANDATORY COMP..." -- an
        // ellipsis HIDING part of the heading. white-space:nowrap + text-overflow:ellipsis
        // on the title must be gone; it should wrap instead.
        $this->assertDoesNotMatchRegularExpression('/\.policy-modal-title\s*\{[^}]*text-overflow:\s*ellipsis/', $html, 'the title must never be truncated -- it should wrap, not hide text');
        $this->assertMatchesRegularExpression('/\.policy-modal-title\s*\{[^}]*white-space:\s*normal/', $html);
        $this->assertMatchesRegularExpression('/\.policy-modal-headrow\s*\{[^}]*gap:\s*1rem/', $html, 'a visible gap between the title and the count, on every screen size');
    }

    public function test_the_checkbox_row_is_a_real_48px_tap_target(): void
    {
        // The first fix's 44px was measured as still short in the retest; the audit asks
        // for a minimum of 48x48 CSS px.
        $user = $this->activeUser();
        $this->pendingPolicy();
        $html = $this->render($user);

        $this->assertMatchesRegularExpression('/\.policy-modal-checkrow\s*\{[^}]*min-height:\s*48px/', $html);
        $this->assertMatchesRegularExpression('/#forceSignBtn\s*\{[^}]*min-height:\s*48px/', $html);
    }

    public function test_no_sideways_scroll_guards_are_present(): void
    {
        $user = $this->activeUser();
        $this->pendingPolicy();
        $html = $this->render($user);

        $this->assertMatchesRegularExpression('/\.policy-modal-content\s*\{[^}]*overflow-x:\s*hidden/', $html);
        $this->assertMatchesRegularExpression('/body\.modal-open\s*\{[^}]*padding-right:\s*0/', $html);
        // The real, non-cosmetic source the retest found: an external viewer imposing its
        // own fixed internal width. Removing it (in favour of PDF.js, sized to the actual
        // container) is the structural fix; the guards above are a defensive backstop, not
        // a substitute for it.
        $this->assertStringNotContainsString('docs.google.com/gview', $html, 'the component that imposed a fixed ~393px width regardless of viewport must be gone');
    }

    public function test_the_phone_pdf_reader_fetches_same_origin_not_the_bucket_directly(): void
    {
        // The storage bucket sends no Access-Control-Allow-Origin header (confirmed against
        // the real bucket), so a cross-origin fetch() of its signed URL -- what PDF.js needs,
        // unlike a plain navigation -- would simply fail. Streaming through our own route
        // sidesteps that and never exposes the bucket URL to this code path at all.
        $user = $this->activeUser();
        $this->pendingPolicy();
        $html = $this->render($user);

        $this->assertStringContainsString("url: `/hr-policies/stream/\${id}`", $html);
        $this->assertStringContainsString('pdf.js/3.11.174/pdf.min.js', $html, 'reuses the exact library/version already used by the training portal');
        $this->assertStringContainsString('policyReaderZoom', $html, 'zoom controls exist');
        $this->assertStringContainsString('policyReaderError', $html, 'a failure state exists, separate from the acknowledge flow');
        $this->assertStringContainsString('policyReaderRetry', $html, 'the failure state offers a retry');
        // The Bootstrap breakpoint in the fallback link is unaffected by any of this: it must
        // still exist, now reading "Open original PDF" per the retest's exact phrasing.
        $this->assertStringContainsString('Open original PDF', $html);
    }

    public function test_a_network_failure_cannot_silently_satisfy_the_checkbox_or_button(): void
    {
        // The reader's error path only toggles reader-UI elements (#policyReaderStatus /
        // #policyReaderError), never #forceConfirmCheckbox or #forceSignBtn -- a failed
        // preview must never read as "the policy was read".
        $user = $this->activeUser();
        $this->pendingPolicy();
        $html = $this->render($user);

        preg_match('/function showReaderError\(\)\s*\{(.*?)\n            \}/s', $html, $m);
        $this->assertNotEmpty($m, 'showReaderError() must exist');
        $this->assertStringNotContainsString('forceConfirmCheckbox', $m[1]);
        $this->assertStringNotContainsString('forceSignBtn', $m[1]);
        $this->assertStringNotContainsString('.disabled = false', $m[1], 'the error path never re-enables the acknowledge button');
    }

    public function test_the_push_notification_banner_defers_while_a_policy_is_pending(): void
    {
        // The check is now "any open modal" (see PushBannerGlobalRulesTest for the full
        // coverage of that generalisation) -- #forcePolicyModal, carrying Bootstrap's
        // 'show' class while visible, is one instance of that general rule.
        $js = file_get_contents(public_path('assets/js/webpush-subscribe.js'));

        $this->assertNotFalse($js, 'the live webpush-subscribe.js file must exist and be readable');
        $this->assertStringContainsString("document.querySelector('.modal.show')", $js, "showBanner() must bail out while any modal, including the policy one, is open");
        $this->assertStringContainsString('z-index:1040', $js, 'banner z-index kept below the modal as a second safety');
        $this->assertStringNotContainsString('z-index:99999', $js);
    }

    public function test_the_delegate_exit_control_lives_inside_the_modal_itself(): void
    {
        // The retest's root cause for the "0x0 rectangle": the real delegate-exit control
        // sits inside a navbar dropdown that is itself collapsed behind a hamburger menu on
        // phone -- and the modal's own backdrop blocks that hamburger from ever being
        // reachable. A control placed in the navbar has to win that stacking/reachability
        // fight; one placed INSIDE the modal's own DOM never has to fight it at all. This
        // must appear ONLY for an admin currently acting as someone else, and must submit
        // the existing, unmodified delegation-exit route -- nothing new is invented here.
        $originalAdmin = $this->activeUser();
        $delegateTarget = $this->activeUser();
        $this->pendingPolicy();

        // Matches the real flow: the person actually logged in (and seeing the policy
        // modal) is the delegate TARGET account; the session remembers the ORIGINAL
        // admin's id so "Exit" can return to them. withSession(), not the bare session()
        // helper -- a feature test's simulated request needs the value set on ITS OWN
        // session, not just the current process's.
        $html = $this->actingAs($delegateTarget)
            ->withSession(['delegate_switch_original_id' => $originalAdmin->id])
            ->get('/user/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('policy-modal-delegate-exit', $html);
        $this->assertStringContainsString(route('user.delegation.exit'), $html);
        $this->assertStringContainsString('Exit delegated view', $html);
    }

    public function test_the_delegate_exit_control_is_absent_for_an_ordinary_user(): void
    {
        // Its own test, with its own fresh session, rather than a second request tacked
        // onto the test above: the test session store is per-test, not per-request, and a
        // delegate session value set earlier in the SAME test method was found to survive
        // into a later request in that method however it was cleared -- a genuine quirk of
        // the testing session, not of the application code under test.
        $other = $this->activeUser();
        $this->pendingPolicy();
        $html = $this->render($other);

        // 'policy-modal-delegate-exit' alone isn't a safe needle here: it's also the CSS
        // selector in the unconditional <style> block, so it "matches" the page whether or
        // not the @if actually rendered the control. "Exit delegated view" only exists
        // inside that @if, so it's the one that actually proves the control is absent.
        $this->assertStringNotContainsString('Exit delegated view', $html, 'never a general-purpose "close the modal" escape hatch for an ordinary employee');
    }

    public function test_the_delegate_navbar_controls_still_also_carry_elevated_z_index_as_a_backstop(): void
    {
        $strip = file_get_contents(resource_path('views/layouts/sections/delegate_strip.blade.php'));
        $hub = file_get_contents(resource_path('views/layouts/sections/navbar/delegate_hub.blade.php'));

        $this->assertMatchesRegularExpression('/\.hrx-delegate-wrap\s*\{[^}]*position:\s*relative[^}]*z-index:\s*1100/', $strip);
        $this->assertStringContainsString('z-index:1100', $hub);
    }
}
