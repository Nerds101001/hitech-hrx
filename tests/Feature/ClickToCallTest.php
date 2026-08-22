<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ClickToCallService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Click to call against the CentroVision agent API.
 *
 * The dialer answers every outcome with HTTP 200 and a plain-text body, so the
 * body is the only signal — a test asserting on status codes would pass while
 * the feature reported a refused call as a placed one.
 *
 * Http::fake throughout: a real request here would ring a real phone.
 */
class ClickToCallTest extends TestCase
{
    use DatabaseTransactions;

    private const AGENT_EMAIL = 'bhatiamehak52@gmail.com';

    private function agent(): User
    {
        return User::where('email', self::AGENT_EMAIL)->firstOrFail();
    }

    private function outsider(): User
    {
        return User::role('employee')->where('email', '!=', self::AGENT_EMAIL)->firstOrFail();
    }

    public function test_the_mapped_agent_can_place_a_call(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('Success to dial', 200)]);

        $this->actingAs($this->agent())
            ->postJson('/user/click-to-call', ['phone' => '7889041267'])
            ->assertOk()
            ->assertJson(['result' => 'success']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'ACTION=DIAL')
            && str_contains($r->url(), 'campaign=mehak')
            && str_contains($r->url(), 'agent=mehak')
            && str_contains($r->url(), 'client_id=8006')
            && str_contains($r->url(), 'phone=7889041267'));
    }

    /**
     * The headline behaviour: a campaign left in auto mode refuses the dial, so
     * switch to manual and dial again rather than sending the agent off to
     * another application.
     */
    public function test_a_wrong_mode_is_fixed_and_the_call_retried(): void
    {
        Mail::fake();

        Http::fakeSequence()
            ->push('Agent not in manual or progressive mode', 200)   // first DIAL
            ->push('1=Auto|2=Manual|3=Callback|4=Break|5=Progressive', 200) // LIST mode
            ->push('Agent mode changed', 200)                        // MODE
            ->push('Success to dial', 200);                          // retried DIAL

        $this->actingAs($this->agent())
            ->postJson('/user/click-to-call', ['phone' => '7889041267'])
            ->assertOk()
            ->assertJson(['result' => 'success', 'mode_fix_attempted' => true]);

        // The mode id must come from the dialer's own list, not a guess.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'ACTION=MODE')
            && str_contains($r->url(), 'mode=2')
            && str_contains($r->url(), 'modetext=Manual'));
    }

    /**
     * The live box says "manual/progressive" where the spec says "manual or
     * progressive". Matching the document's sentence meant this refusal went
     * unrecognised and the automatic mode fix silently never ran — found by
     * dialling production, not by any mock.
     */
    public function test_the_dialers_actual_wrong_mode_wording_is_recognised(): void
    {
        Mail::fake();

        Http::fakeSequence()
            ->push('Agent not in manual/progressive mode', 200)   // exactly what the box returns
            ->push('1=Auto|2=Manual|3=Callback|4=Break', 200)     // and its real mode list
            ->push('Agent mode changed', 200)
            ->push('Success to dial', 200);

        $this->actingAs($this->agent())
            ->postJson('/user/click-to-call', ['phone' => '7889041267'])
            ->assertOk()
            ->assertJson(['result' => 'success', 'mode_fix_attempted' => true]);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'ACTION=MODE') && str_contains($r->url(), 'mode=2'));
    }

    /** A campaign that numbers its modes differently must still be honoured. */
    public function test_the_manual_mode_id_is_read_from_the_dialer(): void
    {
        Mail::fake();

        Http::fakeSequence()
            ->push('Agent not in manual or progressive mode', 200)
            ->push('1=Auto|4=Manual|7=Progressive', 200)   // Manual is 4 here
            ->push('Agent mode changed', 200)
            ->push('Success to dial', 200);

        $this->actingAs($this->agent())
            ->postJson('/user/click-to-call', ['phone' => '7889041267'])
            ->assertOk()
            ->assertJson(['result' => 'success']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'ACTION=MODE') && str_contains($r->url(), 'mode=4'));
    }

    /** If the mode cannot be changed, say why — do not silently retry forever. */
    public function test_a_failed_mode_change_is_reported_and_not_retried(): void
    {
        Mail::fake();

        Http::fakeSequence()
            ->push('Agent not in manual or progressive mode', 200)
            ->push('Agent not login or exist', 200)   // LIST fails
            ->push('Agent not login or exist', 200);  // MODE fails

        $this->actingAs($this->agent())
            ->postJson('/user/click-to-call', ['phone' => '7889041267'])
            ->assertOk()
            ->assertJson(['result' => 'not_logged_in']);

        // Exactly three calls: the dial, the mode list, the mode change. No
        // second dial, because the mode was never fixed.
        Http::assertSentCount(3);
    }

    /** Every non-success reply must be reported as such, not as a placed call. */
    public function test_dialer_refusals_are_not_reported_as_success(): void
    {
        Mail::fake();

        Http::fakeSequence()
            ->push('Agent busy', 200)
            ->push('Agent not login or exist', 200)
            ->push('Error', 200)
            ->push('something nobody documented', 200);

        foreach (['busy', 'not_logged_in', 'error', 'error'] as $expected) {
            $this->actingAs($this->agent())
                ->postJson('/user/click-to-call', ['phone' => '7889041267'])
                ->assertOk()
                ->assertJson(['result' => $expected]);
        }
    }

    public function test_hangup_and_dispose(): void
    {
        Mail::fake();
        Http::fakeSequence()
            ->push('Call hangup', 200)
            ->push('Callback|ldhccare|TESTCALL', 200)   // dispositions, to validate the choice
            ->push('Call disposed (success)', 200);

        $this->actingAs($this->agent())
            ->postJson('/user/click-to-call/hangup')
            ->assertOk()->assertJson(['result' => 'success']);

        $this->actingAs($this->agent())
            ->postJson('/user/click-to-call/dispose', ['disposition' => 'TESTCALL'])
            ->assertOk()->assertJson(['result' => 'success']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'ACTION=DISPOSE')
            && str_contains($r->url(), 'disposition=TESTCALL'));
    }

    /** The campaign has no sub-dispositions, so the parameter must be omitted. */
    public function test_dispose_omits_an_empty_sub_disposition(): void
    {
        Mail::fake();
        Http::fakeSequence()
            ->push('Callback|ldhccare|TESTCALL', 200)   // validation lookup
            ->push('Call disposed (success)', 200);

        $this->actingAs($this->agent())
            ->postJson('/user/click-to-call/dispose', ['disposition' => 'Callback', 'sub_disposition' => ''])
            ->assertOk();

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'ACTION=DISPOSE')
            && str_contains($r->url(), 'sub_disposition'));
    }

    public function test_disposition_list_is_parsed(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('Callback|ldhccare|TESTCALL', 200)]);

        $this->actingAs($this->agent())
            ->getJson('/user/click-to-call/dispositions')
            ->assertOk()
            ->assertJson(['dispositions' => ['Callback', 'ldhccare', 'TESTCALL']]);
    }

    /** A refusal is not a list of dispositions. */
    public function test_a_refusal_does_not_become_a_disposition(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('Agent not login or exist', 200)]);

        $this->actingAs($this->agent())
            ->getJson('/user/click-to-call/dispositions')
            ->assertOk()
            ->assertJson(['dispositions' => []]);
    }

    /** The button is hidden for others — but hiding a button is not a permission. */
    public function test_an_unmapped_user_is_refused_on_every_action(): void
    {
        Mail::fake();
        Http::fake();

        $this->actingAs($this->outsider())->postJson('/user/click-to-call', ['phone' => '7889041267'])->assertStatus(403);
        $this->actingAs($this->outsider())->postJson('/user/click-to-call/hangup')->assertStatus(403);
        $this->actingAs($this->outsider())->postJson('/user/click-to-call/dispose', ['disposition' => 'X'])->assertStatus(403);
        $this->actingAs($this->outsider())->getJson('/user/click-to-call/dispositions')->assertStatus(403);

        Http::assertNothingSent();
    }

    public function test_rubbish_numbers_never_reach_the_dialer(): void
    {
        Mail::fake();
        Http::fake();

        foreach (['', '12345', 'not-a-number'] as $bad) {
            $this->actingAs($this->agent())
                ->postJson('/user/click-to-call', ['phone' => $bad])
                ->assertStatus(422)
                ->assertJson(['result' => 'invalid']);
        }

        Http::assertNothingSent();
    }

    /** People paste "+91 78890-41267" out of a CRM; the dialer wants digits. */
    public function test_pasted_numbers_are_normalised(): void
    {
        $service = app(ClickToCallService::class);

        $this->assertSame('7889041267', $service->normalisePhone('+91 78890-41267'));
        $this->assertSame('7889041267', $service->normalisePhone('917889041267'));
        $this->assertSame('7889041267', $service->normalisePhone('  7889041267  '));
    }

    public function test_an_unreachable_dialer_is_reported_plainly(): void
    {
        Mail::fake();
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $this->actingAs($this->agent())
            ->postJson('/user/click-to-call', ['phone' => '7889041267'])
            ->assertOk()
            ->assertJson(['result' => 'unreachable']);
    }
}
