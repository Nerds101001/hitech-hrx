<?php

namespace Tests\Feature\Telephony;

use App\Models\Telephony\TelephonyCall;
use App\Models\Telephony\TelephonyReconciliationRun;
use App\Services\Telephony\CallEventNormalizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Exercises telephony:reconcile and CentroVisionClient against a faked gateway.
 *
 * The point of reconciliation is to find calls the webhook never delivered, so
 * the important case is the one where the vendor knows about a call HRX does
 * not - and that HRX writes it rather than merely reporting a gap.
 */
class TelephonyReconcileTest extends TelephonyTestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    config([
      'telephony.inbound.default_tenant_id' => 'default',
      'telephony.vendor.base_url' => 'http://gateway.test/api/v1',
      'telephony.vendor.api_key' => 'vendor-key',
      'telephony.vendor.timeout' => 5,
      'telephony.vendor.connect_timeout' => 2,
      'telephony.vendor.retry_times' => 1,
      'telephony.vendor.retry_sleep_ms' => 1,
      'telephony.vendor.verify_tls' => false,
      'telephony.vendor_timezone' => 'Asia/Kolkata',
      'telephony.reconcile.talk_tolerance_seconds' => 2,
      'telephony.reconcile.page_size' => 500,
    ]);

    Schema::create('users', function (Blueprint $t) {
      $t->id();
      $t->string('tenant_id')->nullable();
      $t->string('code')->nullable();
      $t->unsignedBigInteger('reporting_to_id')->nullable();
      $t->timestamps();
    });

    foreach (array_merge(
      // array_merge, NOT `+`: the union operator keys on index, so the
      // second array's element 0 would be silently discarded.
      glob(database_path('migrations/2026_08_19_10*.php')),
      glob(database_path('migrations/2026_08_2*_1*.php'))
    ) as $file) {
      (require $file)->up();
    }
  }

  public function test_matching_counts_record_a_pass_and_fetch_nothing_further(): void
  {
    $this->seedLocalCall('CV-1', 100);

    Http::fake([
      '*/cdr*' => Http::response(['total_count' => 1, 'next_cursor' => null, 'records' => []]),
    ]);

    $this->artisan('telephony:reconcile', ['--date' => '2026-08-19'])
      ->assertExitCode(0);

    $run = TelephonyReconciliationRun::firstOrFail();

    $this->assertSame('pass', $run->result);
    $this->assertSame(0, $run->count_gap);
    $this->assertSame(0, $run->backfilled);
  }

  public function test_a_call_the_webhook_never_delivered_is_backfilled(): void
  {
    $this->seedLocalCall('CV-1', 100);

    Http::fake([
      '*/cdr*' => Http::response([
        'total_count' => 2,
        'next_cursor' => null,
        'records' => [
          $this->vendorRecord('CV-1', 100),
          $this->vendorRecord('CV-MISSING', 250),
        ],
      ]),
    ]);

    $this->artisan('telephony:reconcile', ['--date' => '2026-08-19'])
      ->assertExitCode(1); // a gap existed, so the run is flagged even after repair

    $run = TelephonyReconciliationRun::firstOrFail();

    $this->assertSame(1, $run->count_gap);
    $this->assertSame(1, $run->backfilled);
    $this->assertSame(['CV-MISSING'], $run->missing_call_uids);
    $this->assertSame('investigate', $run->result);

    $recovered = TelephonyCall::where('call_uid', 'CV-MISSING')->firstOrFail();

    $this->assertSame(250, $recovered->talk_seconds);
    $this->assertTrue((bool) $recovered->via_reconciliation);
    $this->assertTrue((bool) $recovered->is_complete);
  }

  public function test_equal_counts_with_drifted_durations_are_still_caught(): void
  {
    // The whole point of the talk-time check. Counts agreeing proves only that
    // the same NUMBER of calls exists on both sides - a timezone or rounding
    // bug shows up in the durations, not the count. The command used to take a
    // shortcut on this path and compare local talk time against itself, so
    // this could never fail.
    $this->seedLocalCall('CV-1', 100);

    Http::fake([
      '*/cdr*' => Http::response([
        'total_count' => 1,
        'next_cursor' => null,
        'records' => [$this->vendorRecord('CV-1', 900)], // same call, very different talk time
      ]),
    ]);

    $this->artisan('telephony:reconcile', ['--date' => '2026-08-19'])->assertExitCode(1);

    $run = TelephonyReconciliationRun::firstOrFail();

    $this->assertSame(0, $run->count_gap, 'counts agree - only the durations differ');
    $this->assertSame(800, $run->talk_gap_seconds);
    $this->assertSame('investigate', $run->result);
    $this->assertSame(0, $run->backfilled, 'nothing was missing, so nothing should be written');
  }

  public function test_a_gateway_outage_is_recorded_rather_than_throwing(): void
  {
    Http::fake(['*/cdr*' => Http::response('gateway down', 500)]);

    $this->artisan('telephony:reconcile', ['--date' => '2026-08-19'])
      ->assertExitCode(1);

    $run = TelephonyReconciliationRun::firstOrFail();

    $this->assertSame('failed', $run->result);
    $this->assertNotNull($run->error);
  }

  public function test_the_client_sends_the_bearer_token_and_ref_id_on_originate(): void
  {
    Http::fake([
      '*/calls/originate' => Http::response(['call_uid' => 'CV-9', 'ref_id' => 'abc', 'status' => 'queued']),
    ]);

    $client = app(\App\Services\Telephony\CentroVisionClient::class);
    $result = $client->originate('abc', 'AG-1042', '+919876543210', 'CMP-07', '+911244567890');

    $this->assertSame('CV-9', $result['call_uid']);

    Http::assertSent(function ($request) {
      return $request->hasHeader('Authorization', 'Bearer vendor-key')
        && $request['ref_id'] === 'abc'
        && $request['agent_id'] === 'AG-1042'
        && $request['destination'] === '+919876543210'
        && $request['record'] === true;
    });
  }

  public function test_the_day_window_is_the_indian_business_day_not_the_utc_day(): void
  {
    // 03:00 IST on the 19th is 21:30 UTC on the 18th. It belongs to the 19th
    // for an Indian call centre, and the window must be converted to UTC
    // before it hits a UTC-stored column or this call falls outside its day.
    $this->seedLocalCallAt('CV-EARLY', 60, '2026-08-19T03:00:00+05:30');

    Http::fake([
      '*/cdr*' => Http::response(['total_count' => 1, 'next_cursor' => null, 'records' => []]),
    ]);

    $this->artisan('telephony:reconcile', ['--date' => '2026-08-19'])->assertExitCode(0);

    $run = TelephonyReconciliationRun::firstOrFail();

    $this->assertSame(1, $run->local_count, 'an early-morning IST call was missed by the window');
    $this->assertSame(0, $run->count_gap);
  }

  private function seedLocalCallAt(string $uid, int $talk, string $initiatedAt): void
  {
    $record = $this->vendorRecord($uid, $talk);
    $record['timestamps']['initiated_at'] = $initiatedAt;
    $record['event_id'] = (string) Str::uuid();
    $record['occurred_at'] = $initiatedAt;

    app(CallEventNormalizer::class)->handle($record);
  }

  public function test_running_reconcile_twice_does_not_duplicate_the_event_log(): void
  {
    Http::fake([
      '*/cdr*' => Http::response([
        'total_count' => 1,
        'next_cursor' => null,
        'records' => [$this->vendorRecord('CV-MISSING', 250)],
      ]),
    ]);

    $this->artisan('telephony:reconcile', ['--date' => '2026-08-19']);
    $this->artisan('telephony:reconcile', ['--date' => '2026-08-19']);

    $this->assertSame(1, TelephonyCall::where('call_uid', 'CV-MISSING')->count());
    $this->assertSame(
      1,
      \App\Models\Telephony\TelephonyCallEvent::where('call_uid', 'CV-MISSING')->count(),
      'a second reconcile run must not append a duplicate event'
    );
  }

  // ─────────────────────────────────────────────────────────────── helpers

  private function seedLocalCall(string $uid, int $talk): void
  {
    app(CallEventNormalizer::class)->handle($this->vendorRecord($uid, $talk) + [
      'event' => 'call.completed',
      'event_id' => (string) Str::uuid(),
      'occurred_at' => '2026-08-19T15:01:44+05:30',
    ]);
  }

  /** @return array<string,mixed> */
  private function vendorRecord(string $uid, int $talk): array
  {
    return [
      'event' => 'call.completed',
      'call_uid' => $uid,
      'origin' => 'manual_dial',
      'direction' => 'outbound',
      'agent' => ['agent_id' => 'AG-1042', 'extension' => '1042'],
      'caller_number' => '+919812345678',
      'callee_number' => '+919812309876',
      'status' => 'answered',
      'timestamps' => [
        'initiated_at' => '2026-08-19T14:58:02+05:30',
        'ended_at' => '2026-08-19T15:01:40+05:30',
      ],
      'durations' => ['talk_seconds' => $talk, 'total_seconds' => $talk + 20],
    ];
  }
}
