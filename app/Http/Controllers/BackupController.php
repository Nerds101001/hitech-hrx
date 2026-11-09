<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Backup management (read + download only). Lists the spatie/laravel-backup
 * archives, shows the last-backup time, lets an admin download an archive or
 * trigger a fresh backup. Deliberately NO web restore: restoring overwrites the
 * live database, so it stays a manual, deliberate operation.
 */
class BackupController extends Controller
{
    private function dir(): string
    {
        $name = config('backup.backup.name', config('app.name', 'laravel-backup'));
        $dir = storage_path('app/' . $name);
        if (!is_dir($dir) && is_dir(storage_path('app/private/' . $name))) {
            $dir = storage_path('app/private/' . $name);
        }
        return $dir;
    }

    public function index()
    {
        $dir = $this->dir();
        $items = [];
        $total = 0;
        $newest = 0;
        foreach (glob($dir . '/*.zip') ?: [] as $f) {
            $size = (int) @filesize($f);
            $m = (int) @filemtime($f);
            $items[] = (object) ['name' => basename($f), 'size' => $size, 'modified' => $m];
            $total += $size;
            $newest = max($newest, $m);
        }
        usort($items, fn($a, $b) => $b->modified <=> $a->modified);

        return view('security.backups', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'items'       => $items,
            'count'       => count($items),
            'totalSize'   => $total,
            'lastAt'      => $newest ? \Carbon\Carbon::createFromTimestamp($newest) : null,
        ]);
    }

    public function download(Request $request, string $file)
    {
        $file = basename($file); // strip any path traversal
        if (!str_ends_with(strtolower($file), '.zip')) {
            abort(404);
        }
        $path = $this->dir() . '/' . $file;
        if (!is_file($path)) {
            abort(404);
        }
        return response()->download($path, $file);
    }

    public function runNow(Request $request)
    {
        @set_time_limit(300); // a DB dump + zip can take a little while, inline
        try {
            Artisan::call('backup:run', ['--only-db' => true]);
            Cache::forget('sysstat.backup');
            $out = trim(Artisan::output());
            $ok = stripos($out, 'Backup completed') !== false;
            return back()->with(
                $ok ? 'success' : 'error',
                $ok ? 'Backup completed — the new archive is listed below.'
                    : 'Backup finished but the result was unclear. Check the list / logs.'
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }
}
