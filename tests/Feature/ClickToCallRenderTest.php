<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The button appears on Mehak's dashboard and nobody else's. */
class ClickToCallRenderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_button_renders_on_the_agents_dashboard(): void
    {
        Mail::fake();
        $agent = User::where('email', 'bhatiamehak52@gmail.com')->firstOrFail();

        $html = $this->actingAs($agent)->get('/user/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Click to Call', $html);
        $this->assertStringContainsString('clickToCallModal', $html);
        $this->assertStringContainsString('ctcDial', $html);
        $this->assertStringContainsString('user/click-to-call', $html);
        // The dialpad should name the identity the call goes out as.
        $this->assertStringContainsString('campaign mehak', $html);
    }

    public function test_other_employees_get_no_button_and_no_endpoint(): void
    {
        Mail::fake();
        $other = User::role('employee')
            ->where('email', '!=', 'bhatiamehak52@gmail.com')
            ->where('status', \App\Enums\UserAccountStatus::ACTIVE)
            ->firstOrFail();

        $html = $this->actingAs($other)->get('/user/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('clickToCallModal', $html);
        $this->assertStringNotContainsString('user/click-to-call', $html);
    }

    /**
     * THE REPORTED BUG, live: this partial read $agent['agent'] and
     * $agent['campaign'] unconditionally to build the "Agent X · campaign Y"
     * line — Meenu's Convoque config has neither key (only vendor and
     * extension), so her dashboard 500'd on every load with "Undefined array
     * key 'agent'" the moment she was wired up. Renders for a Convoque agent
     * and shows their extension instead of the CentroVision-only line.
     */
    public function test_button_renders_for_a_convoque_agent_without_erroring(): void
    {
        Mail::fake();
        $meenu = User::where('email', 'newbiz@hitechgroup.in')->first();
        if (!$meenu) {
            $meenu = User::create([
                'first_name' => 'Meenu', 'last_name' => 'QA',
                'email'      => 'newbiz@hitechgroup.in',
                'code'       => 'MEENUQA' . random_int(100000, 999999),
                'password'   => \Illuminate\Support\Facades\Hash::make('secret-not-used'),
                'status'     => 'active',
            ]);
        }
        if (!$meenu->hasAnyRole(['employee', 'office_employee', 'manager', 'admin', 'hr'])) {
            $meenu->assignRole('employee');
        }

        $html = $this->actingAs($meenu)->get('/user/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Click to Call', $html);
        $this->assertStringContainsString('Extension 217', $html);
        $this->assertStringNotContainsString('Undefined array key', $html);
    }

    /**
     * Reported live: Convoque has no disposition concept at the API level at
     * all (confirmed against the vendor's own doc), so the dropdown showed
     * up permanently empty for Meenu — not broken, but looked it. Hidden for
     * a Convoque agent entirely rather than shown with nothing in it; Hang Up
     * stays, since that one is still pending, not permanently unsupported.
     */
    public function test_the_disposition_section_is_hidden_for_a_convoque_agent(): void
    {
        Mail::fake();
        $meenu = User::where('email', 'newbiz@hitechgroup.in')->first();
        if (!$meenu) {
            $meenu = User::create([
                'first_name' => 'Meenu', 'last_name' => 'QA',
                'email'      => 'newbiz@hitechgroup.in',
                'code'       => 'MEENUQA' . random_int(100000, 999999),
                'password'   => \Illuminate\Support\Facades\Hash::make('secret-not-used'),
                'status'     => 'active',
            ]);
        }
        if (!$meenu->hasAnyRole(['employee', 'office_employee', 'manager', 'admin', 'hr'])) {
            $meenu->assignRole('employee');
        }

        $html = $this->actingAs($meenu)->get('/user/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="ctcDisposition"', $html);
        $this->assertStringNotContainsString('id="ctcDispose"', $html);
        $this->assertStringContainsString('id="ctcHangup"', $html, 'Hang Up should still show — it is pending, not permanently unsupported');
        $this->assertStringContainsString('id="ctcDial"', $html);
    }

    /** The discriminator: Mehak's disposition section — genuinely supported by CentroVision — must still show. */
    public function test_the_disposition_section_still_shows_for_the_centrovision_agent(): void
    {
        Mail::fake();
        $agent = User::where('email', 'bhatiamehak52@gmail.com')->firstOrFail();

        $html = $this->actingAs($agent)->get('/user/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('id="ctcDisposition"', $html);
        $this->assertStringContainsString('id="ctcDispose"', $html);
    }
}
