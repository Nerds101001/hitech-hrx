<?php

namespace Tests\Feature\Telephony;

use App\Models\Telephony\TelephonyAgentMap;
use App\Models\Telephony\TelephonyAgentSession;
use App\Models\Telephony\TelephonyCall;
use App\Models\Telephony\TelephonyCallLeg;
use App\Models\Telephony\TelephonyRecording;
use App\Models\Telephony\TelephonyWebhookDelivery;
use App\Services\Telephony\CallEventNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

/**
 * Covers the things that must never regress: a forged request is rejected, a
 * replayed event is not written twice, a late event cannot overwrite a newer
 * one, and a manual handset dial still lands against the right employee.
 *
 * The schema is provisioned here rather than by RefreshDatabase because the
 * repository's full migration set does not build from an empty database -
 * 2024_03_07_000001_add_type_to_custom_questions alters a table that no
 * migration creates. Provisioning only what is under test keeps this suite
 * runnable in CI regardless of that.
 */
class TelephonyIngestTest extends TelephonyTestCase
{
  private string $secret = 'test-shared-secret';
  private string $key = 'test-api-key';

  protected function setUp(): void
  {
    parent::setUp();

    config([
      'telephony.inbound.api_key' => $this->key,
      'telephony.inbound.secrets' => [$this->secret],
      'telephony.inbound.allowed_ips' => [],
      'telephony.inbound.max_skew_seconds' => 300,
      'telephony.inbound.nonce_ttl_seconds' => 600,
      'telephony.inbound.max_batch' => 500,
      'telephony.inbound.default_tenant_id' => 'default',
      'telephony.vendor_timezone' => 'Asia/Kolkata',
      'telephony.queue' => 'sync',
      'queue.default' => 'sync',
    ]);

    $this->provisionSchema();
  }

  private function provisionSchema(): void
  {
    Schema::create('users', function (Blueprint $t) {
      $t->id();
      $t->string('tenant_id')->nullable();
      $t->string('code')->nullable();
      $t->string('first_name')->nullable();
      $t->string('email')->nullable();
      $t->unsignedBigInteger('reporting_to_id')->nullable();
      $t->timestamps();
    });

    foreach (glob(database_path('migrations/2026_08_19_10*.php')) as $file) {
      (require $file)->up();
    }
  }

  // ─────────────────────────────────────────────────────── transport security

  public function test_health_returns_ok_for_a_correctly_signed_request(): void
  {
    $this->signed('GET', '/api/telephony/v1/health')
      ->assertOk()
      ->assertJsonPath('status', 'ok')
      ->assertJsonPath('api_version', 'v1');
  }

  public function test_a_tampered_signature_is_rejected_and_writes_nothing(): void
  {
    $raw = json_encode(['event' => 'call.originated', 'event_id' => (string) Str::uuid()]);

    // Everything else valid - only the signature is wrong. Sending a bad
    // signature with no timestamp would fail on the cheaper check first and
    // prove nothing about the HMAC.
    $this->raw('POST', '/api/telephony/v1/events', $raw, [
      'X-HRX-Timestamp' => (string) time(),
      'X-HRX-Nonce' => (string) Str::uuid(),
      'X-HRX-Signature' => 'sha256=deadbeef',
    ])->assertStatus(401)->assertJsonPath('error.code', 'invalid_signature');

    $this->assertSame(0, TelephonyWebhookDelivery::count());
  }

  public function test_a_wrong_api_key_is_rejected(): void
  {
    $this->signed('GET', '/api/telephony/v1/health', [], ['Authorization' => 'Bearer wrong-key'])
      ->assertStatus(401)
      ->assertJsonPath('error.code', 'invalid_api_key');
  }

  public function test_a_stale_timestamp_is_rejected(): void
  {
    $raw = json_encode(['event' => 'call.originated', 'event_id' => (string) Str::uuid()]);
    $ts = (string) (time() - 3600);
    $nonce = (string) Str::uuid();

    $this->raw('POST', '/api/telephony/v1/events', $raw, [
      'X-HRX-Timestamp' => $ts,
      'X-HRX-Nonce' => $nonce,
      'X-HRX-Signature' => 'sha256=' . hash_hmac('sha256', "{$ts}.{$nonce}.{$raw}", $this->secret),
    ])->assertStatus(401)->assertJsonPath('error.code', 'stale_timestamp');
  }

  public function test_a_reused_nonce_is_rejected(): void
  {
    $raw = json_encode($this->completedCallPayload());
    $ts = (string) time();
    $nonce = (string) Str::uuid();
    $headers = [
      'X-HRX-Timestamp' => $ts,
      'X-HRX-Nonce' => $nonce,
      'X-HRX-Signature' => 'sha256=' . hash_hmac('sha256', "{$ts}.{$nonce}.{$raw}", $this->secret),
    ];

    $this->raw('POST', '/api/telephony/v1/events', $raw, $headers)->assertStatus(202);
    $this->raw('POST', '/api/telephony/v1/events', $raw, $headers)
      ->assertStatus(401)
      ->assertJsonPath('error.code', 'replayed_nonce');
  }

  public function test_an_oversized_body_is_refused_before_it_is_stored(): void
  {
    config(['telephony.inbound.max_body_bytes' => 1024]);

    $payload = $this->completedCallPayload();
    $payload['remarks'] = str_repeat('A', 4096);

    $this->signed('POST', '/api/telephony/v1/events', $payload)
      ->assertStatus(413)
      ->assertJsonPath('error.code', 'payload_too_large');

    // The point of checking in the middleware rather than the controller: an
    // oversized body must never reach the longText payload column at all.
    $this->assertSame(0, TelephonyWebhookDelivery::count());
  }

  public function test_a_normal_event_is_unaffected_by_the_size_cap(): void
  {
    $payload = $this->completedCallPayload();

    $this->signed('POST', '/api/telephony/v1/events', $payload)->assertStatus(202);

    $this->assertSame(1, TelephonyCall::where('call_uid', $payload['call_uid'])->count());
  }

  // ──────────────────────────────────────────────────────────── idempotency

  public function test_a_replayed_event_id_is_accepted_once_then_reported_duplicate(): void
  {
    $event = $this->completedCallPayload();

    $this->signed('POST', '/api/telephony/v1/events', $event)->assertStatus(202);
    $this->signed('POST', '/api/telephony/v1/events', $event)
      ->assertStatus(200)
      ->assertJsonPath('status', 'duplicate');

    $this->assertSame(1, TelephonyWebhookDelivery::where('event_id', $event['event_id'])->count());
    $this->assertSame(1, TelephonyCall::where('call_uid', $event['call_uid'])->count());
  }

  public function test_a_malformed_payload_is_stored_and_rejected(): void
  {
    $this->signed('POST', '/api/telephony/v1/events', ['event' => 'call.originated'])
      ->assertStatus(422);

    $this->assertSame(1, TelephonyWebhookDelivery::where('status', 'rejected')->count());
  }

  // ───────────────────────────────────────────────────────────── normalising

  public function test_a_click_to_call_produces_a_complete_cdr_row(): void
  {
    $payload = $this->completedCallPayload();
    $payload['origin'] = 'api_click_to_call';
    $payload['ref_id'] = (string) Str::uuid();

    $this->signed('POST', '/api/telephony/v1/events', $payload)->assertStatus(202);

    $call = TelephonyCall::where('call_uid', $payload['call_uid'])->firstOrFail();

    $this->assertSame('answered', $call->status);
    $this->assertSame('customer', $call->hangup_by);
    $this->assertSame(16, $call->hangup_cause_code);
    $this->assertSame(12, $call->customer_ring_seconds);
    $this->assertSame(198, $call->talk_seconds);
    $this->assertSame($payload['ref_id'], $call->ref_id);
    $this->assertSame('NI', $call->disposition_code);
    $this->assertTrue($call->is_complete);
  }

  public function test_the_same_instant_lands_identically_whatever_offset_it_arrives_in(): void
  {
    $ist = $this->completedCallPayload();
    $this->signed('POST', '/api/telephony/v1/events', $ist)->assertStatus(202);

    $call = TelephonyCall::where('call_uid', $ist['call_uid'])->firstOrFail();

    // 14:58:22+05:30 is 09:28:22Z. Both must resolve to the same moment.
    $this->assertSame('2026-08-19 09:28:22', $call->answered_at->utc()->format('Y-m-d H:i:s'));
    $this->assertSame('2026-08-19 09:31:40', $call->ended_at->utc()->format('Y-m-d H:i:s'));

    $utc = $this->completedCallPayload();
    $utc['call_uid'] = 'CV-UTC-1';
    $utc['timestamps']['answered_at'] = '2026-08-19T09:28:22Z';
    $utc['timestamps']['ended_at'] = '2026-08-19T09:31:40Z';
    $this->signed('POST', '/api/telephony/v1/events', $utc)->assertStatus(202);

    $second = TelephonyCall::where('call_uid', 'CV-UTC-1')->firstOrFail();

    $this->assertTrue(
      $call->answered_at->equalTo($second->answered_at),
      'a gateway switching to UTC must not shift call times by 5.5 hours'
    );
  }

  public function test_a_manual_dial_resolves_to_the_employee_by_extension_alone(): void
  {
    $userId = $this->makeUser('EMP-096');

    TelephonyAgentMap::create([
      'tenant_id' => 'default',
      'user_id' => $userId,
      'agent_id' => 'AG-1042',
      'agent_code' => 'mehak',
      'extension' => '1042',
      'agent_number' => '+919812345678',
      'is_active' => true,
    ]);

    $payload = $this->completedCallPayload();
    $payload['origin'] = 'manual_dial';
    $payload['ref_id'] = null;
    $payload['agent'] = ['extension' => '1042']; // a handset dial may report nothing else

    app(CallEventNormalizer::class)->handle($payload);

    $call = TelephonyCall::where('call_uid', $payload['call_uid'])->firstOrFail();

    $this->assertSame($userId, $call->user_id);
    $this->assertFalse((bool) $call->is_unassigned);
    $this->assertSame('manual_dial', $call->origin);
    $this->assertNull($call->ref_id);
  }

  public function test_an_unknown_agent_is_stored_as_unassigned_rather_than_dropped(): void
  {
    $payload = $this->completedCallPayload();
    $payload['agent'] = ['extension' => '9999'];

    app(CallEventNormalizer::class)->handle($payload);

    $call = TelephonyCall::where('call_uid', $payload['call_uid'])->firstOrFail();

    $this->assertNull($call->user_id);
    $this->assertTrue((bool) $call->is_unassigned);
  }

  public function test_a_late_lifecycle_event_cannot_overwrite_the_completed_cdr(): void
  {
    $completed = $this->completedCallPayload();
    $completed['sequence'] = 9;
    app(CallEventNormalizer::class)->handle($completed);

    app(CallEventNormalizer::class)->handle([
      'event' => 'call.answered',
      'event_id' => (string) Str::uuid(),
      'occurred_at' => '2026-08-19T14:58:22+05:30',
      'sequence' => 4,
      'call_uid' => $completed['call_uid'],
      'origin' => 'inbound',
      'direction' => 'inbound',
    ]);

    $call = TelephonyCall::where('call_uid', $completed['call_uid'])->firstOrFail();

    $this->assertSame('answered', $call->status);
    $this->assertSame(198, $call->talk_seconds);
    $this->assertSame('manual_dial', $call->origin, 'a stale event must not rewrite origin');
    $this->assertSame(9, (int) $call->last_sequence);
  }

  public function test_transfer_conference_and_forwarding_create_linked_legs(): void
  {
    $callUid = 'CV-20260819-000481';

    app(CallEventNormalizer::class)->handle([
      'event' => 'call.transfer.completed',
      'event_id' => (string) Str::uuid(),
      'occurred_at' => '2026-08-19T14:34:02+05:30',
      'sequence' => 6,
      'call_uid' => $callUid,
      'transfer' => [
        'type' => 'attended',
        'from_agent_id' => 'AG-1042',
        'to_agent_id' => 'AG-1077',
        'to_extension' => '1077',
        'transferred_at' => '2026-08-19T14:34:02+05:30',
        'child_call_uid' => 'CV-20260819-000482',
        'consult_seconds' => 18,
        'recording_uid' => 'REC-TL-20260819-000482',
      ],
    ]);

    app(CallEventNormalizer::class)->handle([
      'event' => 'call.conference.participant_joined',
      'event_id' => (string) Str::uuid(),
      'occurred_at' => '2026-08-19T14:35:20+05:30',
      'sequence' => 7,
      'call_uid' => $callUid,
      'conference' => [
        'conference_id' => 'CONF-20260819-0033',
        'started_at' => '2026-08-19T14:35:05+05:30',
        'recording_uid' => 'REC-CF-20260819-0033',
        'participants' => [
          ['number' => '+919812345678', 'role' => 'agent', 'joined_at' => '2026-08-19T14:35:05+05:30'],
          ['number' => '+919876543210', 'role' => 'customer', 'joined_at' => '2026-08-19T14:35:05+05:30'],
          ['number' => '+919812300099', 'role' => 'supervisor', 'joined_at' => '2026-08-19T14:35:20+05:30'],
        ],
      ],
    ]);

    app(CallEventNormalizer::class)->handle([
      'event' => 'call.forwarded',
      'event_id' => (string) Str::uuid(),
      'occurred_at' => '2026-08-19T14:36:00+05:30',
      'sequence' => 8,
      'call_uid' => $callUid,
      'forwarding' => [
        'type' => 'no_answer',
        'from_number' => '+919812345678',
        'to_number' => '+919812300055',
        'forwarded_at' => '2026-08-19T14:36:00+05:30',
        'child_call_uid' => 'CV-20260819-000491',
      ],
    ]);

    $call = TelephonyCall::where('call_uid', $callUid)->firstOrFail();

    $this->assertTrue((bool) $call->was_transferred);
    $this->assertTrue((bool) $call->was_conferenced);
    $this->assertTrue((bool) $call->was_forwarded);

    $this->assertSame(3, TelephonyCallLeg::where('call_uid', $callUid)->count());

    $transfer = TelephonyCallLeg::where('call_uid', $callUid)->where('leg_type', 'transfer')->firstOrFail();
    $this->assertSame('attended', $transfer->sub_type);
    $this->assertSame(18, (int) $transfer->consult_seconds);
    $this->assertSame('CV-20260819-000482', $transfer->child_call_uid);

    $conference = TelephonyCallLeg::where('call_uid', $callUid)->where('leg_type', 'conference')->firstOrFail();
    $this->assertCount(3, $conference->participants);
  }

  public function test_a_queue_abandon_is_recorded_as_abandoned(): void
  {
    app(CallEventNormalizer::class)->handle([
      'event' => 'queue.abandoned',
      'event_id' => (string) Str::uuid(),
      'occurred_at' => '2026-08-19T15:12:31+05:30',
      'sequence' => 2,
      'call_uid' => 'CV-20260819-000525',
      'origin' => 'inbound',
      'direction' => 'inbound',
      'caller_number' => '+919812311111',
      'queue_name' => 'CustomerCare',
      'wait_seconds' => 96,
      'abandon_point' => 'queue',
    ]);

    $call = TelephonyCall::where('call_uid', 'CV-20260819-000525')->firstOrFail();

    $this->assertSame('abandoned', $call->status);
    $this->assertSame(96, $call->queue_wait_seconds);
    $this->assertSame('CustomerCare', $call->queue);
    $this->assertTrue((bool) $call->is_complete);
  }

  public function test_a_later_event_without_an_agent_block_does_not_unassign_the_call(): void
  {
    $userId = $this->makeUser('EMP-096');

    TelephonyAgentMap::create([
      'tenant_id' => 'default',
      'user_id' => $userId,
      'agent_id' => 'AG-1042',
      'extension' => '1042',
      'is_active' => true,
    ]);

    $completed = $this->completedCallPayload();
    app(CallEventNormalizer::class)->handle($completed);

    $this->assertFalse(
      (bool) TelephonyCall::where('call_uid', $completed['call_uid'])->value('is_unassigned')
    );

    // Forwarding, queue and conference events legitimately carry no agent
    // block at all. Recomputing assignment from an absent agent used to flip a
    // correctly-resolved call back onto the exceptions screen.
    app(CallEventNormalizer::class)->handle([
      'event' => 'call.forwarded',
      'event_id' => (string) Str::uuid(),
      'occurred_at' => '2026-08-19T15:02:00+05:30',
      'sequence' => 7,
      'call_uid' => $completed['call_uid'],
      'forwarding' => [
        'type' => 'no_answer',
        'from_number' => '+919812345678',
        'to_number' => '+919812300055',
        'forwarded_at' => '2026-08-19T15:02:00+05:30',
      ],
    ]);

    $call = TelephonyCall::where('call_uid', $completed['call_uid'])->firstOrFail();

    $this->assertSame($userId, $call->user_id);
    $this->assertFalse((bool) $call->is_unassigned, 'an agent-less event must not unassign a resolved call');
  }

  public function test_an_abandoned_call_still_gets_an_initiated_at(): void
  {
    // telephony:reconcile windows the day on initiated_at. An abandoned call
    // without one is invisible to every local count, so the nightly run would
    // report a gap that no backfill could ever close.
    app(CallEventNormalizer::class)->handle([
      'event' => 'queue.abandoned',
      'event_id' => (string) Str::uuid(),
      'occurred_at' => '2026-08-19T15:12:31+05:30',
      'sequence' => 2,
      'call_uid' => 'CV-ABANDON-1',
      'origin' => 'inbound',
      'direction' => 'inbound',
      'queue_name' => 'CustomerCare',
      'wait_seconds' => 96,
    ]);

    $call = TelephonyCall::where('call_uid', 'CV-ABANDON-1')->firstOrFail();

    $this->assertSame('abandoned', $call->status);
    $this->assertNotNull($call->initiated_at, 'an abandoned call with no initiated_at is invisible to reconciliation');
  }

  // ────────────────────────────────────────────────────────────── recordings

  public function test_recording_ready_stores_metadata_and_flags_the_call(): void
  {
    $payload = $this->completedCallPayload();
    unset($payload['recording']);
    app(CallEventNormalizer::class)->handle($payload);

    $this->signed('POST', '/api/telephony/v1/recordings/ready', [
      'event' => 'recording.ready',
      'event_id' => (string) Str::uuid(),
      'occurred_at' => '2026-08-19T15:02:10+05:30',
      'call_uid' => $payload['call_uid'],
      'recording_uid' => 'REC-20260819-000512',
      'recording_type' => 'call',
      'duration_seconds' => 198,
      'format' => 'wav',
      'channels' => 'stereo',
      'size_bytes' => 3168000,
      'retention_until' => '2027-08-19',
    ])->assertStatus(202);

    $recording = TelephonyRecording::where('recording_uid', 'REC-20260819-000512')->firstOrFail();
    $this->assertSame(198, $recording->duration_seconds);
    $this->assertSame('stereo', $recording->channels);

    $this->assertTrue(
      (bool) TelephonyCall::where('call_uid', $payload['call_uid'])->value('has_recording')
    );
  }

  // ─────────────────────────────────────────────────────────── agent sessions

  public function test_agent_logout_stores_the_vendor_session_totals(): void
  {
    $userId = $this->makeUser('EMP-096');

    TelephonyAgentMap::create([
      'tenant_id' => 'default',
      'user_id' => $userId,
      'agent_id' => 'AG-1042',
      'extension' => '1042',
      'is_active' => true,
    ]);

    $this->signed('POST', '/api/telephony/v1/agent-sessions', [
      'event' => 'agent.logout',
      'event_id' => (string) Str::uuid(),
      'occurred_at' => '2026-08-19T19:02:11+05:30',
      'session_id' => 'SES-20260819-1042-01',
      'agent' => ['agent_id' => 'AG-1042', 'extension' => '1042'],
      'login_at' => '2026-08-19T09:58:40+05:30',
      'logout_at' => '2026-08-19T19:02:11+05:30',
      'mode' => 'manual',
      'totals' => [
        'login_seconds' => 32611,
        'ready_seconds' => 14980,
        'talk_seconds' => 9120,
        'hold_seconds' => 340,
        'wrap_seconds' => 2210,
        'idle_seconds' => 3105,
        'break_seconds' => 2856,
        'occupancy_percent' => 41.2,
        'calls_handled' => 63,
        'calls_missed' => 4,
      ],
      'breaks' => [
        ['reason' => 'Lunch', 'from' => '2026-08-19T13:30:00+05:30', 'to' => '2026-08-19T14:05:12+05:30', 'seconds' => 2112],
      ],
    ])->assertStatus(202);

    $session = TelephonyAgentSession::where('session_id', 'SES-20260819-1042-01')->firstOrFail();

    $this->assertSame($userId, $session->user_id);
    $this->assertSame(32611, $session->login_seconds);
    $this->assertSame(2856, $session->break_seconds);
    $this->assertSame(29755, $session->activeSeconds());
    $this->assertFalse((bool) $session->is_open);
    $this->assertCount(1, $session->breaks);
  }

  // ───────────────────────────────────────────────────────────────── batching

  public function test_a_batch_returns_a_per_item_result(): void
  {
    $good = $this->completedCallPayload();
    $good['call_uid'] = 'CV-BATCH-1';

    $response = $this->signed('POST', '/api/telephony/v1/events/batch', [
      'events' => [$good, ['event' => 'call.completed']], // second has no event_id
    ])->assertStatus(207);

    $response->assertJsonPath('accepted', 1)->assertJsonPath('rejected', 1);
    $this->assertSame(1, TelephonyCall::where('call_uid', 'CV-BATCH-1')->count());
  }

  // ─────────────────────────────────────────────────────────────── helpers

  private function makeUser(string $code): int
  {
    return (int) DB::table('users')->insertGetId([
      'tenant_id' => 'default',
      'code' => $code,
      'first_name' => 'MEHAK',
      'email' => strtolower($code) . '@example.test',
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }

  /** @return array<string,mixed> */
  private function completedCallPayload(): array
  {
    return [
      'event' => 'call.completed',
      'event_id' => (string) Str::uuid(),
      'occurred_at' => '2026-08-19T15:01:44+05:30',
      'sequence' => 6,
      'call_uid' => 'CV-20260819-000512',
      'ref_id' => null,
      'origin' => 'manual_dial',
      'direction' => 'outbound',
      'mode' => 'manual',
      'agent' => ['agent_id' => 'AG-1042', 'extension' => '1042'],
      'caller_number' => '+919812345678',
      'callee_number' => '+919812309876',
      'status' => 'answered',
      'hangup_by' => 'customer',
      'hangup_cause' => 'NORMAL_CLEARING',
      'hangup_cause_code' => 16,
      'timestamps' => [
        'initiated_at' => '2026-08-19T14:58:02+05:30',
        'customer_ring_at' => '2026-08-19T14:58:10+05:30',
        'answered_at' => '2026-08-19T14:58:22+05:30',
        'ended_at' => '2026-08-19T15:01:40+05:30',
      ],
      'durations' => [
        'customer_ring_seconds' => 12,
        'pdd_seconds' => 8,
        'talk_seconds' => 198,
        'hold_seconds' => 0,
        'wrap_seconds' => 4,
        'total_seconds' => 218,
      ],
      'disposition' => ['code' => 'NI', 'name' => 'Not Interested'],
      'recording' => ['available' => true, 'recording_uid' => 'REC-20260819-000512'],
    ];
  }

  private function signed(string $method, string $uri, array $body = [], array $override = [])
  {
    $raw = $body === [] ? '' : json_encode($body);
    $ts = (string) time();
    $nonce = (string) Str::uuid();

    return $this->raw($method, $uri, $raw, array_merge([
      'X-HRX-Timestamp' => $ts,
      'X-HRX-Nonce' => $nonce,
      'X-HRX-Signature' => 'sha256=' . hash_hmac('sha256', "{$ts}.{$nonce}.{$raw}", $this->secret),
      'Idempotency-Key' => $body['event_id'] ?? null,
    ], $override));
  }

  private function raw(string $method, string $uri, string $raw, array $headers = [])
  {
    $headers = array_filter(array_merge([
      'Authorization' => 'Bearer ' . $this->key,
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
    ], $headers));

    // call() ignores withHeaders(), so the headers must be turned into server
    // vars explicitly. Using call() rather than postJson() matters: postJson
    // re-encodes the body, which would change the bytes the signature covers.
    $server = $this->transformHeadersToServerVars($headers);
    $server['CONTENT_TYPE'] = 'application/json';

    return $this->call($method, $uri, [], [], [], $server, $raw);
  }
}
