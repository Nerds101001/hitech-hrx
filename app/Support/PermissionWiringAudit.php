<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * "Does checking this box actually do anything?" - the question the whole
 * 2026-09 granular-access review kept running into by hand: a permission
 * can exist in config/permissions_registry.php and the Roles & Permissions
 * UI, be genuinely saveable via RoleController, and still gate nothing at
 * all anywhere in the app (MenuPermissionMiddleware, five orphaned
 * telephony/dashboard keys, six missing Permission rows, several routes
 * whose auth check was commented out - all found the same way this class
 * now checks automatically: is the key referenced anywhere outside the
 * registry itself).
 *
 * A plain substring search, not a real static-analysis tool - "referenced
 * somewhere" is a floor, not a guarantee that every reference is a genuine
 * enforcement point, but it is exactly the check that caught every dead
 * permission found this pass, and it is cheap enough to run on an
 * admin-only, rarely-loaded page.
 */
class PermissionWiringAudit
{
    private const SCAN_DIRS = ['app', 'routes', 'resources/menu', 'resources/views'];
    private const CACHE_KEY = 'permission_wiring_audit_v1';
    private const CACHE_TTL_SECONDS = 3600;

    /** @return array<string,bool> permission key => referenced anywhere outside the registry */
    public static function wiredMap(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $keys = [];
            foreach (config('permissions_registry', []) as $perms) {
                foreach (array_keys($perms) as $key) {
                    $keys[$key] = false;
                }
            }

            $haystack = self::concatenatedSource();
            foreach (array_keys($keys) as $key) {
                $keys[$key] = str_contains($haystack, $key);
            }

            return $keys;
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function concatenatedSource(): string
    {
        $chunks = [];
        foreach (self::SCAN_DIRS as $dir) {
            $path = base_path($dir);
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['php', 'json'], true)) {
                    continue;
                }
                // The registry itself always contains every key - excluding it
                // is what makes this "referenced somewhere ELSE", not "exists".
                if ($file->getPathname() === base_path('config/permissions_registry.php')) {
                    continue;
                }
                $chunks[] = @file_get_contents($file->getPathname()) ?: '';
            }
        }

        return implode("\n", $chunks);
    }
}
