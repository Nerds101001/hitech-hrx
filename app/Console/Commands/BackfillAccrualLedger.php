<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\LeaveBalance;
use Carbon\Carbon;

class BackfillAccrualLedger extends Command
{
    protected $signature = 'leave:backfill-ledger';
    protected $description = 'Backfill audit ledger entries for April and May 2026 accruals';

    public function handle()
    {
        $this->info("Backfilling ledger entries for April & May 2026 accruals...\n");

        $alpType = DB::table('leave_types')->where('code', 'ALP')->first();
        if (!$alpType) {
            $this->error("ALP leave type not found.");
            return Command::FAILURE;
        }

        $balances = LeaveBalance::with('user')
            ->where('leave_type_id', $alpType->id)
            ->get();

        $count = 0;

        foreach ($balances as $balance) {
            $userName = ($balance->user->code ?? '?') . ' ' . ($balance->user->first_name ?? '');

            // Check if April entry already exists
            $aprilExists = DB::table('audits')
                ->where('auditable_type', 'App\Models\LeaveBalance')
                ->where('auditable_id', $balance->id)
                ->where('user_agent', 'Earned for April 2026')
                ->exists();

            // Check if May entry already exists
            $mayExists = DB::table('audits')
                ->where('auditable_type', 'App\Models\LeaveBalance')
                ->where('auditable_id', $balance->id)
                ->where('user_agent', 'Earned for May 2026')
                ->exists();

            if ($aprilExists && $mayExists) {
                $this->line("  [{$userName}] Already complete — skipping.");
                continue;
            }

            if (!$aprilExists) {
                $this->insertAuditEntry($balance, 'Earned for April 2026', Carbon::create(2026, 4, 1, 0, 0, 0));
                $this->line("  [{$userName}] April entry added.");
            }

            if (!$mayExists) {
                $this->insertAuditEntry($balance, 'Earned for May 2026', Carbon::create(2026, 5, 1, 0, 0, 0));
                $this->line("  [{$userName}] May entry added.");
            }

            $count++;
        }

        $this->info("\n✅ Done. {$count} users backfilled.");
        return Command::SUCCESS;
    }

    private function insertAuditEntry(LeaveBalance $balance, string $reason, Carbon $date): void
    {
        $currentBalance = (float)$balance->balance;

        DB::table('audits')->insert([
            'user_type'      => 'App\Models\User',
            'user_id'        => $balance->user_id,
            'event'          => 'updated',
            'auditable_type' => 'App\Models\LeaveBalance',
            'auditable_id'   => $balance->id,
            'old_values'     => json_encode(['balance' => round($currentBalance - 1.0, 2)]),
            'new_values'     => json_encode(['balance' => round($currentBalance, 2)]),
            'url'            => 'system/monthly-accrual',
            'ip_address'     => '127.0.0.1',
            'user_agent'     => $reason,
            'tags'           => null,
            'created_at'     => $date,
            'updated_at'     => $date,
        ]);
    }
}
