<?php

namespace App\Console\Commands;

use App\Services\LeaveAttendanceReconciler;
use Illuminate\Console\Command;

/**
 * Returns leave days to the All Purpose Leave pool where the employee actually
 * turned up. Dry by default — pass --apply to write.
 */
class ReconcileLeaveAttendance extends Command
{
    protected $signature = 'leave:reconcile-attendance
                            {--apply : Write the changes (default is a dry run)}
                            {--user= : Restrict to one user id}';

    protected $description = 'Credit back approved PAID leave days on which attendance was recorded';

    public function handle(): int
    {
        $dry = !$this->option('apply');
        $this->info($dry ? 'DRY RUN — nothing will be written.' : 'APPLYING changes.');

        $r = LeaveAttendanceReconciler::run($dry, $this->option('user') ? (int) $this->option('user') : null);

        if (!empty($r['rows'][0]['error'])) {
            $this->error($r['rows'][0]['error']);
            return self::FAILURE;
        }

        if ($r['rows']) {
            $this->table(['Employee', 'Leave #', 'Type', 'Date', 'Days back'],
                array_map(fn($x) => [$x['user'], $x['leave'], $x['type'], $x['date'], $x['days']],
                    array_slice($r['rows'], 0, 60)));
            if (count($r['rows']) > 60) {
                $this->line('  ... ' . (count($r['rows']) - 60) . ' more');
            }
        } else {
            $this->line('  No leave days conflict with attendance.');
        }

        $this->newLine();
        $this->line("  approved requests scanned : {$r['scanned']}");
        $this->line("  days credited back to ALP : {$r['credited']}");
        $this->line("  requests fully cancelled  : {$r['cancelled']}");

        return self::SUCCESS;
    }
}
