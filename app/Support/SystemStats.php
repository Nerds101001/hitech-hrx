<?php

namespace App\Support;

use App\Models\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Real values for the Settings dashboard stat cards (Active Modules, Storage
 * Used, Backup Status, System Health). Each method is cached and fully guarded
 * so a glitch degrades to a safe placeholder instead of breaking the page.
 *
 * Lives in a helper (not the controller) because the prod SettingsController is
 * drifted/obfuscated — the view, which matches prod, calls these directly.
 */
class SystemStats
{
    /** Modules granted by the plan, minus the force-disabled ones. */
    public static function activeModules(): int
    {
        return Cache::remember('sysstat.active_modules', now()->addMinutes(10), function () {
            try {
                $settings = Settings::first();
                $mods = $settings?->available_modules ?? [];
                if (is_string($mods)) {
                    $mods = json_decode($mods, true) ?: [];
                }
                $disabled = ['AiChat', 'Monitoring', 'ClientVisit', 'SoS', 'Device', 'RegisteredDevices', 'DisabledModule'];
                return count(array_diff((array) $mods, $disabled));
            } catch (\Throwable $e) {
                return 0;
            }
        });
    }

    /** Size of the application storage directory, in GB (2 dp). */
    public static function storageUsed(): string
    {
        return Cache::remember('sysstat.storage_gb', now()->addHours(12), function () {
            try {
                $bytes = 0;
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(storage_path(), \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($it as $file) {
                    if ($file->isFile()) {
                        $bytes += $file->getSize();
                    }
                }
                return number_format($bytes / 1073741824, 2); // bytes → GB
            } catch (\Throwable $e) {
                return '0.00';
            }
        });
    }

    /** OK / Stale / None, based on the newest backup archive's age. */
    public static function backupStatus(): string
    {
        return Cache::remember('sysstat.backup', now()->addHour(), function () {
            try {
                $name = config('backup.backup.name', config('app.name', 'laravel-backup'));
                $dir = storage_path('app/' . $name);
                if (!is_dir($dir)) {
                    $dir = storage_path('app/private/' . $name); // L11 private disk layout
                }
                if (!is_dir($dir)) {
                    return 'None';
                }
                $newest = 0;
                foreach (glob($dir . '/*.zip') ?: [] as $zip) {
                    $newest = max($newest, (int) filemtime($zip));
                }
                if ($newest === 0) {
                    return 'None';
                }
                $label = (time() - $newest) <= 2 * 86400 ? 'OK' : 'Stale';
                return $label . ' · ' . date('d M H:i', $newest); // e.g. "OK · 09 Nov 14:59"
            } catch (\Throwable $e) {
                return 'Unknown';
            }
        });
    }

    /** Composite health %, from a handful of cheap, real checks. */
    public static function systemHealth(): int
    {
        return Cache::remember('sysstat.health', now()->addMinutes(5), function () {
            $checks = [];
            try { DB::select('select 1'); $checks[] = true; } catch (\Throwable $e) { $checks[] = false; }
            $checks[] = is_writable(storage_path());
            try {
                Cache::put('sysstat.ping', 1, 60);
                $checks[] = Cache::get('sysstat.ping') === 1;
            } catch (\Throwable $e) { $checks[] = false; }
            $checks[] = in_array(self::backupStatus(), ['OK', 'Stale'], true); // a backup exists at all
            $checks[] = config('app.debug') === false;                          // debug off in prod

            $passed = count(array_filter($checks));
            $total = count($checks);
            return $total ? (int) round($passed / $total * 100) : 0;
        });
    }
}
