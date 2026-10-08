<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A backfill / re-sync request made from the Tally Push admin page. Created by a viewer (status 'pending'),
 * claimed and run by the office PC over the signed API (hrx:tally-push for the date range), progress written
 * back here. The live server never pulls from Tally itself -- it only records the request and the result.
 */
class TallySyncJob extends Model
{
    protected $table = 'tally_sync_jobs';
    protected $guarded = ['id'];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'requested_at' => 'datetime',
        'claimed_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const PENDING = 'pending';
    public const CLAIMED = 'claimed';
    public const RUNNING = 'running';
    public const DONE = 'done';
    public const FAILED = 'failed';

    // What the office PC runs for this job.
    public const KIND_BACKFILL = 'backfill';        // hrx:tally-push --from --to (a date range of vouchers)
    public const KIND_OUTSTANDING = 'outstanding';  // hrx:tally-push-outstanding (bill-wise receivables snapshot)
    public const KIND_PARTIES = 'parties';          // hrx:tally-push-parties (ledger master snapshot)

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => 'Waiting for the office PC',
            self::CLAIMED, self::RUNNING => 'Syncing…',
            self::DONE => 'Done',
            self::FAILED => 'Failed',
            default => $this->status,
        };
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_OUTSTANDING => 'Outstanding refresh',
            self::KIND_PARTIES => 'Customer ledgers refresh',
            default => 'Backfill',
        };
    }

    public function totals(): array
    {
        return [
            'invoices' => $this->invoices, 'invoice_lines' => $this->invoice_lines, 'sale_lines' => $this->sale_lines,
            'sale_orders' => $this->sale_orders, 'deliveries' => $this->deliveries, 'receipts' => $this->receipts,
        ];
    }
}
