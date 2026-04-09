<?php

namespace App\Http\Controllers;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Helpers\Format;
use Symfony\Component\Console\Output\BufferedOutput;

class UtilitiesController extends Controller
{

  public function index()
  {
    return view('utilities.index');
  }

  public function createBackup()
  {
    $output = new BufferedOutput();

    try {
      Artisan::call('backup:run', [
        '--only-db' => true,
      ], $output);

      // Get the output from the command
      $result = $output->fetch();

      if (str_contains($result, 'Backup failed')) {
        return redirect()->back()->with('error', 'Backup creation failed');
      }

      return Success::response('Backup created successfully');
    } catch (\Exception $e) {
      return Error::response('Backup creation failed');
    }
  }

  public function downloadBackup($fileName)
  {
    $file = config('backup.backup.name') . '/' . $fileName;
    $disk = Storage::disk(config('backup.backup.destination.disks')[0]);

    if ($disk->exists($file)) {
      $stream = $disk->readStream($file);

      return Response::stream(function () use ($stream) {
        fpassthru($stream);
      }, 200, [
        "Content-Type" => $disk->mimeType($file),
        "Content-Length" => $disk->size($file),
        "Content-disposition" => "attachment; filename=\"" . basename($file) . "\"",
      ]);
    } else {
      abort(404, "The backup file doesn't exist.");
    }
  }

  public function deleteBackup($fileName)
  {
    $file = config('backup.backup.name') . '/' . $fileName;
    $disk = Storage::disk(config('backup.backup.destination.disks')[0]);

    if ($disk->exists($file)) {
      $disk->delete($file);
      return redirect()->back()->with('success', 'Backup deleted successfully');
    } else {
      return redirect()->back()->with('error', 'Backup deletion failed');
    }
  }

  public function restoreBackup($fileName)
  {
    $output = new BufferedOutput();
    try {
      // Assuming the backup file contains a database dump
      $filePath = config('backup.backup.name') . '/' . $fileName;
      $disk = Storage::disk(config('backup.backup.destination.disks')[0]);

      if (!$disk->exists($filePath)) {
        return response()->json(['status' => 'error', 'message' => 'Backup file not found.']);
      }

      // Generate a proper local path to restore the backup
      $localPath = storage_path('app/' . $fileName);

      // Make sure the path is not duplicated
      $disk->copy($filePath, $localPath);

      // Assuming the backup is a SQL dump, you can restore it using `mysql` command
      // You might need to adjust this command based on your setup
      Artisan::call('db:restore', [
        '--path' => $localPath
      ], $output);

      // Get the output from the command
      $result = $output->fetch();

      // Remove the local file after restoration
      Storage::delete($localPath);

      if (str_contains($result, 'Restoration failed')) {
        return Error::response('Backup restoration failed');
      }

      return Success::response('Backup restored successfully');
    } catch (\Exception $e) {
      return Error::response('Backup restoration failed ' . $e->getMessage());
    }
  }

  public function getBackupListAjax()
  {
    try {
      $disk = Storage::disk(config('backup.backup.destination.disks')[0]);
      $files = $disk->files(config('backup.backup.name'));
      $backups = [];
      // make an array of backup files, with their filesize and creation date
      foreach ($files as $k => $f) {
        // only take the zip files into account
        if (str_ends_with($f, '.zip') && $disk->exists($f)) {
          $backups[] = [
            'file_path' => $f,
            'file_name' => str_replace(config('backup.backup.name') . '/', '', $f),
            'file_size' => Format::humanReadableSize($disk->size($f)),
            'last_modified' => Carbon::createFromTimestamp($disk->lastModified($f))->diffForHumans(),
          ];
        }
      }
      // reverse the backups, so the newest one would be on top
      $backups = array_reverse($backups);

      return Success::response($backups);
    } catch (\Exception $e) {
      return Error::response($e->getMessage());
    }
  }

  public function clearCache()
  {
    $command = "optimize:clear";

    $result = Artisan::call($command);

    if ($result == 0) {
      return redirect()->back()->with('success', 'All cache cleared successfully');
    } else {
      return redirect()->back()->with('error', 'Unable to clear cache');
    }
  }

  public function clearLog()
  {
    $command = "logs:clear";

    $result = Artisan::call($command);

    if ($result == 0) {
      return redirect()->back()->with('success', 'Log cleared successfully');
    } else {
      return redirect()->back()->with('error', 'Unable to clear log');
    }
  }

}
