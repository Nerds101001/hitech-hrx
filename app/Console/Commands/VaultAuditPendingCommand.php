<?php

namespace App\Console\Commands;

use App\Models\LibraryFile;
use App\Services\VaultAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Classifies Digital Library files the upload dialog could not wait for.
 *
 * When the AI provider's queue is slow the upload accepts the file anyway and
 * leaves it carrying the placeholder crux. This command picks those up later,
 * when nobody is watching and a multi-minute call costs nothing, and fills in
 * the brand, sub-category, category and crux.
 *
 * Safe to run by hand or on a schedule: it only ever touches files still
 * holding the placeholder, so re-running skips whatever it already did.
 */
class VaultAuditPendingCommand extends Command
{
    protected $signature = 'vault:audit-pending
                            {--limit=20 : Maximum files to classify in this run}
                            {--id= : Audit one specific library file id}
                            {--dry-run : List what would be audited, change nothing}';

    protected $description = 'Run the AI audit on Digital Library files still awaiting classification';

    public function handle(VaultAuditService $audit): int
    {
        $query = LibraryFile::whereNotNull('file_path')
            ->where('summary', VaultAuditService::PENDING_SUMMARY);

        if ($this->option('id')) {
            $query->where('id', $this->option('id'));
        }

        $files = $query->orderBy('id')->limit((int) $this->option('limit'))->get();

        if ($files->isEmpty()) {
            $this->info('Nothing awaiting audit.');

            return self::SUCCESS;
        }

        $this->info("{$files->count()} file(s) awaiting audit.");

        if ($this->option('dry-run')) {
            foreach ($files as $file) {
                $this->line("  [{$file->id}] {$file->title}");
            }
            $this->comment('Dry run — nothing changed.');

            return self::SUCCESS;
        }

        $timeout = (int) config('openai.audit_timeout_queued', 300);
        $done    = $failed = 0;

        foreach ($files as $file) {
            $this->line("Auditing [{$file->id}] {$file->title} ...");

            try {
                $text = $this->extractText($audit, $file);
            } catch (\Throwable $e) {
                $this->error("  could not read the stored file: {$e->getMessage()}");
                $failed++;
                continue;
            }

            $result = $audit->audit($text, $file->title, $timeout);

            if ($result['status'] !== 'ok') {
                // Left as pending on purpose — a timeout here means the queue is
                // still slow, and the next run should try this file again.
                $this->warn("  {$result['status']}: " . ($result['message'] ?? ''));
                $failed++;
                continue;
            }

            $file->update([
                'brand'        => $result['brand'],
                'sub_category' => $result['sub_category'],
                'category'     => $result['category'],
                'summary'      => $result['summary'],
            ]);

            Log::info("VAULT_AUDIT_BACKFILL [{$file->id}]: {$result['brand']} / {$result['category']}");
            $this->info("  {$result['brand']} / {$result['sub_category']} / {$result['category']}");
            $done++;
        }

        $this->newLine();
        $this->info("Classified {$done}, still pending {$failed}.");

        return self::SUCCESS;
    }

    /** Stored files live on R2, so pull a copy down to parse it. */
    private function extractText(VaultAuditService $audit, LibraryFile $file): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'vault_') . '.pdf';

        try {
            file_put_contents($temp, Storage::disk('r2')->get($file->file_path));

            return $audit->extractText($temp);
        } finally {
            @unlink($temp);
        }
    }
}
