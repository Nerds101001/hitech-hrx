<?php

namespace App\Console\Commands;

use App\Models\LibraryFile;
use App\Models\LibraryTaxonomy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Learn @ Hi Tech used to be its own top-level document type. It now lives
 * inside Video as just another category, alongside anything else staff file
 * under Video — so existing "LEARN" rows need to actually become Video rows
 * tagged with that category, not just be displayed that way.
 *
 * Also normalises pre-existing Video rows: video never had a real category
 * picker before this change, so every Video upload was silently stamped
 * with the chemical-document default ('Industrial Assets') for sub_category.
 * Those get moved to a genuine "General" video category instead.
 *
 * Idempotent: once no LEARN rows and no stale sub_category values remain,
 * re-running finds nothing to do.
 */
class MigrateLearnToVideoCommand extends Command
{
    public const LEARN_CATEGORY = 'Learn @ Hi Tech';
    public const GENERAL_CATEGORY = 'General';

    protected $signature = 'library:migrate-learn-to-video {--dry-run : Show what would change, write nothing}';

    protected $description = 'Fold existing Learn @ Hi Tech files into Video as a category, and seed the video-only taxonomy';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $learnCount = LibraryFile::where('category', 'LEARN')->count();
        $staleVideoCount = LibraryFile::where('category', 'Video')
            ->where(function ($q) {
                $q->whereNull('sub_category')
                  ->orWhere('sub_category', '')
                  ->orWhere('sub_category', 'Industrial Assets');
            })
            ->count();

        $this->info("LEARN rows to fold into Video: {$learnCount}");
        $this->info("Video rows with no real category yet: {$staleVideoCount}");

        if ($dry) {
            $this->comment('Dry run — no changes written.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($learnCount, $staleVideoCount) {
            // Video's own taxonomy rows (parent_id null = not under any brand).
            LibraryTaxonomy::firstOrCreate(
                ['type' => 'category', 'parent_id' => null, 'name' => self::LEARN_CATEGORY],
                ['order' => 0]
            );
            LibraryTaxonomy::firstOrCreate(
                ['type' => 'category', 'parent_id' => null, 'name' => self::GENERAL_CATEGORY],
                ['order' => 1]
            );

            if ($learnCount > 0) {
                LibraryFile::where('category', 'LEARN')->update([
                    'category' => 'Video',
                    'sub_category' => self::LEARN_CATEGORY,
                ]);
            }

            if ($staleVideoCount > 0) {
                LibraryFile::where('category', 'Video')
                    ->where(function ($q) {
                        $q->whereNull('sub_category')
                          ->orWhere('sub_category', '')
                          ->orWhere('sub_category', 'Industrial Assets');
                    })
                    ->update(['sub_category' => self::GENERAL_CATEGORY]);
            }
        });

        $this->info("Done. {$learnCount} file(s) moved from LEARN to Video/\"" . self::LEARN_CATEGORY . '".');
        $this->info("{$staleVideoCount} pre-existing Video file(s) tagged \"" . self::GENERAL_CATEGORY . '".');

        return self::SUCCESS;
    }
}
