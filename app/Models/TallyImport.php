<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** One row per `hrx:import-tally` run -- drives the "data as of" banner. */
class TallyImport extends Model implements AuditableContract
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public static function latestGood(): ?self
    {
        return static::whereIn('status', ['ok', 'partial'])->latest('id')->first();
    }

    /**
     * Two independent things keep the Sales Sources tables fresh: this file-based import (hrx:import-tally) and
     * the newer Tally API push automation (hrx:tally-push / hrx:tally-push-parties, logged in tally_push_runs --
     * see App\Http\Controllers\Api\TallyPushController's own doc comment for how the two coexist). The "data as
     * of" banner should say whichever one actually wrote data most recently, not always the file import --
     * otherwise, once the API push became the day-to-day sync, the banner would keep pointing at an
     * increasingly stale file-import date even while the data itself was current.
     *
     * @return array{source: 'push'|'file', at: \Illuminate\Support\Carbon, partial: bool, label: string}|null
     */
    public static function latestSync(): ?array
    {
        $lastImport = static::latestGood();
        $lastPush = DB::table('tally_push_runs')->whereIn('status', ['ok', 'partial'])->orderByDesc('finished_at')->first();

        if ($lastPush && (!$lastImport || \Carbon\Carbon::parse($lastPush->finished_at)->gt($lastImport->finished_at))) {
            $counts = json_decode((string) $lastPush->counts, true) ?: [];
            return [
                'source' => 'push', 'at' => \Carbon\Carbon::parse($lastPush->finished_at),
                'partial' => $lastPush->status !== 'ok',
                'label' => 'Tally synced' . (($counts['invoices'] ?? null) !== null ? " ({$counts['invoices']} invoices)" : ''),
            ];
        }
        if ($lastImport) {
            return ['source' => 'file', 'at' => $lastImport->finished_at, 'partial' => $lastImport->status !== 'ok', 'label' => 'Tally data imported'];
        }
        return null;
    }
}
