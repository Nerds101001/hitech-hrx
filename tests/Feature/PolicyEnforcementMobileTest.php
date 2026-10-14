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
