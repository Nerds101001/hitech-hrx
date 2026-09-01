<?php

namespace App\Console\Commands;

use App\Services\UnpaidAbsenceReconciler;
use Illuminate\Console\Command;

/**
 * Converts an unpaid (LWP) leave to paid once enough All Purpose Leave
 * balance has become available within the same month - e.g. a Monday taken
 * as leave with no balance, followed by a Sunday worked that same month
 * earning a comp-off day. Dry by default - pass --apply to write.
 */
class ReconcileUnpaidAbsences extends Command
{
    protected $signature = 'leave:reconcile-unpaid-absences
                            {--apply : Write the changes (default is a dry run)}
                            {--user= : Restrict to one user id}
                            {--month= : Target month (1-12), defaults to the current month}
                            {--year= : Target year, defaults to the current year}';

    protected $description = 'Convert LWP leaves to paid once enough balance became available within the same month';

    public function handle(): int
    {
        $dry = !$this->option('apply');
        $this->info($dry ? 'DRY RUN — nothing will be written.' : 'APPLYING changes.');

        $r = UnpaidAbsenceReconciler::run(
            $dry,
            $this->option('user') ? (int) $this->option('user') : null,
            $this->option('month') ? (int) $this->option('month') : null,
            $this->option('year') ? (int) $this->option('year') : null,
        );

        if (!empty($r['rows'][0]['error'])) {
            $this->error($r['rows'][0]['error']);
            return self::FAILURE;
        }

        if ($r['rows']) {
            $this->table(['Employee', 'Leave #', 'Type', 'Date', 'Days paid'],
                array_map(fn($x) => [$x['user'], $x['leave'], $x['type'], $x['date'], $x['days']],
                    array_slice($r['rows'], 0, 60)));
            if (count($r['rows']) > 60) {
                $this->line('  ... ' . (count($r['rows']) - 60) . ' more');
            }
        } else {
            $this->line('  No unpaid leave now has enough balance to convert.');
        }

        $this->newLine();
        $this->line("  unpaid leave requests scanned : {$r['scanned']}");
        $this->line("  requests converted to paid    : {$r['converted']}");
        $this->line("  days paid                     : {$r['daysPaid']}");

        return self::SUCCESS;
    }
}
