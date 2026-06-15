<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OutdoorDuty;
use App\Models\User;
use App\Notifications\Leave\LeaveRequestApproval;
use Constants;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Yajra\DataTables\Facades\DataTables;

class LeaveController extends Controller
{
  public function index()
  {
    $employees = User::select('id', 'first_name', 'last_name', 'code')
      ->where('status', 'active')
      ->orderBy('first_name')
      ->get();
    $leaveTypes = LeaveType::all();

    return view('tenant.leave.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'employees'   => $employees,
      'leaveTypes'  => $leaveTypes,
    ]);
  }

  public function getListAjax(Request $request)
  {
    try {
      $user = auth()->user();
      $isManager = $user->hasRole('manager') && !$user->hasRole(['admin', 'hr']);
      $isAccounts = $user->hasRole('accounts') && !$user->hasRole(['admin', 'hr']);
      
      $leaveQuery = LeaveRequest::query()->with(['user', 'leaveType', 'user.designation.department', 'approvedBy']);
      $odQuery = OutdoorDuty::query()->with(['user', 'user.designation.department', 'approvedBy']);

      if ($isManager) {
          $managedSubordinateIds = User::where('reporting_to_id', $user->id)->pluck('id')->toArray();
          $scopedUserIds = array_merge($managedSubordinateIds, [$user->id]);
          $leaveQuery->whereIn('user_id', $scopedUserIds);
          $odQuery->whereIn('user_id', $scopedUserIds);
      }

      if ($isAccounts) {
          $leaveQuery->where('status', LeaveRequestStatus::APPROVED);
          // Accounts typically only care about leaves, but we'll approve both to be safe
          $odQuery->where('status', 'approved');
      }

      // Apply Filters
      if ($request->filled('employeeFilter')) {
        $leaveQuery->where('user_id', $request->employeeFilter);
        $odQuery->where('user_id', $request->employeeFilter);
      }
      if ($request->filled('leaveTypeFilter')) {
        $leaveQuery->where('leave_type_id', $request->leaveTypeFilter);
        // Outdoor duties have no leave type, so we return empty if a specific leave type is selected
        $odQuery->whereRaw('1 = 0'); 
      }
      if ($request->filled('dateFilter')) {
        $leaveQuery->whereDate('created_at', $request->dateFilter);
        $odQuery->whereDate('date', $request->dateFilter);
      }
      if ($request->filled('monthFilter')) {
        $leaveQuery->whereMonth('created_at', $request->monthFilter);
        $odQuery->whereMonth('date', $request->monthFilter);
      }
      if ($request->filled('yearFilter')) {
        $leaveQuery->whereYear('created_at', $request->yearFilter);
        $odQuery->whereYear('date', $request->yearFilter);
      }
      if ($request->filled('statusFilter')) {
        $leaveQuery->where('status', $request->statusFilter);
        $odQuery->where('status', $request->statusFilter);
      }

      // Search
      $search = $request->input('searchTerm') ?? $request->input('search.value');
      if (!empty($search)) {
        $leaveQuery->where(function($q) use ($search) {
          $q->whereHas('user', function($qu) use ($search) {
            $qu->where('first_name', 'LIKE', "%{$search}%")->orWhere('last_name', 'LIKE', "%{$search}%");
          })->orWhereHas('leaveType', function($qu) use ($search) {
            $qu->where('name', 'LIKE', "%{$search}%");
          })->orWhere('user_notes', 'LIKE', "%{$search}%");
        });
        
        $odQuery->where(function($q) use ($search) {
          $q->whereHas('user', function($qu) use ($search) {
            $qu->where('first_name', 'LIKE', "%{$search}%")->orWhere('last_name', 'LIKE', "%{$search}%");
          })->orWhere('purpose', 'LIKE', "%{$search}%")->orWhere('location', 'LIKE', "%{$search}%");
        });
      }

      // Get collections
      $leaves = $leaveQuery->get()->map(function($item) {
          $name = $item->leaveType->name ?? 'N/A';
          if ($item->from_date && $item->from_date->lt($item->created_at->startOfDay())) {
              $name .= ' <span class="badge bg-label-warning ms-1" style="font-size:0.6rem;">BACK DATED</span>';
          }
          
          $dept = $item->user && $item->user->designation && $item->user->designation->department ? $item->user->designation->department->name : 'N/A';
          $statusVal = $item->status instanceof \App\Enums\LeaveRequestStatus ? $item->status->value : $item->status;
          $duration = $item->is_half_day ? 0.5 : ($item->from_date->diffInDays($item->to_date) + 1);
          $dateFmt = $item->from_date->format('d M, Y');
          if (!$item->from_date->equalTo($item->to_date)) {
              $dateFmt .= ' - ' . $item->to_date->format('d M, Y');
          }

          return [
              'unified_id' => 'leave_' . $item->id,
              'id' => $item->id,
              'item_type' => 'leave',
              'created_at' => $item->created_at,
              'user_name' => $item->user->getFullName(),
              'user_code' => $item->user->code,
              'user_profile_image' => $item->user->profile_picture ? asset('storage/' . \Constants::BaseFolderEmployeeProfileWithSlash . $item->user->profile_picture) : null,
              'user_initial' => $item->user->getInitials(),
              'department' => $dept,
              'request_type' => $name,
              'dates' => $dateFmt,
              'duration' => $duration . ' Days',
              'reason' => $item->user_notes ?? 'N/A',
              'status' => $statusVal,
              'approved_by_name' => $item->approvedBy ? $item->approvedBy->getFullName() : 'N/A',
              'attachment' => $item->document ? asset('storage/' . \Constants::BaseFolderLeaveDocumentWithSlash . $item->document) : null,
              'sort_date' => $item->created_at->timestamp
          ];
      });

      $ods = $odQuery->get()->map(function($item) {
          $dept = $item->user && $item->user->designation && $item->user->designation->department ? $item->user->designation->department->name : 'N/A';
          $reason = $item->purpose;
          if ($item->location) $reason .= " <small class='text-muted d-block'>Loc: {$item->location}</small>";
          
          return [
              'unified_id' => 'outdoor_' . $item->id,
              'id' => $item->id,
              'item_type' => 'outdoor',
              'created_at' => $item->created_at,
              'user_name' => $item->user->getFullName(),
              'user_code' => $item->user->code,
              'user_profile_image' => $item->user->profile_picture ? asset('storage/' . \Constants::BaseFolderEmployeeProfileWithSlash . $item->user->profile_picture) : null,
              'user_initial' => $item->user->getInitials(),
              'department' => $dept,
              'request_type' => 'Outdoor Duty',
              'dates' => $item->date->format('d M, Y'),
              'duration' => $item->expected_return_time ? "Return: " . $item->expected_return_time : '1 Day',
              'reason' => $reason,
              'status' => $item->status,
              'approved_by_name' => $item->approvedBy ? $item->approvedBy->getFullName() : 'N/A',
              'attachment' => null,
              'sort_date' => $item->created_at->timestamp
          ];
      });

      $merged = $leaves->concat($ods)->sortByDesc('sort_date')->values();

      return \Yajra\DataTables\Facades\DataTables::of($merged)->make(true);

    } catch (Exception $e) {
      Log::error('Unified getListAjax error: ' . $e->getMessage());
      return response()->json(['error' => $e->getMessage()], 500);
    }
  }

  public function actionAjax(Request $request)
  {
    if (auth()->user()->hasRole('accounts')) {
        return response()->json(['status' => 'error', 'message' => 'Unauthorized: Accounts role cannot perform this action.']);
    }

    $validated = $request->validate([
      'id' => 'required|exists:leave_requests,id',
      'status' => 'required|in:approved,rejected,cancelled',
      'adminNotes' => 'nullable|string',
    ]);

    try {

      $leaveRequest = LeaveRequest::findOrFail($validated['id']);
      $leaveRequest->status = $validated['status'];

      if ($validated['status'] == LeaveRequestStatus::CANCELLED) {
        $leaveRequest->cancel_reason = $validated['adminNotes'] ?? null;
        $leaveRequest->cancelled_at = now();
      } elseif ($validated['status'] == LeaveRequestStatus::APPROVED->value || $validated['status'] == 'approved') {
        $leaveRequest->approval_notes = $validated['adminNotes'] ?? null;
        $leaveRequest->approved_by_id = auth()->id();
        $leaveRequest->approved_at = now();
      } else {
        $leaveRequest->approval_notes = $validated['adminNotes'] ?? null;
        $leaveRequest->rejected_by_id = auth()->id();
        $leaveRequest->rejected_at = now();
      }

      $leaveRequest->save();

      Notification::send($leaveRequest->user, new LeaveRequestApproval($leaveRequest, $validated['status']));

      return response()->json(['status' => 'success', 'message' => 'Leave request ' . $validated['status'] . ' successfully.']);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return response()->json(['status' => 'error', 'message' => 'Something went wrong. Please try again.']);
    }
  }

  public function bulkActionAjax(Request $request)
  {
    if (auth()->user()->hasRole('accounts')) {
        return response()->json(['status' => 'error', 'message' => 'Unauthorized']);
    }
    $validated = $request->validate([
      'ids' => 'required|array',
      'status' => 'required|in:approved,rejected,cancelled',
    ]);

    try {
      $leaveIds = [];
      $outdoorIds = [];
      foreach ($validated['ids'] as $uid) {
          if (str_starts_with($uid, 'leave_')) $leaveIds[] = str_replace('leave_', '', $uid);
          elseif (str_starts_with($uid, 'outdoor_')) $outdoorIds[] = str_replace('outdoor_', '', $uid);
          else $leaveIds[] = $uid; // fallback for old UI
      }

      // Process Leaves
      if (count($leaveIds) > 0) {
          $leaveRequests = LeaveRequest::whereIn('id', $leaveIds)->get();
          foreach ($leaveRequests as $leaveRequest) {
            $newStatus = LeaveRequestStatus::tryFrom($validated['status']) ?? $validated['status'];
            $leaveRequest->status = $newStatus;
            if ($validated['status'] == 'approved') {
                $leaveRequest->approved_by_id = auth()->id();
                $leaveRequest->approved_at = now();
            } elseif ($validated['status'] == 'rejected') {
                $leaveRequest->rejected_by_id = auth()->id();
                $leaveRequest->rejected_at = now();
            }
            $leaveRequest->save();
            Notification::send($leaveRequest->user, new LeaveRequestApproval($leaveRequest, $validated['status']));
          }
      }

      // Process Outdoors
      if (count($outdoorIds) > 0) {
          $outdoorDuties = OutdoorDuty::whereIn('id', $outdoorIds)->get();
          foreach ($outdoorDuties as $duty) {
              $duty->status = $validated['status'];
              if ($validated['status'] === 'approved') {
                  $duty->approved_by_id = auth()->id();
                  $duty->approved_at = now();
              } elseif ($validated['status'] === 'rejected') {
                  $duty->rejected_by_id = auth()->id();
                  $duty->rejected_at = now();
              }
              $duty->save();
          }
      }

      return response()->json(['status' => 'success', 'message' => 'Requests ' . $validated['status'] . ' successfully.']);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return response()->json(['status' => 'error', 'message' => 'Something went wrong. Please try again.']);
    }
  }


  public function getByIdAjax($id)
  {
    $leaveRequest = LeaveRequest::findOrFail($id);

    if (!$leaveRequest) {
      return Error::response('Leave request not found');
    }

    $response = [
      'id' => $leaveRequest->id,
      'userName' => $leaveRequest->user->getFullName(),
      'userCode' => $leaveRequest->user->code,
      'leaveType' => $leaveRequest->leaveType->name . ($leaveRequest->from_date && $leaveRequest->from_date->lt($leaveRequest->created_at->startOfDay()) ? ' <span class="badge bg-label-warning ms-1" style="font-size:0.6rem;">BACK DATED</span>' : ''),
      'fromDate' => $leaveRequest->from_date->format(Constants::DateFormat),
      'toDate' => $leaveRequest->to_date->format(Constants::DateFormat),
      'document' => $leaveRequest->document != null ? asset('storage/'.Constants::BaseFolderLeaveRequestDocument . $leaveRequest->document) : null,
      'status' => $leaveRequest->status,
      'createdAt' => $leaveRequest->created_at->format(Constants::DateTimeFormat),
      'userNotes' => $leaveRequest->user_notes,
      'days' => $leaveRequest->is_half_day ? 0.5 : ($leaveRequest->from_date->diffInDays($leaveRequest->to_date) + 1),
      'isHalfDay' => (bool)$leaveRequest->is_half_day,
      'halfDaySession' => $leaveRequest->half_day_session,
      'userInitials' => $leaveRequest->user->getInitials()
    ];

    return Success::response($response);
  }

  // ── Outdoor Duty (lives inside the Leave page) ─────────────────────────────

  public function outdoorDutyListAjax(Request $request)
  {
    try {
      $user      = auth()->user();
      $isAdmin   = $user->hasRole(['admin', 'hr']);
      $isManager = $user->hasRole('manager') && !$isAdmin;

      $query = OutdoorDuty::with(['user', 'approvedBy'])->select('outdoor_duties.*');

      if ($isManager) {
        $subIds = User::where('reporting_to_id', $user->id)->pluck('id')->toArray();
        $query->whereIn('user_id', array_merge($subIds, [$user->id]));
      } elseif (!$isAdmin) {
        $query->where('user_id', $user->id);
      }

      if ($request->filled('employeeFilter'))  $query->where('user_id', $request->employeeFilter);
      if ($request->filled('statusFilter'))    $query->where('status', $request->statusFilter);
      if ($request->filled('monthFilter'))     $query->whereMonth('date', $request->monthFilter);
      if ($request->filled('yearFilter'))      $query->whereYear('date', $request->yearFilter);
      if ($request->filled('dateFilter'))      $query->whereDate('date', $request->dateFilter);

      $search = $request->input('searchTerm') ?? $request->input('search.value');
      if (!empty($search)) {
        $query->where(function ($q) use ($search) {
          $q->whereHas('user', fn($qu) =>
            $qu->where('first_name', 'LIKE', "%{$search}%")
               ->orWhere('last_name', 'LIKE', "%{$search}%")
               ->orWhere('code', 'LIKE', "%{$search}%")
          )->orWhere('purpose', 'LIKE', "%{$search}%")
           ->orWhere('location', 'LIKE', "%{$search}%");
        });
      }

      return DataTables::of($query)
        ->addColumn('user_name',          fn($r) => $r->user->getFullName())
        ->addColumn('user_code',          fn($r) => $r->user->code ?? '')
        ->addColumn('user_profile_image', fn($r) => $r->user->profile_picture
          ? asset('storage/' . \Constants::BaseFolderEmployeeProfileWithSlash . $r->user->profile_picture) : null)
        ->addColumn('user_initial',       fn($r) => $r->user->getInitials())
        ->addColumn('date_fmt',           fn($r) => $r->date->format('d M, Y'))
        ->addColumn('approved_by_name',   fn($r) => $r->approvedBy?->getFullName() ?? 'N/A')
        ->addColumn('approved_at_fmt',    fn($r) => $r->approved_at?->format('d/m H:i') ?? 'N/A')
        ->make(true);
    } catch (Exception $e) {
      Log::error('OutdoorDuty list error: ' . $e->getMessage());
      return response()->json(['error' => $e->getMessage()], 500);
    }
  }

  public function outdoorDutyStoreAjax(Request $request)
  {
    $validated = $request->validate([
      'od_user_id'           => 'nullable|exists:users,id',
      'od_date'              => 'required|date',
      'od_purpose'           => 'required|string|max:500',
      'od_location'          => 'nullable|string|max:255',
      'od_expected_return'   => 'nullable|string|max:15',
    ]);

    try {
      $user   = auth()->user();
      $userId = ($user->hasRole(['admin', 'hr', 'manager']) && !empty($validated['od_user_id']))
        ? $validated['od_user_id'] : $user->id;

      $existing = OutdoorDuty::where('user_id', $userId)
        ->whereDate('date', $validated['od_date'])
        ->whereIn('status', ['pending', 'approved'])->first();

      if ($existing) {
        return response()->json(['status' => 'error', 'message' => 'An outdoor duty request already exists for this date.']);
      }

      $duty = OutdoorDuty::create([
        'user_id'              => $userId,
        'date'                 => $validated['od_date'],
        'purpose'              => $validated['od_purpose'],
        'location'             => $validated['od_location'] ?? null,
        'expected_return_time' => $validated['od_expected_return'] ?? null,
        'status'               => 'pending',
        'created_by_id'        => $user->id,
        'updated_by_id'        => $user->id,
      ]);

      $duty->load('user');

      // Notify manager + HR
      $approvers = User::where(function ($q) use ($duty) {
        $q->whereHas('roles', fn($r) => $r->whereIn('name', ['hr', 'admin']))
          ->orWhere('id', optional($duty->user->reportingTo)->id);
      })->where('status', 'active')->get();

      foreach ($approvers as $approver) {
        try {
          // Outdoor duty notification logic pending NewOutdoorDuty notification class
        } catch (Exception $e) { /* non-fatal */ }
      }

      return response()->json(['status' => 'success', 'message' => 'Outdoor duty request submitted successfully.']);
    } catch (Exception $e) {
      Log::error('OutdoorDuty store error: ' . $e->getMessage());
      return response()->json(['status' => 'error', 'message' => 'Something went wrong. Please try again.']);
    }
  }

  public function outdoorDutyActionAjax(Request $request)
  {
    $user = auth()->user();
    if (!$user->hasRole(['admin', 'hr', 'manager'])) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized.']);
    }

    $validated = $request->validate([
      'id'         => 'required|exists:outdoor_duties,id',
      'status'     => 'required|in:approved,rejected,cancelled',
      'adminNotes' => 'nullable|string|max:500',
    ]);

    try {
      $duty = OutdoorDuty::findOrFail($validated['id']);
      $duty->status        = $validated['status'];
      $duty->updated_by_id = $user->id;

      if ($validated['status'] === 'approved') {
        $duty->approved_by_id = $user->id;
        $duty->approved_at    = now();
        $duty->approval_notes = $validated['adminNotes'] ?? null;
        $this->markOutdoorDutyAttendance($duty);
      } elseif ($validated['status'] === 'rejected') {
        $duty->rejected_by_id = $user->id;
        $duty->rejected_at    = now();
        $duty->approval_notes = $validated['adminNotes'] ?? null;
      } else {
        $duty->cancel_reason = $validated['adminNotes'] ?? null;
        $duty->cancelled_at  = now();
      }

      $duty->save();

      return response()->json(['status' => 'success', 'message' => 'Outdoor duty ' . $validated['status'] . ' successfully.']);
    } catch (Exception $e) {
      Log::error('OutdoorDuty action error: ' . $e->getMessage());
      return response()->json(['status' => 'error', 'message' => 'Something went wrong. Please try again.']);
    }
  }

  public function outdoorDutyGetByIdAjax($id)
  {
    $duty = OutdoorDuty::with('user', 'approvedBy')->findOrFail($id);
    return response()->json([
      'id'                 => $duty->id,
      'userName'           => $duty->user->getFullName(),
      'userCode'           => $duty->user->code,
      'userInitials'       => $duty->user->getInitials(),
      'date'               => $duty->date->format('d M, Y'),
      'purpose'            => $duty->purpose,
      'location'           => $duty->location ?? '—',
      'expectedReturn'     => $duty->expected_return_time ?? '—',
      'status'             => $duty->status,
      'approvalNotes'      => $duty->approval_notes,
      'approvedByName'     => $duty->approvedBy?->getFullName(),
      'approvedAt'         => $duty->approved_at?->format('d M Y, H:i'),
      'createdAt'          => $duty->created_at->format('d M Y, H:i'),
    ]);
  }

  private function markOutdoorDutyAttendance(OutdoorDuty $duty): void
  {
    try {
      $date       = $duty->date;
      $attendance = Attendance::firstOrNew(['user_id' => $duty->user_id,
        'check_in_time' => $date->copy()->setTime(9, 0, 0)]);

      // Don't overwrite a real biometric punch — only write if no record or if it's already an OD record
      if (!$attendance->exists || $attendance->is_outdoor_duty) {
        $attendance->fill([
          'user_id'         => $duty->user_id,
          'check_in_time'   => $date->copy()->setTime(9, 0, 0),
          'check_out_time'  => $date->copy()->setTime(18, 0, 0),
          'status'          => Attendance::STATUS_PRESENT,
          'is_outdoor_duty' => true,
          'outdoor_duty_id' => $duty->id,
          'approved_by_id'  => $duty->approved_by_id,
          'approved_at'     => now(),
          'admin_reason'    => 'Outdoor Duty',
        ])->save();
      }
    } catch (Exception $e) {
      Log::error('OutdoorDuty attendance mark error: ' . $e->getMessage());
    }
  }
}
