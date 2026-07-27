<?php

namespace Database\Seeders;

use App\Models\LibraryFile;
use Illuminate\Database\Seeder;

/**
 * "Rustex" -> "Rust-X" (brand normalization, matches the rest of the app)
 * and "Protip" -> "Protape" (actual product name correction) across the
 * Learn @ HiTech records just added.
 */
class FixLearnHiTechNamingSeeder extends Seeder
{
    public function run(): void
    {
        $fixed = 0;

        LibraryFile::where('category', 'LEARN')
            ->where(function ($q) {
                $q->where('title', 'like', '%Rustex%')->orWhere('summary', 'like', '%Rustex%');
            })
            ->each(function (LibraryFile $file) use (&$fixed) {
                $file->title = str_replace('Rustex', 'Rust-X', $file->title);
                $file->summary = str_replace('Rustex', 'Rust-X', $file->summary);
                $file->save();
                $fixed++;
            });

        LibraryFile::where('category', 'LEARN')
            ->where(function ($q) {
                $q->where('title', 'like', '%Protip%')->orWhere('summary', 'like', '%Protip%');
            })
            ->each(function (LibraryFile $file) use (&$fixed) {
                $file->title = str_replace('Protip', 'Protape', $file->title);
                $file->summary = str_replace('Protip', 'Protape', $file->summary);
                $file->save();
                $fixed++;
            });

        $this->command->info("FixLearnHiTechNamingSeeder: updated {$fixed} records.");
    }
}
