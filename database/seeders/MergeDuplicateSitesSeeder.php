<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cleans up the sites (locations) list:
 *
 *  1. MERGE true duplicates — records whose names match once case, spacing and
 *     punctuation are ignored (e.g. two separate "Manesar 99" rows). The lowest
 *     id wins; every table with a site_id is repointed at it, then the loser is
 *     soft-deleted. Holiday rows are de-duplicated during the repoint so the
 *     winning site does not end up with the same holiday twice.
 *
 *  2. NORMALISE display names to Title Case so the dropdowns stop showing a
 *     mix of "SALES NORTH", "sales South" and "Sales- West".
 *
 * Idempotent: re-running finds no duplicates and leaves names untouched.
 */
class MergeDuplicateSitesSeeder extends Seeder
{
    /** Names to leave exactly as typed (acronyms etc.) */
    private const KEEP_AS_IS = ['CG Office'];

    public function run(): void
    {
        $tables = collect(DB::select(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE COLUMN_NAME = 'site_id' AND TABLE_SCHEMA = DATABASE()"
        ))->pluck('TABLE_NAME')
          ->filter(fn ($t) => Schema::hasTable($t))
          ->values()
          ->all();

        $this->command->info('Tables carrying site_id: ' . count($tables));

        $sites = DB::table('sites')->whereNull('deleted_at')->orderBy('id')->get();
        $norm = fn ($n) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $n));

        $groups = [];
        foreach ($sites as $s) {
            $groups[$norm($s->name)][] = $s;
        }

        $mergedSites = 0; $rowsRepointed = 0; $holidayDupesDropped = 0;

        DB::transaction(function () use ($groups, $tables, &$mergedSites, &$rowsRepointed, &$holidayDupesDropped) {
            foreach ($groups as $group) {
                if (count($group) < 2) {
                    continue;
                }

                $winner = array_shift($group); // lowest id
                $this->command->warn("\nMerging into id={$winner->id} '{$winner->name}':");

                foreach ($group as $loser) {
                    foreach ($tables as $table) {
                        $count = DB::table($table)->where('site_id', $loser->id)->count();
                        if ($count === 0) {
                            continue;
                        }

                        // Holidays: a repoint could create an exact duplicate
                        // (same date + name already on the winner), so drop
                        // those rows instead of moving them.
                        if ($table === 'holidays') {
                            $rows = DB::table('holidays')->where('site_id', $loser->id)->get();
                            foreach ($rows as $h) {
                                $clash = DB::table('holidays')
                                    ->where('site_id', $winner->id)
                                    ->whereDate('date', $h->date)
                                    ->where('name', $h->name)
                                    ->exists();
                                if ($clash) {
                                    DB::table('holidays')->where('id', $h->id)->delete();
                                    $holidayDupesDropped++;
                                } else {
                                    DB::table('holidays')->where('id', $h->id)
                                        ->update(['site_id' => $winner->id]);
                                    $rowsRepointed++;
                                }
                            }
                            $this->command->line("   holidays: {$count} row(s) handled");
                            continue;
                        }

                        DB::table($table)->where('site_id', $loser->id)
                            ->update(['site_id' => $winner->id]);
                        $rowsRepointed += $count;
                        $this->command->line("   {$table}: {$count} row(s) repointed");
                    }

                    // Soft-delete when supported, otherwise mark inactive
                    if (Schema::hasColumn('sites', 'deleted_at')) {
                        DB::table('sites')->where('id', $loser->id)->update([
                            'deleted_at' => now(),
                            'status' => 'inactive',
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('sites')->where('id', $loser->id)->update(['status' => 'inactive']);
                    }

                    $this->command->line("   retired duplicate id={$loser->id} '{$loser->name}'");
                    $mergedSites++;
                }
            }
        });

        // ---- normalise display names ----
        $renamed = 0;
        foreach (DB::table('sites')->whereNull('deleted_at')->get() as $s) {
            if (in_array($s->name, self::KEEP_AS_IS, true)) {
                continue;
            }
            // tidy spacing/hyphens, then Title Case each word
            $clean = preg_replace('/\s*-\s*/', ' ', (string) $s->name);
            $clean = preg_replace('/\s+/', ' ', trim($clean));
            $clean = ucwords(strtolower($clean));

            if ($clean !== '' && $clean !== $s->name) {
                DB::table('sites')->where('id', $s->id)
                    ->update(['name' => $clean, 'updated_at' => now()]);
                $this->command->line("  renamed '{$s->name}' -> '{$clean}'");
                $renamed++;
            }
        }

        $this->command->info("\nDuplicates merged      : {$mergedSites}");
        $this->command->info("Rows repointed         : {$rowsRepointed}");
        $this->command->info("Duplicate holidays cut : {$holidayDupesDropped}");
        $this->command->info("Names normalised       : {$renamed}");

        $this->command->info("\nFinal location list:");
        foreach (DB::table('sites')->whereNull('deleted_at')->orderBy('name')->get() as $s) {
            $users = DB::table('users')->where('site_id', $s->id)->whereNull('deleted_at')->count();
            $this->command->line(sprintf('  id=%-4s %-22s %d user(s)', $s->id, $s->name, $users));
        }
    }
}
