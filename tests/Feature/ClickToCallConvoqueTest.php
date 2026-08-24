<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ClickToCallService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Click to call for the one agent (Meenu / newbiz@hitechgroup.in) wired to
 * Convoque instead of the shared CentroVision gateway everyone else uses.
 *
 * Convoque's whole API is one GET returning JSON — nothing like CentroVision's
 * plain-text-body, ACTION=-driven shape — so this is a completely separate
 * path through ClickToCallService, not a variant of the CentroVision one. The
 * thing that actually matters here is that adding it did not touch what
 * ClickToCallTest already covers for Mehak; that file staying green alongside
 * this one is the real proof.
 *
 * Http::fake throughout: a real request here would ring a real phone.
 */
class ClickToCallConvoqueTest extends TestCase
{
    use DatabaseTransactions;

    private const AGENT_EMAIL = 'newbiz@hitechgroup.in';

    private ClickToCallService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->service = app(ClickToCallService::class);

        if (!User::where('email', self::AGENT_EMAIL)->exists()) {
            User::create([
                'first_name' => 'Meenu', 'last_name' => 'QA',
                'email'      => self::AGENT_EMAIL,
                'code'       => 'MEENU' . random_int(1000, 9999),
                'password'   => Hash::make('secret-not-used'),
                'status'     => 'active',
            ]);
        }
    }

    private function agent(): User
    {
        return User::where('email', self::AGENT_EMAIL)->firstOrFail();
    }

    public function test_the_convoque_agent_can_place_a_call(): void
    {
        Http::fake(['*' => Http::response(
            '{"status":"success","traceID":"abc123","message":"Call request placed successfully"}',
            200
        )]);

        $result = $this->service->dial($this->agent(), '7889041267');

        $this->assertSame('success', $result['result']);
        $this->assertArrayHasKey('ref_id', $result);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'placeACall_cri.jsp')
            && str_contains($r->url(), 'src=217')
            && str_contains($r->url(), 'dst=7889041267')
            && str_contains($r->url(), 'authCode='));
    }

    /** The discriminator: a refusal from Convoque must not be reported as success. */
    public function test_a_convoque_refusal_is_not_reported_as_success(): void
    {
        Http::fake(['*' => Http::response('{"status":"failed","message":"invalid auth code"}', 200)]);

        $result = $this->service->dial($this->agent(), '7889041267');

        $this->assertSame('error', $result['result']);
    }

    public function test_an_unreachable_convoque_dialer_is_reported_plainly(): void
    {
        Http::fake(['*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $result = $this->service->dial($this->agent(), '7889041267');

        $this->assertSame('unreachable', $result['result']);
    }

    /**
     * Actions with no Convoque equivalent must fail soft — not silently send
     * CentroVision-shaped params (campaign/agent/client_id, none of which a
     * Convoque agent's config has) to Convoque's endpoint, and not throw.
     */
    public function test_actions_convoque_has_no_equivalent_for_are_reported_as_unsupported(): void
    {
        Http::fake(); // asserted against below — nothing should be sent at all

        $this->assertSame('unsupported', $this->service->hangup($this->agent())['result']);
        $this->assertSame('unsupported', $this->service->dispose($this->agent(), 'Interested')['result']);
        $this->assertSame('unsupported', $this->service->lineStatus($this->agent())['result']);
        $this->assertSame([], $this->service->modeList($this->agent()));
        $this->assertSame([], $this->service->dispositions($this->agent()));
        $this->assertSame([], $this->service->subDispositions($this->agent(), 'Interested'));

        Http::assertNothingSent();
    }

    /** No auth code configured must fail closed, not silently dial with a blank/wrong one. */
    public function test_dialling_without_a_configured_auth_code_fails_closed(): void
    {
        config(['access.click_to_call.convoque.auth_codes' => []]);
        Http::fake();

        $result = $this->service->dial($this->agent(), '7889041267');

        $this->assertSame('unreachable', $result['result']);
        Http::assertNothingSent();
    }

    /** THE ACTUAL POINT of all this: Meenu existing must not change anything about Mehak's flow. */
    public function test_the_convoque_agent_does_not_affect_the_centrovision_agent(): void
    {
        $mehak = User::where('email', 'bhatiamehak52@gmail.com')->first();
        if (!$mehak) {
            $this->markTestSkipped('CentroVision demo agent not seeded locally.');
        }

        Http::fake(['*' => Http::response('Success to dial', 200)]);

        $result = $this->service->dial($mehak, '7889041267');

        $this->assertSame('success', $result['result']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'ACTION=DIAL')
            && str_contains($r->url(), 'campaign=mehak'));
    }
}
