<?php

namespace App\Http\Controllers;

use ZipArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Nwidart\Modules\Facades\Module;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class AddonController extends Controller
{

  public function index()
  {
    // Get all available modules
    $modules = Module::all();

    return view('addons.index', [
      'modules' => $modules
    ]);
  }

  // Enable a module (addon)
  public function activate(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $moduleName = $request->input('module');

    // Enable the module using Artisan
    Artisan::call('module:enable', ['module' => $moduleName]);

    return redirect()->back()->with('success', 'Module enabled successfully.');
  }

  // Disable a module (addon)
  public function deactivate(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $moduleName = $request->input('module');

    // Disable the module using Artisan
    Artisan::call('module:disable', ['module' => $moduleName]);

    return redirect()->back()->with('success', 'Module disabled successfully.');
  }

  // Upload and install a new module (addon)
  public function upload(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    // Validate the file input, ensuring it is a zip file
    $request->validate([
      'module' => 'required|file|mimes:zip|max:20480', // Limit file size to 20MB
    ]);

    // Store the uploaded file temporarily
    $file = $request->file('module');
    $fileName = $file->getClientOriginalName();
    $tempPath = storage_path('modules/' . $fileName);
    $file->move(storage_path('modules'), $fileName);

    // Extract the zip file to a temporary location
    $zip = new ZipArchive();
    if ($zip->open($tempPath) === TRUE) {
      // Get the base filename without any extension or extra path
      $moduleFolderName = pathinfo($fileName, PATHINFO_FILENAME);

      // Define the extraction path using just the module name
      $extractPath = storage_path('modules/extracted/');

      // Extract the zip to the extraction path
      $zip->extractTo($extractPath);
      $zip->close();
    } else {
      return redirect()->back()->with('error', 'Failed to extract the module.');
    }

    // Validate that the extracted directory contains module.json (and possibly other expected files)
    if (!File::exists($extractPath . $moduleFolderName . '/module.json')) {
      // If no module.json is found, delete extracted files and return an error
      File::deleteDirectory($extractPath);
      //Delete the zip file
      File::delete($tempPath);
      return redirect()->back()->with('error', 'Invalid addon: module.json not found.');
    }

    //Check if the same module is already installed
    if (Module::find($moduleFolderName)) {
      // If the module is already installed, delete extracted files and return an error
      File::deleteDirectory($extractPath);
      //Delete the zip file
      File::delete($tempPath);
      return redirect()->back()->with('error', 'Module already installed.');
    }

    // Move the extracted module to the Modules directory
    File::moveDirectory($extractPath . $moduleFolderName, base_path('Modules/' . pathinfo($fileName, PATHINFO_FILENAME)));

    // Clean up: delete the uploaded zip file
    File::delete($tempPath);

    return redirect()->back()->with('success', 'Module uploaded successfully.');

  }

  public function uninstall(Request $request)
  {

    try{

    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $moduleName = $request->input('module');

    // Disable the module before uninstalling
    Artisan::call('module:disable', ['module' => $moduleName]);

    // Remove the module's directory
    $modulePath = base_path('Modules/' . $moduleName);
    if (File::exists($modulePath)) {
      File::deleteDirectory($modulePath);
    }

    // You might also want to clean up any module-specific database tables here.
    // Example: \Artisan::call('module:migrate-rollback', ['module' => $moduleName]);

    }catch(\Exception $e){
      Log::error($e->getMessage());
    }

    return redirect()->back()->with('success', 'Module uninstalled successfully.');
  }

  public function update(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $moduleName = $request->input('module');

    // Logic for updating the module
    // This could involve downloading the latest version of the module and replacing the old files

    return redirect()->back()->with('success', 'Module updated successfully.');
  }

}
