<?php

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The delegate-session strip: shown only while acting, hidden otherwise. */
class DelegateStripRenderTest extends TestCase
{
    use DatabaseTransactions;

    private function employee(): User
    {
        return User::role('employee')
            ->where('status', UserAccountStatus::ACTIVE)
            ->orderBy('id')
            ->firstOrFail();
    }

    public function test_strip_renders_inside_a_delegate_session(): void
    {
        Mail::fake();
        $super = User::where('email', 'ai@rustx.com')->firstOrFail();

        $res = $this->withSession([
            'delegate_switch_original_id' => $super->id,
            'delegate_switch_super'       => true,
        ])->actingAs($this->employee())->get('/user/dashboard');

        $res->assertOk();
        $html = $res->getContent();

        $this->assertStringContainsString('hrxDelegateStrip', $html);
        $this->assertStringContainsString('Delegate access active', $html);
        $this->assertStringContainsString('hrxDelegateStripHide', $html);   // hide control
        $this->assertStringContainsString('hrxDelegateChip', $html);        // show-again chip
        $this->assertStringContainsString('Exit Delegate Login', $html);
        $this->assertStringContainsString($super->getFullName(), $html);    // whose account it really is

        // It belongs in the navbar gap: after the page title, before the
        // right-hand icon row.
        $title = strpos($html, 'hitech-nav-title');
        $strip = strpos($html, 'hrxDelegateStrip');
        $icons = strpos($html, 'navbar-nav flex-row align-items-center ms-auto');
        $this->assertNotFalse($title);
        $this->assertNotFalse($icons);
        $this->assertGreaterThan($title, $strip, 'strip should follow the page title');
        $this->assertLessThan($icons, $strip, 'strip should precede the navbar icon row');
    }

    public function test_strip_is_absent_in_a_normal_session(): void
    {
        Mail::fake();

        $res = $this->actingAs($this->employee())->get('/user/dashboard');

        $res->assertOk();
        $this->assertStringNotContainsString('hrxDelegateStrip', $res->getContent());
        $this->assertStringNotContainsString('Delegate access active', $res->getContent());
    }
}
