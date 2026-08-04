<?php

namespace App\Http\Controllers\tenant;

use Exception;
use App\Enums\Status;
use App\Models\Holiday;
use App\ApiClasses\Error;
use App\ApiClasses\Success;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
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

  /**
   * Holiday Dashboard — HR/admin calendar management, laid out like the
   * Employee Directory: clickable stat cards, search, filters, sortable
   * server-side table, and add/edit with a location (site) selector.
   */
  public function dashboard(Request $request)
  {
    $year = (int) ($request->input('year') ?: now()->year);

    $base = fn () => Holiday::query()->whereYear('date', $year);

    $statCounts = [
      'total'      => $base()->count(),
      'upcoming'   => $base()->whereDate('date', '>=', now()->toDateString())->count(),
      'this_month' => $base()->whereMonth('date', now()->month)->count(),
      'global'     => $base()->whereNull('site_id')->count(),
      'location'   => $base()->whereNotNull('site_id')->count(),
    ];

    // Years that actually have holidays, so the year picker never offers empties
    $years = Holiday::selectRaw('DISTINCT YEAR(date) as y')
      ->orderByDesc('y')->pluck('y')->all();
    if (!in_array($year, $years, true)) {
      $years[] = $year;
      rsort($years);
    }

    return view('tenant.holidays.dashboard', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'statCounts'  => $statCounts,
      'sites'       => \App\Models\Site::orderBy('name')->get(['id', 'name']),
      'departments' => \App\Models\Department::withTrashed()->orderBy('name')->get(['id', 'name']),
      'year'        => $year,
      'years'       => $years,
    ]);
  }

  /** Server-side data for the Holiday Dashboard table. */
  public function dashboardAjax(Request $request)
  {
    try {
      $query = Holiday::with(['site', 'department'])->select('holidays.*');

      if ($request->filled('yearFilter')) {
        $query->whereYear('date', $request->yearFilter);
      }
      if ($request->filled('siteFilter')) {
        // 'global' = company-wide rows only
        $request->siteFilter === 'global'
          ? $query->whereNull('site_id')
          : $query->where('site_id', $request->siteFilter);
      }
      if ($request->filled('monthFilter')) {
        $query->whereMonth('date', $request->monthFilter);
      }
      if ($request->filled('departmentFilter')) {
        $request->departmentFilter === 'none'
          ? $query->whereNull('department_id')
          : $query->where('department_id', $request->departmentFilter);
      }
      if ($request->filled('statusFilter')) {
        $query->where('status', $request->statusFilter);
      }
      if ($request->filled('scopeFilter')) {
        match ($request->scopeFilter) {
          'upcoming' => $query->whereDate('date', '>=', now()->toDateString()),
          'past'     => $query->whereDate('date', '<', now()->toDateString()),
          'global'   => $query->whereNull('site_id'),
          'location' => $query->whereNotNull('site_id'),
          default    => null,
        };
      }
      if ($request->filled('searchTerm')) {
        $term = $request->searchTerm;
        $query->where(function ($q) use ($term) {
          $q->where('name', 'like', "%{$term}%")
            ->orWhere('code', 'like', "%{$term}%")
            ->orWhere('notes', 'like', "%{$term}%");
        });
      }

      return DataTables::of($query)
        ->addColumn('date_formatted', fn ($h) => $h->date->format('d M Y'))
        ->addColumn('day_name', fn ($h) => $h->date->format('l'))
        ->addColumn('type_label', fn ($h) => $h->type_label)
        ->addColumn('is_part_day', fn ($h) => $h->isPartDay())
        ->addColumn('gender_scope', fn ($h) => $h->applies_to_gender ?? 'all')
        ->addColumn('site_name', fn ($h) => $h->site->name ?? 'All Locations')
        ->addColumn('dept_name', fn ($h) => $h->department->name ?? null)
        ->addColumn('is_global', fn ($h) => is_null($h->site_id))
        ->addColumn('status_value', fn ($h) => $h->status instanceof Status ? $h->status->value : $h->status)
        ->addColumn('is_past', fn ($h) => $h->date->lt(now()->startOfDay()))
        ->addColumn('notes_text', fn ($h) => $h->notes ?: '')
        ->orderColumn('date_formatted', 'date $1')
        ->orderColumn('site_name', 'site_id $1')
        ->rawColumns([])
        ->make(true);
    } catch (\Exception $e) {
      Log::error('Holiday dashboardAjax: ' . $e->getMessage());
      return Error::response($e->getMessage());
    }
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
      'name' => 'required|string|max:191',
      'code' => ['nullable', 'unique:holidays,code,' . $holidayId],
      'date' => 'required|date',
      'site_id' => 'nullable|exists:sites,id',
      'site_ids' => 'nullable|array',
      'site_ids.*' => 'exists:sites,id',
      'department_ids' => 'nullable|array',
      'department_ids.*' => 'exists:departments,id',
      'notes' => 'nullable|string|max:500',
      'holiday_type' => 'nullable|in:full,half,hours',
      'hours_off' => 'nullable|numeric|min:0.5|max:7|required_if:holiday_type,hours',
      'session' => 'nullable|in:morning,afternoon',
      'applies_to_gender' => 'nullable|in:all,male,female',
    ]);

    $type = $request->holiday_type ?: 'full';

    $data = [
      'name' => $request->name,
      'date' => $request->date,
      'notes' => $request->notes,
      'holiday_type' => $type,
      // hours_off is only meaningful for the 'hours' type; clear it otherwise
      // so switching a row back to full/half can't leave a stale value behind
      'hours_off' => $type === 'hours' ? $request->hours_off : null,
      'session' => $type === 'full' ? null : ($request->session ?: null),
      'applies_to_gender' => $request->applies_to_gender ?: 'all',
    ];

    // Locations / departments are multi-select. One row is stored per
    // location x department pair, which keeps the existing per-row scoping
    // (and the dashboard's one-line-per-location view) working unchanged.
    // An empty list means "not restricted by that dimension" -> [null].
    $siteIds = array_values(array_filter((array) $request->input('site_ids', [])));
    $deptIds = array_values(array_filter((array) $request->input('department_ids', [])));

    // Single-value fallback so older callers / the legacy page still work
    if (!$siteIds && $request->filled('site_id')) {
      $siteIds = [$request->site_id];
    }
    if (!$siteIds) { $siteIds = [null]; }
    if (!$deptIds) { $deptIds = [null]; }

    if ($holidayId) {
      // Editing one row: update it in place using the first pair, then add
      // rows for any extra locations/departments newly ticked.
      $holiday = Holiday::findOrFail($holidayId);
      $holiday->update($data + [
        'site_id' => $siteIds[0],
        'department_id' => $deptIds[0],
      ]);

      $extra = 0;
      foreach ($siteIds as $si) {
        foreach ($deptIds as $di) {
          if ($si === $siteIds[0] && $di === $deptIds[0]) {
            continue; // already handled by the update above
          }
          if ($this->holidayExists($data['name'], $data['date'], $si, $di)) {
            continue;
          }
          Holiday::create($data + [
            'code' => $this->generateHolidayCode(),
            'site_id' => $si,
            'department_id' => $di,
          ]);
          $extra++;
        }
      }

      return Success::response($extra
        ? "Updated, and added to {$extra} more location/department combination(s)"
        : 'Updated');
    }

    $created = 0; $skipped = 0;
    foreach ($siteIds as $si) {
      foreach ($deptIds as $di) {
        if ($this->holidayExists($data['name'], $data['date'], $si, $di)) {
          $skipped++;
          continue;
        }
        Holiday::create($data + [
          'code' => $this->generateHolidayCode(),
          'site_id' => $si,
          'department_id' => $di,
        ]);
        $created++;
      }
    }

    $msg = $created === 1 ? 'Holiday added' : "Holiday added for {$created} location/department combination(s)";
    if ($skipped) {
      $msg .= " ({$skipped} already existed)";
    }

    return Success::response($msg);
  }

  /** Guards against creating the same holiday twice for one scope. */
  private function holidayExists(string $name, string $date, $siteId, $deptId): bool
  {
    return Holiday::whereDate('date', $date)
      ->where('name', $name)
      ->where('site_id', $siteId)
      ->where('department_id', $deptId)
      ->exists();
  }

  /** Next free HOL#### code (the prefix in Settings when one is configured). */
  private function generateHolidayCode(): string
  {
    $prefix = optional(\App\Models\Settings::first())->holiday_code_prefix ?: 'HOL';
    do {
      $code = $prefix . str_pad((string) (Holiday::withTrashed()->max('id') + 1 + ($i ?? 0)), 4, '0', STR_PAD_LEFT);
      $i = ($i ?? 0) + 1;
    } while (Holiday::withTrashed()->where('code', $code)->exists());

    return $code;
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
      'site_ids' => $holiday->site_id ? [(string) $holiday->site_id] : [],
      'department_id' => $holiday->department_id,
      'department_ids' => $holiday->department_id ? [(string) $holiday->department_id] : [],
      'notes' => $holiday->notes,
      'holiday_type' => $holiday->holiday_type ?? 'full',
      'hours_off' => $holiday->hours_off,
      'session' => $holiday->session,
      'applies_to_gender' => $holiday->applies_to_gender ?? 'all',
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
