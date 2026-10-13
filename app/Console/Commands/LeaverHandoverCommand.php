<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LeaverHandoverService;
use Illuminate\Console\Command;

/**
 * Preview, apply or undo a leaver handover. Previews by default: nothing changes
 * unless --apply is given WITH the fingerprint the preview printed, so what is
 * applied is exactly what was reviewed.
 */
class LeaverHandoverCommand extends Command
{
    protected $signature = 'sales:leaver-handover
                            {leaver? : user id of the person who left}
                            {--salesperson= : replacement user id for the Salesperson role}
                            {--ccare= : replacement user id for the CCare role}
                            {--new-biz= : replacement user id for the New Biz role}
                            {--billing= : replacement user id for Client Master billing}
                            {--finance= : replacement user id for Client Master finance}
                            {--month= : first month the replacement is credited, YYYY-MM}
                            {--apply : really do it (needs --fingerprint)}
                            {--fingerprint= : the fingerprint printed by the preview}
                            {--revert= : undo this handover batch id}';

    protected $description = 'Hand a leaver\'s customers to a replacement from a month on (preview by default)';

    public function handle(): int
    {
        if ($batch = $this->option('revert')) {
            try {
                $r = LeaverHandoverService::revert((int) $batch, null);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->info("Undone: {$r['reverted']} change(s) put back.");

            return self::SUCCESS;
        }

        $leaver = User::find($this->argument('leaver'));
        if (!$leaver) {
            $this->error('Give the leaver\'s user id.');

            return self::FAILURE;
        }
        $month = (string) $this->option('month');
        $repl = [
            'salesperson' => $this->option('salesperson'), 'ccare' => $this->option('ccare'), 'new_biz' => $this->option('new-biz'),
            'billing' => $this->option('billing'), 'finance' => $this->option('finance'),
        ];

        $plan = LeaverHandoverService::plan($leaver, $repl, $month);
        $s = $plan['summary'];
        $this->info("Handover of #{$leaver->id} {$leaver->first_name} {$leaver->last_name} from {$month}");
        $this->line(sprintf(
            '  %d customer(s), %d role change(s), %d additional-rep tag(s) end, %d Client Master record(s), %d CCare-map row(s) added',
            $s['customers'], $s['role_moves'], $s['tags_ending'], $s['client_master'], $s['ccare_map_added']
        ));
        $this->line("  Stays with the leaver (months before {$month}): {$s['sale_stays_L']} L.  Goes to the replacement ({$month} on): {$s['sale_moves_L']} L in {$s['sale_month_rows']} month row(s).");
        foreach ($plan['warnings'] as $w) {
            $this->warn('  ! ' . $w);
        }
        foreach ($plan['errors'] as $e) {
            $this->error('  x ' . $e);
        }
        $this->line('  Fingerprint: ' . $plan['fingerprint']);

        if (!$this->option('apply')) {
            $this->info('Preview only. Nothing was changed.');

            return $plan['errors'] ? self::FAILURE : self::SUCCESS;
        }

        try {
            $id = LeaverHandoverService::apply($leaver, $repl, $month, (string) $this->option('fingerprint'), null, 'via command');
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Applied as batch #{$id}. Undo with: php artisan sales:leaver-handover --revert={$id}");

        return self::SUCCESS;
    }
}
