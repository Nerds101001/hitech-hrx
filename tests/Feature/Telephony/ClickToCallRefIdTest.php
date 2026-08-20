<?php

namespace Tests\Feature\Telephony;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every click-to-call dial carries a correlation id.
 *
 * ref_id is what ties a click in the HRX UI to a CDR row hours later.
 * CentroVision does not echo it back yet -- that is question 5 on the vendor
 * questionnaire -- but HRX has to be sending it before they can start, and
 * CallEventNormalizer already stores whatever arrives on the webhook, so
 * nothing else changes here once they do.
 *
 * Deliberately extends Tests\TestCase rather than TelephonyTestCase: this
 * exercises the live click-to-call path, which reads a real mapped agent out
 * of the database, so it belongs on the normal connection with
 * DatabaseTransactions like the rest of the click-to-call suite.
 *
 * Http::fake throughout -- a real request here would ring a real phone.
 * Mail::fake because HRX lifecycle listeners mail real people as a side effect.
 */
class ClickToCallRefIdTest extends TestCase
{
  use DatabaseTransactions;

  /** The one agent mapped in config/access.php. */
  private const AGENT_EMAIL = 'bhatiamehak52@gmail.com';

  private function agent(): User
  {
    return User::where('email', self::AGENT_EMAIL)->firstOrFail();
  }

  protected function setUp(): void
  {
    parent::setUp();

    Mail::fake();

    if (!User::where('email', self::AGENT_EMAIL)->exists()) {
      $this->markTestSkipped('The mapped click-to-call agent is not present in this database.');
    }
  }

  public function test_a_dial_carries_a_ref_id(): void
  {
    Http::fake(['*' => Http::response('Success to dial', 200)]);

    $response = $this->actingAs($this->agent())
      ->postJson('/user/click-to-call', ['phone' => '7889041267'])
      ->assertOk();

    $refId = $response->json('ref_id');

    $this->assertNotEmpty($refId, 'the caller needs the ref_id to correlate this dial later');
    $this->assertMatchesRegularExpression(
      '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
      $refId,
      'ref_id must be a UUID -- the vendor contract types it as one'
    );

    Http::assertSent(fn ($r) => str_contains($r->url(), 'ACTION=DIAL')
      && str_contains($r->url(), 'ref_id=' . $refId));
  }

  /**
   * One dial from the agent's point of view is one ref_id, even though the
   * mode fix sends two requests to the gateway. Minting a second id on the
   * retry would leave the refused attempt correlating to nothing.
   */
  public function test_the_mode_fix_retry_reuses_the_same_ref_id(): void
  {
    Http::fakeSequence()
      ->push('Agent not in manual/progressive mode', 200)  // first dial refused
      ->push('2=Manual|1=Auto', 200)                       // mode list
      ->push('Agent mode changed', 200)                    // switched to manual
      ->push('Success to dial', 200);                      // retried

    $response = $this->actingAs($this->agent())
      ->postJson('/user/click-to-call', ['phone' => '7889041267'])
      ->assertOk()
      ->assertJson(['result' => 'success', 'mode_fix_attempted' => true]);

    $refId = $response->json('ref_id');
    $this->assertNotEmpty($refId);

    $dialsWithThisRef = 0;
    Http::assertSent(function ($r) use ($refId, &$dialsWithThisRef) {
      if (str_contains($r->url(), 'ACTION=DIAL') && str_contains($r->url(), 'ref_id=' . $refId)) {
        $dialsWithThisRef++;
      }

      return true;
    });

    $this->assertSame(2, $dialsWithThisRef, 'both the refused dial and the retry must share one ref_id');
  }
}
