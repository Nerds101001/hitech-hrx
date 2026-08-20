<?php

namespace App\Console\Commands;

use App\Models\Telephony\TelephonyCall;
use App\Models\Telephony\TelephonyReconciliationRun;
use App\Services\Telephony\CallEventNormalizer;
use App\Services\Telephony\CentroVisionClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Nightly proof that HRX and the gateway agree about yesterday.
 *
 * Counts first: if the vendor's total for the window equals ours, nothing else
 * needs fetching. Only on a mismatch do we page through the CDR, and only the
 * call_uids we are actually missing get written.
 *
 * A non-zero gap is a defect to root-cause, not a tolerance to widen.
 */
class TelephonyReconcile extends Command
{
  protected $signature = 'telephony:reconcile
                          {--date= : Date to reconcile (Y-m-d), defaults to yesterday}
                          {--backfill=1 : Write missing calls found on the vendor side}';

  protected $description = 'Compare HRX call records against the CentroVision CDR and backfill any gap';

  public function handle(CentroVisionClient $vendor): int
  {
    $tz = config('telephony.vendor_timezone');
    $date = $this->option('date')
      ? Carbon::parse($this->option('date'), $tz)
      : Carbon::now($tz)->subDay();

    $start = $date->copy()->startOfDay();
    $end = $date->copy()->endOfDay();
    $tenantId = config('telephony.inbound.default_tenant_id');

    $this->info("Reconciling {$date->toDateString()} ({$tz})");

    $run = TelephonyReconciliationRun::updateOrCreate(
      ['tenant_id' => $tenantId, 'period_date' => $date->toDateString()],
      ['period_start' => $start, 'period_end' => $end, 'result' => 'pending', 'error' => null]
    );

    try {
      $localQuery = TelephonyCall::where('tenant_id', $tenantId)
        ->whereBetween('initiated_at', [$start, $end]);

      $localCount = (clone $localQuery)->count();
      $localTalk = (int) (clone $localQuery)->sum('talk_seconds');

      $first = $vendor->cdr($start->toIso8601String(), $end->toIso8601String());
      $vendorCount = (int) ($first['total_count'] ?? 0);

      $this->line("  vendor: {$vendorCount} calls   HRX: {$localCount} calls");

      $missing = [];
      $backfilled = 0;
      $vendorTalk = 0;
      $talkComparable = true;

      // Page the CDR every night, whether or not the counts already agree.
      // Equal counts do not prove equal durations - the vendor can report the
      // same number of calls with different talk time on any of them, which is
      // exactly the timezone and rounding drift this run exists to catch. The
      // counts decide whether anything gets BACKFILLED, never whether the
      // totals get CHECKED.
      $known = (clone $localQuery)->pluck('call_uid')->flip();
      $page = $first;

      do {
        foreach (($page['records'] ?? []) as $record) {
          $uid = $record['call_uid'] ?? null;
          $vendorTalk += (int) ($record['durations']['talk_seconds'] ?? 0);

          if (!$uid || $known->has($uid)) {
            continue;
          }

          $missing[] = $uid;

          if ($this->option('backfill')) {
            $record['via_reconciliation'] = true;
            $record['event'] = $record['event'] ?? 'call.completed';
            // A CDR row has no event of its own, so give it a deterministic
            // identity. Re-running reconcile for the same day must not append
            // a second event to the append-only log.
            $record['event_id'] = $record['event_id'] ?? $this->stableUuid('cdr-import:' . $uid);
            $record['occurred_at'] = $record['occurred_at']
              ?? ($record['timestamps']['ended_at'] ?? now()->toIso8601String());

            app(CallEventNormalizer::class)->handle($record);
            $backfilled++;
          }
        }

        $cursor = $page['next_cursor'] ?? null;
        $page = $cursor
          ? $vendor->cdr($start->toIso8601String(), $end->toIso8601String(), $cursor)
          : null;
      } while ($page !== null);

      // A gateway that reports total_count but returns no rows leaves us
      // unable to compare talk time. Say so rather than reporting a false
      // -Xs gap against zero.
      $talkComparable = $vendorCount === 0 || $vendorTalk > 0;

      $countGap = $vendorCount - $localCount;
      $talkGap = $talkComparable ? $vendorTalk - $localTalk : 0;
      $tolerance = (int) config('telephony.reconcile.talk_tolerance_seconds') * max(1, $vendorCount);

      $result = ($countGap === 0 && abs($talkGap) <= $tolerance) ? 'pass' : 'investigate';

      if (!$talkComparable) {
        $this->warn('  talk time not comparable - the gateway returned a count but no CDR rows.');
      }

      $run->update([
        'vendor_count' => $vendorCount,
        'local_count' => $localCount,
        'count_gap' => $countGap,
        'vendor_talk_seconds' => $vendorTalk,
        'local_talk_seconds' => $localTalk,
        'talk_gap_seconds' => $talkGap,
        'backfilled' => $backfilled,
        'missing_call_uids' => array_slice($missing, 0, 500),
        'result' => $result,
      ]);

      if ($result === 'pass') {
        $this->info('  PASS - HRX and the gateway agree.');

        return self::SUCCESS;
      }

      $this->error("  INVESTIGATE - count gap {$countGap}, talk gap {$talkGap}s, backfilled {$backfilled}.");

      Log::warning('[telephony] reconciliation gap', [
        'date' => $date->toDateString(),
        'count_gap' => $countGap,
        'talk_gap_seconds' => $talkGap,
        'backfilled' => $backfilled,
      ]);

      return self::FAILURE;
    } catch (Throwable $e) {
      $run->update(['result' => 'failed', 'error' => substr($e->getMessage(), 0, 2000)]);
      $this->error('  FAILED - ' . $e->getMessage());

      return self::FAILURE;
    }
  }

  /** A stable v4-shaped UUID derived from the call id, so replays collapse. */
  private function stableUuid(string $seed): string
  {
    $hash = hash('sha256', $seed);

    return sprintf(
      '%s-%s-4%s-%s%s-%s',
      substr($hash, 0, 8),
      substr($hash, 8, 4),
      substr($hash, 13, 3),
      dechex((hexdec(substr($hash, 16, 1)) & 0x3) | 0x8),
      substr($hash, 17, 3),
      substr($hash, 20, 12)
    );
  }
}
