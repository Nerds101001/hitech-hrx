<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Visit;
use App\Notifications\NewVisit;
use Carbon\Carbon;
use Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VisitController extends Controller
{

  public function create(Request $request)
  {

    $file = $request->file('file');
    $clientId = $request->clientId;
    $remarks = $request->remarks;
    $latitude = $request->latitude;
    $longitude = $request->longitude;
    $address = $request->address;

    if ($file == null) {
      return Error::response('File is required');
    }

    if ($clientId == null) {
      return Error::response('Client Id is required');
    }

    if ($remarks == null) {
      return Error::response('Remarks is required');
    }

    if ($latitude == null) {
      return Error::response('Latitude is required');
    }

    if ($longitude == null) {
      return Error::response('Longitude is required');
    }

    $client = Client::find($clientId);

    if ($client == null) {
      return Error::response('Client not found');
    }

    $attendance = Attendance::where('user_id', auth()->user()->id)
      ->whereDate('created_at', Carbon::now())
      ->first();

    if ($attendance == null) {
      return Error::response('Attendance not found');
    }

    $fileName = time() . '_' . $file->getClientOriginalName();

    Storage::disk('public')->putFileAs(Constants::BaseFolderVisitImages, $file, $fileName);

    $visit = Visit::create([
      'client_id' => $client->id,
      'attendance_log_id' => $attendance->todaysLatestAttendanceLog()->id,
      'remarks' => $remarks,
      'latitude' => $latitude,
      'longitude' => $longitude,
      'address' => $address,
      'created_by_id' => auth()->user()->id,
      'img_url' => $fileName
    ]);

    NotificationHelper::notifyAdminHR(new NewVisit($visit));

    return Success::response('Visit created successfully');

  }

  public function getVisitsCount()
  {
    $todaysVisits = Visit::where('created_by_id', auth()->user()->id)
      ->whereDate('created_at', Carbon::now())
      ->count();

    $totalVisits = Visit::where('created_by_id', auth()->user()->id)
      ->count();

    return Success::response([
      'todaysVisits' => $todaysVisits,
      'totalVisits' => $totalVisits
    ]);
  }

  public function getHistory(Request $request)
  {
    $skip = $request->skip;
    $take = $request->take ?? 10;

    $visits = Visit::query()
      ->where('created_by_id', auth()->id())
      ->with('client')
      ->orderBy('created_at', 'desc');

    if ($request->has('clientId')) {
      $visits->where('client_id', $request->clientId);
    }

    if ($request->has('date') && !empty($request->date)) {
      $visits->whereDate('created_at', $request->date);
    }

    $totalCount = $visits->count();

    $visits = $visits->skip($skip)->take($take)->get();

    $visitHistory = $visits->map(function ($visit) {
      return [
        'id' => $visit->id,
        'clientAddress' => $visit->address,
        'clientName' => $visit->client->name,
        'visitImage' => asset('storage/'.Constants::BaseFolderVisitImages . $visit->img_url),
        'latitude' => floatval($visit->latitude),
        'longitude' => floatval($visit->longitude),
        'visitRemarks' => $visit->remarks,
        'visitDateTime' => $visit->created_at->format(Constants::DateTimeFormat),
        'clientContactPerson' => $visit->client->contact_person,
        'clientEmail' => $visit->client->email,
        'clientPhoneNumber' => $visit->client->phone,
      ];
    });

    $response = [
      'totalCount' => $totalCount,
      'values' => $visitHistory
    ];

    return Success::response($response);
  }
}
