<?php

namespace App\Http\Controllers\tenant;

use Exception;
use App\Enums\Status;
use App\Models\Holiday;
use App\Models\Location;
use App\ApiClasses\Error;
use App\ApiClasses\Success;
use Illuminate\Http\Request;
use App\Imports\HolidaysImport;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;
use Constants;

class HolidayController extends Controller
{
  public function index()
  {
    $total = Holiday::count();
    $upcoming = Holiday::where('date', '>=', now())->count();
    $past = Holiday::where('date', '<', now())->count();
    $sites = \App\Models\Site::all();

    return view('tenant.holidays.index', [
      'stats' => [
        'total' => $total,
        'upcoming' => $upcoming,
        'past' => $past,
      ],
      'sites' => $sites,
      'pageConfigs' => ['contentLayout' => 'wide']
    ]);
  }

  public function indexAjax(Request $request)
  {
    try {
      $columns = [
        1 => 'id',
        2 => 'name',
        3 => 'code',
        4 => 'date',
        5 => 'site_id',
        6 => 'notes',
        7 => 'status',
      ];

      $search = [];

      $totalData = Holiday::count();

      $totalFiltered = $totalData;

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');

      $query = Holiday::with('site');

      if (!empty($request->input('search.value'))) {
        $search = $request->input('search.value');
        $query->where(function($q) use ($search) {
          $q->where('id', 'like', "%{$search}%")
            ->orWhere('name', 'like', "%{$search}%")
            ->orWhere('code', 'like', "%{$search}%")
            ->orWhere('notes', 'like', "%{$search}%");
        });
        $totalFiltered = $query->count();
      }

      $holidays = $query->offset($start)
        ->limit($limit)
        ->orderBy($order, $dir)
        ->get();

      $data = [];
      if (!empty($holidays)) {
        foreach ($holidays as $holiday) {
          $nestedData['id'] = $holiday->id;
          $nestedData['name'] = $holiday->name;
          $nestedData['code'] = $holiday->code;
          $nestedData['date'] = $holiday->date->format(Constants::DateFormat);
          $nestedData['site_name'] = $holiday->site ? $holiday->site->name : 'All Units';
          $nestedData['notes'] = $holiday->notes;
          $nestedData['status'] = $holiday->status;
          $nestedData['action'] = '<div class="d-flex align-items-center justify-content-center gap-2">' .
            '<a href="javascript:;" class="text-hitech edit-record me-2" data-id="' . $holiday->id . '" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateHoliday" title="Edit"><i class="bx bx-edit fs-4"></i></a>' .
            '<a href="javascript:;" class="text-danger delete-record" data-id="' . $holiday->id . '" title="Delete"><i class="bx bx-trash fs-4"></i></a>' .
            '</div>';
          $data[] = $nestedData;
        }
      }
      return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => intval($totalData),
        'recordsFiltered' => intval($totalFiltered),
        'code' => 200,
        'data' => $data
      ]);
    } catch (\Exception $e) {
      return Error::response($e->getMessage());
    }
  }

  public function addOrUpdateHolidayAjax(Request $request)
  {
    $holidayId = $request->id;
    $request->validate([
      'name' => 'required',
      'code' => ['required', 'unique:holidays,code,' . $holidayId],
      'date' => 'required',
      'site_id' => 'nullable|exists:sites,id',
      'notes' => 'nullable',
    ]);

    $data = [
      'name' => $request->name,
      'code' => $request->code,
      'date' => $request->date,
      'site_id' => $request->site_id,
      'notes' => $request->notes,
    ];

    if ($holidayId) {
      $holiday = Holiday::find($holidayId);
      $holiday->update($data);
      return Success::response('Updated');
    } else {
      Holiday::create($data);
      return Success::response('Added');
    }
  }

  public function getByIdAjax($id)
  {
    $holiday = Holiday::findOrFail($id);

    $response = [
      'id' => $holiday->id,
      'name' => $holiday->name,
      'code' => $holiday->code,
      'date' => $holiday->date->format('Y-m-d'),
      'site_id' => $holiday->site_id,
      'notes' => $holiday->notes
    ];

    return Success::response($response);
  }

  public function deleteAjax($id)
  {
    $holiday = Holiday::findOrFail($id);
    if (!$holiday) {
      return Error::response('Holiday not found');
    }

    $holiday->delete();
    return Success::response('Holiday deleted successfully');
  }

  public function changeStatusAjax($id)
  {
    $holiday = Holiday::findOrFail($id);
    if (!$holiday) {
      return Error::response('Holiday not found');
    }

    $holiday->status = $holiday->status == Status::ACTIVE ? Status::INACTIVE : Status::ACTIVE;
    $holiday->save();
    return Success::response('Holiday status changed successfully');
  }
}
