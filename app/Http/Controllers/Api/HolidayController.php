<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Success;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Carbon\Carbon;
use Constants;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
  public function getAll(Request $request)
  {

    $skip = $request->skip;
    $take = $request->take ?? 10;

    $user = auth()->user();
    $query = Holiday::query()
      ->select('holidays.id', 'holidays.name', 'holidays.code', 'holidays.date', 'holidays.created_at', 'holidays.updated_at')
      ->orderBy('holidays.created_at', 'desc')
      ->where('status', Status::ACTIVE)
      ->where(function($q) use ($user) {
          $q->whereNull('site_id')
            ->orWhere('site_id', $user->site_id);
      });


    if ($request->has('year')) {
      $year = $request->year;
      $query->whereYear('holidays.date', $year);
    }

    $totalCount = $query->count();

    $holidays = $query->skip($skip)->take($take)->get();

    $holidays = $holidays->map(function ($holiday) {
      return [
        'id' => $holiday->id,
        'name' => $holiday->name,
        'date' => Carbon::parse($holiday->date)->format(Constants::DateFormat),
        'created_at' => Carbon::parse($holiday->created_at)->format(Constants::DateTimeFormat),
        'updated_at' => Carbon::parse($holiday->updated_at)->format(Constants::DateTimeFormat),
      ];
    });

    $response = [
      'totalCount' => $totalCount,
      'values' => $holidays
    ];

    return Success::response($response);
  }
}
