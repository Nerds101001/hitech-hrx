<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\SOSLog;
use Constants;
use Illuminate\Http\Request;

class SOSController extends Controller
{
  public function create(Request $request)
  {
    $latitude = $request->latitude;
    $longitude = $request->longitude;
    $address = $request->address;

    if (!$latitude || !$longitude) {
      return Error::response('Location is required');
    }

    $sosLog = new SOSLog();
    $sosLog->user_id = auth()->id();
    $sosLog->latitude = $latitude;
    $sosLog->longitude = $longitude;
    $sosLog->address = $address;
    $sosLog->status = 'pending';
    $sosLog->save();

    // Create and send the notification
    $notification = new \App\Notifications\SOSAlert($sosLog);
    NotificationHelper::notifyAllExceptMe($notification);

    return Success::response('SOS created successfully');
  }

  public function getAll()
  {
    $sosLogs = SOSLog::where('user_id', auth()->id())
      ->get();

    $response = $sosLogs->map(function ($sosLog) {
      return [
        'id' => $sosLog->id,
        'latitude' => $sosLog->latitude,
        'longitude' => $sosLog->longitude,
        'address' => $sosLog->address,
        'createdOn' => $sosLog->created_at->format(Constants::DateTimeFormat),
      ];
    });

    return Success::response($response);
  }
}
