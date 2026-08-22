<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The dialer is a third-party box on someone else's network, and its reply
 * reaches the log file, a JSON response and the dashboard. Treat it as
 * untrusted input and prove the scrubbing holds.
 */
class ClickToCallSafetyTest extends TestCase
{
    use DatabaseTransactions;

    private function agent(): User
    {
        return User::where('email', 'bhatiamehak52@gmail.com')->firstOrFail();
    }

    public function test_markup_in_a_reply_never_reaches_the_response(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('<script>alert(1)</script>Error', 200)]);

        $body = $this->actingAs($this->agent())
            ->postJson('/user/click-to-call', ['phone' => '7889041267'])
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('alert(1)', $body);
    }

    /** The dialer's own bytes are never relayed to the browser at all. */
    public function test_the_raw_reply_is_not_returned_to_the_browser(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('Some unexpected <b>vendor</b> chatter', 200)]);

        $json = $this->actingAs($this->agent())
            ->postJson('/user/click-to-call', ['phone' => '7889041267'])
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('raw', $json);
        $this->assertArrayHasKey('message', $json);
    }

    /** Embedded newlines would let a hostile reply forge extra log lines. */
    public function test_control_characters_are_stripped(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response("Error\n\r[2026-01-01] production.INFO: forged", 200)]);

        $raw = app(\App\Services\ClickToCallService::class)
            ->hangup($this->agent())['raw'];

        $this->assertStringNotContainsString("\n", $raw);
        $this->assertStringNotContainsString("\r", $raw);
    }

    /** A compromised endpoint should not be able to push megabytes at us. */
    public function test_an_oversized_reply_is_truncated(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response(str_repeat('A', 500000), 200)]);

        $raw = app(\App\Services\ClickToCallService::class)
            ->hangup($this->agent())['raw'];

        $this->assertLessThanOrEqual(2000, mb_strlen($raw));
    }

    /** Dropdown entries are sent back to the dialer, so odd ones are dropped. */
    public function test_suspicious_list_entries_are_discarded(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('Callback|<img src=x onerror=alert(1)>|TESTCALL|"quoted"|ok_one', 200)]);

        $this->actingAs($this->agent())
            ->getJson('/user/click-to-call/dispositions')
            ->assertOk()
            ->assertJson(['dispositions' => ['Callback', 'TESTCALL', 'ok_one']]);
    }

    public function test_a_flooded_list_is_capped(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response(implode('|', array_fill(0, 5000, 'Disp')), 200)]);

        $items = $this->actingAs($this->agent())
            ->getJson('/user/click-to-call/dispositions')
            ->assertOk()
            ->json('dispositions');

        $this->assertLessThanOrEqual(200, count($items));
    }

    /** A disposition the campaign does not offer must not be relayed. */
    public function test_an_unknown_disposition_is_rejected_before_dialling_out(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('Callback|ldhccare|TESTCALL', 200)]);

        $this->actingAs($this->agent())
            ->postJson('/user/click-to-call/dispose', ['disposition' => 'Something Invented'])
            ->assertStatus(422)
            ->assertJson(['result' => 'invalid']);

        // Only the list lookup went out — never a DISPOSE.
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'ACTION=DISPOSE'));
    }

    public function test_a_known_disposition_still_goes_through(): void
    {
        Mail::fake();
        Http::fakeSequence()
            ->push('Callback|ldhccare|TESTCALL', 200)   // the validation lookup
            ->push('Call disposed (success)', 200);     // the DISPOSE itself

        $this->actingAs($this->agent())
            ->postJson('/user/click-to-call/dispose', ['disposition' => 'TESTCALL'])
            ->assertOk()
            ->assertJson(['result' => 'success']);
    }
}
