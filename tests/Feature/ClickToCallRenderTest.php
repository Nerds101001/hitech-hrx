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
}
