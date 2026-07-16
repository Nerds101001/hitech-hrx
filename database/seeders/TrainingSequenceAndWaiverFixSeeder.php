<?php

namespace Database\Seeders;

use App\Models\TrainingModule;
use App\Models\TrainingPhase;
use App\Models\User;
use App\Models\UserTrainingProgress;
use Illuminate\Database\Seeder;

/**
 * Two fixes (2026-07-13):
 *
 * 1. STRUCTURAL — Phase 2 (Brand/Product Knowledge) catalogue modules had
 *    duplicate/missing `order` values (Rust-X & Dr Bio both 2, Zorbit &
 *    Tuffpaulin both 5, none at 1). Because unlockNextModule() unlocks every
 *    module sharing the next order value, employees could skip past an equal-
 *    ranked module while the sequential gate still blocked them. This renumbers
 *    the 7 catalogues to unique sequential orders so progression is deterministic.
 *
 * 2. WAIVER — Harjinder Kaur (EMP-108) failed the Rust-X assessment (59.52%,
 *    pass mark 80%) but had already completed the other 5 catalogues, leaving
 *    her stuck. Per management decision her Rust-X module is granted a pass so
 *    she can finish Phase 2.
 *
 * Idempotent. Run with:
 *   php artisan db:seed --class=TrainingSequenceAndWaiverFixSeeder
 */
class TrainingSequenceAndWaiverFixSeeder extends Seeder
{
    public function run(): void
    {
        $this->renumberBrandPhaseModules();
        $this->waiveRustXFor('EMP-108');
    }

    private function renumberBrandPhaseModules(): void
    {
        // The brand phase is the one that owns the Rust-X catalogue.
        $rustx = TrainingModule::where('content_type', 'catalog')
            ->where('title', 'like', '%Rust-X%')
            ->orderBy('id')
            ->first();

        if (!$rustx) {
            $this->command?->warn('Rust-X catalogue not found — skipping renumber.');
            return;
        }

        $phaseId = $rustx->phase_id;

        // Desired reading order for the Phase 2 catalogues.
        $orderedPatterns = [
            '%Rust-X%',
            '%Dr%Bio%',
            '%KIF%',
            '%Fillezy%',
            '%Zorbit%',
            '%Tuff%',
            '%Eva%',
        ];

        $order = 1;
        foreach ($orderedPatterns as $pattern) {
            $module = TrainingModule::where('phase_id', $phaseId)
                ->where('title', 'like', $pattern)
                ->orderBy('id')
                ->first();

            if ($module) {
                $module->update(['order' => $order]);
                $this->command?->info("Order {$order}: {$module->title}");
                $order++;
            }
        }
    }

    private function waiveRustXFor(string $code): void
    {
        $user = User::where('code', $code)->first();
        if (!$user) {
            $this->command?->warn("User {$code} not found — skipping waiver.");
            return;
        }

        $rustx = TrainingModule::where('content_type', 'catalog')
            ->where('title', 'like', '%Rust-X%')
            ->orderBy('id')
            ->first();
        if (!$rustx) {
            $this->command?->warn('Rust-X catalogue not found — skipping waiver.');
            return;
        }

        $progress = UserTrainingProgress::firstOrNew([
            'user_id' => $user->id,
            'module_id' => $rustx->id,
        ]);

        $progress->status = 'completed';
        // Grant a pass at the module's threshold (management waiver, not an
        // organically earned score).
        $progress->assessment_score = $rustx->passing_percentage ?? 80;
        $progress->completed_at = $progress->completed_at ?? now();
        $progress->tenant_id = $progress->tenant_id ?? $user->tenant_id;
        $progress->save();

        $this->command?->info("Waived Rust-X for {$code} (user #{$user->id}) — marked completed.");
    }
}
