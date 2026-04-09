<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\UserSettings;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
  public function getAll()
  {
    $userSettings = auth()->user()->userSettings();

    $response = [];
    foreach ($userSettings as $userSetting) {
      $response[$userSetting->key] = $userSetting->value;
    }

    return Success::response($response);
  }

  public function getByKey(Request $request)
  {
    $validated = $request->validate([
      'key' => 'required|string',
    ]);

    $userSetting = auth()->user()->userSettings()->first();

    if (!$userSetting) {
      return Error::response('Setting not found', 404);
    }

    return Success::response($userSetting->value);
  }

  public function addOrUpdate(Request $request)
  {
    $validated = $request->validate([
      'key' => 'required|string',
      'value' => 'required',
    ]);

    $userSetting = auth()->user()->userSettings()
      ->first();

    if (!$userSetting) {
      $userSetting = new UserSettings();
      $userSetting->user_id = auth()->id();
      $userSetting->key = $validated['key'];
    }

    $userSetting->value = $validated['value'];
    $userSetting->save();

    return Success::response('Setting saved successfully');
  }

  public function delete(Request $request)
  {
    $validated = $request->validate([
      'key' => 'required|string',
    ]);

    $userSetting = auth()->user()->userSettings()
      ->first();

    if (!$userSetting) {
      return Error::response('Setting not found', 404);
    }

    $userSetting->delete();

    return Success::response('Setting deleted successfully');
  }
}
