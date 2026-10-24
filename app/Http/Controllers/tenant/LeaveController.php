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
  /**
   * Two approval tiers, additive on top of today's role defaults:
   *  - leave.approve.all  : anyone's request (admin/hr already get this by role)
   *  - leave.approve.team : only people who report to you (manager already
   *    gets this by role, scoped the same way getListAjax() already scopes
   *    what a manager SEES — this just makes the action agree with the list).
   *
   * Before this, actionAjax()/bulkActionAjax() had no scope check at all: any
   * non-accounts user who could reach this page could approve/reject ANY
   * leave request by id, not just the ones the list ever showed them. The
   * UI never sent an out-of-scope id, so this was invisible in normal use,
   * but nothing stopped a crafted request. Both action methods now check
   * this before writing, using the identical managerIdentityIds() scope the
   * list already uses — so an already-legitimate approval keeps working
   * exactly as before, and only an out-of-scope id newly gets rejected.
   */
  private function canApproveFor($actor, int $targetUserId): bool
  {
    if ($actor->hasRole(['admin', 'hr']) || $actor->can('leave.approve.all')) {
        return true;
    }
    if ($actor->hasRole('manager') || $actor->can('leave.approve.team')) {
        $scopedIds = User::whereIn('reporting_to_id', $actor->managerIdentityIds())
            ->notTerminated()->pluck('id')->toArray();
        return in_array($targetUserId, $scopedIds, true);
    }
    return false;
  }

  /**
   * Who an approval should be recorded as coming from: the employee's own
   * manager when that manager is a vacant-position account delegated to the
   * person actually clicking Approve, otherwise the real actor. This is
   * computed from the employee's actual reporting line and the delegate
   * pointer on their manager's account — never from client input — so there
   * is nothing here for a request to spoof. An admin approving a report of
   * GM-IT (delegated to them) is recorded as "approved by GM-IT", exactly as
   * if they had switched into that account first; approving anyone else's
   * request is unaffected and still records their own id, as before.
   */
  private function approverIdFor($actor, int $targetUserId): int
  {
    $managerId = User::find($targetUserId)?->reporting_to_id;
    if ($managerId) {
        $manager = User::find($managerId);
        if ($manager && (int) $manager->access_delegate_id === (int) $actor->id) {
            return $manager->id;
        }
    }
    return $actor->id;
  }

  public function index()
  {
    $employees = User::select('id', 'first_name', 'last_name', 'code')
      ->where('status', 'active')
      ->orderBy('first_name')
      ->get();
    // Every type someone can apply for, plus retired types that still have
    // requests on record (so those stay findable), active ones first. Outdoor
    // Duty is added as its own option in the view.
    $usedTypeIds = LeaveRequest::query()->distinct()->pluck('leave_type_id')->all();
    $leaveTypes = LeaveType::withTrashed()
      ->where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $usedTypeIds))
      ->get()
      ->sortBy(fn ($t) => [($t->status->value ?? $t->status) === 'active' && !$t->deleted_at ? 0 : 1, $t->name])
      ->values();

    return view('tenant.leave.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'employees'   => $employees,
      'leaveTypes'  => $leaveTypes,
      // The "Delegate Access" tab only makes sense for someone who actually
      // delegates for at least one vacant-position account.
      'hasDelegatedAccounts' => User::where('access_delegate_id', auth()->id())->exists(),
    ]);
  }

  public function getListAjax(Request $request)
  {
    try {
      $user = auth()->user();
      // Was role-only, out of step with canApproveFor() below which also
      // honours the leave.approve.team permission — someone holding only
      // that permission (e.g. a delegate for a vacant manager seat, with no
      // reports of their own) fell through to the unscoped branch and saw
      // every pending leave in the company, though canApproveFor() still
      // correctly refused to let them act on any outside their own scope.
      $isManager = ($user->hasRole('manager') || $user->can('leave.approve.team')) && !$user->hasRole(['admin', 'hr']);
      $isAccounts = $user->hasRole('accounts') && !$user->hasRole(['admin', 'hr']);
      
      $leaveQuery = LeaveRequest::query()->with(['user', 'user.department', 'approvedBy', 'leaveType' => function ($q) {
        $q->withTrashed(); // old requests may point to soft-deleted types; still show their name
      }]);
      $odQuery = OutdoorDuty::query()->with(['user', 'user.department', 'approvedBy']);

      if ($isManager) {
          $managedSubordinateIds = User::whereIn('reporting_to_id', $user->managerIdentityIds())->notTerminated()->pluck('id')->toArray();
          $scopedUserIds = array_merge($managedSubordinateIds, [$user->id]);
          $leaveQuery->whereIn('user_id', $scopedUserIds);
          $odQuery->whereIn('user_id', $scopedUserIds);
      }

      // "Delegate Access" tab: only the teams of vacant-position accounts
      // delegated to this user (not their own reports, not everyone else's —
      // even an admin, who otherwise sees the whole company unscoped here,
      // gets narrowed to just this when they ask for it). Empty list for
      // someone with no delegated accounts, rather than falling through to
      // an unscoped/wrong result.
      if ($request->boolean('delegateScope')) {
          $delegatedAccountIds = User::where('access_delegate_id', $user->id)->pluck('id')->toArray();
          $delegatedTeamIds = empty($delegatedAccountIds) ? [-1] : User::whereIn('reporting_to_id', $delegatedAccountIds)->notTerminated()->pluck('id')->toArray();
          $leaveQuery->whereIn('user_id', $delegatedTeamIds ?: [-1]);
          $odQuery->whereIn('user_id', $delegatedTeamIds ?: [-1]);
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
      if ($request->input('leaveTypeFilter') === 'outdoor') {
        // "Outdoor Duty" in the type filter: outdoor duty requests only.
        $leaveQuery->whereRaw('1 = 0');
      } elseif ($request->filled('leaveTypeFilter')) {
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
          // Short leave is detected from the type OR the request's own flag,
          // so rows whose type record is missing still classify correctly
          $isShortItem = $item->is_short_leave || ($item->leaveType->is_short_leave ?? false);

          if ($item->leaveType) {
              // Escaped: this column is sent as raw HTML (for the badges below).
              $name = e($item->leaveType->name);
          } else {
              $name = $isShortItem ? 'Short Leave' : 'N/A';
          }

          if ($isShortItem) {
              $name .= ' <span class="badge ms-1" style="font-size:0.65rem; font-weight:700; background:#0dcaf0; color:#000;">SHORT LEAVE</span>';
          } elseif ($item->is_half_day) {
              $sessionStr = $item->half_day_session ? str_replace('_', ' ', $item->half_day_session) : 'Half Day';
              $name .= ' <span class="badge ms-1" style="font-size:0.65rem; font-weight:700; background:#6610f2; color:#fff;">HALF DAY (' . strtoupper($sessionStr) . ')</span>';
          }

          if ($item->leaveType) {
              if ($item->leaveType->is_paid) {
                  $name .= ' <span class="badge bg-label-success ms-1" style="font-size:0.65rem; font-weight:600;">PAID</span>';
              } else {
                  $name .= ' <span class="badge bg-label-danger ms-1" style="font-size:0.65rem; font-weight:600;">UNPAID</span>';
              }
          }

          if ($item->from_date && $item->from_date->lt($item->created_at->startOfDay())) {
              $name .= ' <span class="badge bg-label-warning ms-1" style="font-size:0.65rem; font-weight:600;">BACK DATED</span>';
          }
          
          $dept = $item->user && $item->user->department ? $item->user->department->name : 'N/A';
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
              'user_id' => $item->user_id,
              'user_name' => $item->user->getFullName(),
              'user_code' => $item->user->code,
              'user_profile_image' => $item->user->profile_picture ? asset('storage/' . \Constants::BaseFolderEmployeeProfileWithSlash . $item->user->profile_picture) : null,
              'user_initial' => $item->user->getInitials(),
              'department' => $dept,
              'request_type' => $name,
              'dates' => $dateFmt,
              'duration' => $duration . ' Days',
              'days' => $duration,
              'reason' => $item->user_notes ?? 'N/A',
              'status' => $statusVal,
              'is_lwp' => (bool) $item->is_lwp,
              'lwp_days' => (float) $item->lwp_days,
              'approved_by_name' => $item->approvedBy ? $item->approvedBy->getFullName() : 'N/A',
              'approved_at_formatted' => $item->approved_at ? $item->approved_at->format('d M, Y h:i A') : null,
              'attachment' => $item->document ? asset('storage/' . \App\Constants\Constants::BaseFolderLeaveRequestDocument . $item->document) : null,
              'sort_date' => $item->created_at->timestamp
          ];
      });

      $ods = $odQuery->get()->map(function($item) {
          $dept = $item->user && $item->user->department ? $item->user->department->name : 'N/A';
          $reason = e($item->purpose);
          if ($item->location) $reason .= " <small class='text-muted d-block'>Loc: " . e($item->location) . "</small>";
          
          return [
              'unified_id' => 'outdoor_' . $item->id,
              'id' => $item->id,
              'item_type' => 'outdoor',
              'created_at' => $item->created_at,
              'user_id' => $item->user_id,
              'user_name' => $item->user->getFullName(),
              'user_code' => $item->user->code,
              'user_profile_image' => $item->user->profile_picture ? asset('storage/' . \Constants::BaseFolderEmployeeProfileWithSlash . $item->user->profile_picture) : null,
              'user_initial' => $item->user->getInitials(),
              'department' => $dept,
              'request_type' => 'Outdoor Duty',
              'dates' => $item->date->format('d M, Y'),
              'duration' => $item->expected_return_time ? "Return: " . $item->expected_return_time : '1 Day',
              'days' => 1,
              'reason' => $reason,
              'status' => $item->status,
              'is_lwp' => false,
              'lwp_days' => 0,
              'approved_by_name' => $item->approvedBy ? $item->approvedBy->getFullName() : 'N/A',
              'approved_at_formatted' => $item->approved_at ? $item->approved_at->format('d M, Y h:i A') : null,
              'attachment' => null,
              'sort_date' => $item->created_at->timestamp
          ];
      });

      $merged = $leaves->concat($ods)->sortByDesc('sort_date')->values();

      // request_type embeds real <span class="badge"> HTML built server-side
      // (type name + PAID/UNPAID/BACK DATED markers) -- without this, Yajra's
      // default column escaping turns it into literal text on the page.
      return \Yajra\DataTables\Facades\DataTables::of($merged)->rawColumns(['request_type'])->make(true);

    } catch (Exception $e) {
      Log::error('Unified getListAjax error: ' . $e->getMessage());
      return response()->json(['error' => $e->getMessage()], 500);
    }
  }

  /**
   * Employee-wise, per-month leave summary: how many leaves an employee
   * applied for and the outcome breakdown (paid/unpaid/half-day days from
   * leave_requests, plus an absent count sourced from attendances since
   * "absent" isn't a leave outcome at all).
   */
  public function summaryAjax(Request $request)
  {
    try {
      $user = auth()->user();
      $isManager = ($user->hasRole('manager') || $user->can('leave.approve.team')) && !$user->hasRole(['admin', 'hr']);

      $month = (int) ($request->input('summaryMonth') ?: now()->month);
      $year  = (int) ($request->input('summaryYear') ?: now()->year);

      $employeesQuery = User::query()
        ->select('id', 'first_name', 'last_name', 'code', 'department_id')
        ->with('department:id,name')
        ->where('status', 'active');

      if ($isManager) {
        $managedSubordinateIds = User::whereIn('reporting_to_id', $user->managerIdentityIds())->pluck('id')->toArray();
        $employeesQuery->whereIn('id', array_merge($managedSubordinateIds, [$user->id]));
      }
      if ($request->filled('summaryEmployeeFilter')) {
        $employeesQuery->where('id', $request->summaryEmployeeFilter);
      }
      if ($request->filled('summaryDepartmentFilter')) {
        $employeesQuery->where('department_id', $request->summaryDepartmentFilter);
      }

      $employees = $employeesQuery->orderBy('first_name')->get();
      $employeeIds = $employees->pluck('id');

      // All statuses here, not just approved -- the per-date drill-down needs
      // to show pending/rejected requests too, not only what's been decided.
      $leaveRequests = LeaveRequest::query()
        ->with(['leaveType' => fn($q) => $q->withTrashed(), 'approvedBy:id,first_name,last_name'])
        ->whereIn('user_id', $employeeIds)
        ->whereYear('from_date', $year)
        ->whereMonth('from_date', $month)
        ->orderBy('from_date')
        ->get([
          'id', 'user_id', 'leave_type_id', 'from_date', 'to_date', 'is_half_day',
          'half_day_session', 'is_short_leave', 'duration_hours', 'start_time', 'end_time',
          'document', 'user_notes', 'approved_by_id', 'approved_at', 'created_at',
          'is_lwp', 'lwp_days', 'status',
        ]);

      $leavesByUser = $leaveRequests->groupBy('user_id');

      $absentCounts = Attendance::query()
        ->whereIn('user_id', $employeeIds)
        ->whereYear('check_in_time', $year)
        ->whereMonth('check_in_time', $month)
        ->whereRaw('LOWER(status) = ?', ['absent'])
        ->selectRaw('user_id, COUNT(*) as cnt')
        ->groupBy('user_id')
        ->pluck('cnt', 'user_id');

      $rows = $employees->map(function ($employee) use ($leavesByUser, $absentCounts) {
        $requests = $leavesByUser->get($employee->id, collect());

        $applied = 0;
        $pending = 0;
        $paidDays = 0.0;
        $unpaidDays = 0.0;
        $halfDayCount = 0;
        $details = [];

        foreach ($requests as $req) {
          $statusVal = $req->status instanceof \App\Enums\LeaveRequestStatus ? $req->status->value : $req->status;
          $days = $req->is_half_day ? 0.5 : ($req->from_date->diffInDays($req->to_date) + 1);
          $dateLabel = $req->from_date->format('d M Y');
          if (!$req->from_date->equalTo($req->to_date)) {
            $dateLabel = $req->from_date->format('d M') . ' - ' . $req->to_date->format('d M Y');
          }

          // The "schedule" of the request: which part of the day it covers.
          $isShort = $req->is_short_leave || ($req->leaveType->is_short_leave ?? false);
          if ($isShort) {
            $schedule = 'Short Leave';
            if ($req->duration_hours) $schedule .= ' · ' . rtrim(rtrim(number_format((float) $req->duration_hours, 1), '0'), '.') . 'h';
            if ($req->start_time && $req->end_time) {
              $schedule .= ' (' . substr($req->start_time, 0, 5) . ' - ' . substr($req->end_time, 0, 5) . ')';
            }
          } elseif ($req->is_half_day) {
            $schedule = 'Half Day' . ($req->half_day_session ? ' · ' . ucwords(str_replace('_', ' ', $req->half_day_session)) : '');
          } else {
            $schedule = 'Full Day' . ($days > 1 ? ' × ' . $days : '');
          }

          if ($statusVal === 'approved') {
            $applied++;
            $lwp = (float) ($req->lwp_days ?? 0);
            $unpaidDays += $lwp;
            $paidDays += max(0, $days - $lwp);
            if ($req->is_half_day) {
              $halfDayCount++;
            }
            $outcome = $lwp > 0 ? ($lwp >= $days ? 'Unpaid' : 'Part Paid / Part Unpaid') : 'Paid';
          } elseif ($statusVal === 'pending') {
            $pending++;
            $outcome = 'Pending Approval';
          } else {
            $outcome = ucfirst($statusVal);
          }

          $details[] = [
            'date'       => $dateLabel,
            'type'       => $req->leaveType->name ?? ($isShort ? 'Short Leave' : 'N/A'),
            'days'       => $days,
            'schedule'   => $schedule,
            'reason'     => $req->user_notes ?: null,
            'document'   => $req->document ? asset('storage/' . \App\Constants\Constants::BaseFolderLeaveRequestDocument . $req->document) : null,
            'applied_on' => $req->created_at?->format('d M Y'),
            'approved_by'=> $req->approvedBy?->getFullName(),
            'approved_at'=> $req->approved_at?->format('d M Y'),
            'status'     => $statusVal,
            'outcome'    => $outcome,
          ];
        }

        return [
          'user_id'      => $employee->id,
          'user_name'    => $employee->getFullName(),
          'user_code'    => $employee->code,
          'department'   => $employee->department?->name ?? 'N/A',
          'applied'      => $applied,
          'pending'      => $pending,
          'paid_days'    => $paidDays,
          'unpaid_days'  => $unpaidDays,
          'total_days'   => $paidDays + $unpaidDays,
          'half_day'     => $halfDayCount,
          'absent'       => (int) ($absentCounts[$employee->id] ?? 0),
          'details'      => $details,
        ];
      })->values();

      return DataTables::of($rows)->make(true);

    } catch (Exception $e) {
      Log::error('Leave summaryAjax error: ' . $e->getMessage());
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

      if ($leaveRequest->user_id === auth()->id() && in_array($validated['status'], ['approved', 'rejected'])) {
          return response()->json(['status' => 'error', 'message' => 'You cannot approve or reject your own leave request.']);
      }

      if (!$this->canApproveFor(auth()->user(), $leaveRequest->user_id)) {
          return response()->json(['status' => 'error', 'message' => 'This leave request is outside what you can approve.']);
      }

      $leaveRequest->status = $validated['status'];
      $approverId = $this->approverIdFor(auth()->user(), $leaveRequest->user_id);

      if ($validated['status'] == LeaveRequestStatus::CANCELLED) {
        $leaveRequest->cancel_reason = $validated['adminNotes'] ?? null;
        $leaveRequest->cancelled_at = now();
      } elseif ($validated['status'] == LeaveRequestStatus::APPROVED->value || $validated['status'] == 'approved') {
        $leaveRequest->approval_notes = $validated['adminNotes'] ?? null;
        $leaveRequest->approved_by_id = $approverId;
        $leaveRequest->approved_at = now();
      } else {
        $leaveRequest->approval_notes = $validated['adminNotes'] ?? null;
        $leaveRequest->rejected_by_id = $approverId;
        $leaveRequest->rejected_at = now();
      }

      $leaveRequest->save();

      app()->terminating(function () use ($leaveRequest, $validated) {
          Notification::send($leaveRequest->user, new LeaveRequestApproval($leaveRequest, $validated['status']));
      });

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
      $actor = auth()->user();
      foreach ($validated['ids'] as $uid) {
          if (str_starts_with($uid, 'leave_')) $leaveIds[] = str_replace('leave_', '', $uid);
          elseif (str_starts_with($uid, 'outdoor_')) $outdoorIds[] = str_replace('outdoor_', '', $uid);
          else $leaveIds[] = $uid; // fallback for old UI
      }

      // Process Leaves
      if (count($leaveIds) > 0) {
          $leaveRequests = LeaveRequest::whereIn('id', $leaveIds)->get();
          foreach ($leaveRequests as $leaveRequest) {
            if ($leaveRequest->user_id === auth()->id() && in_array($validated['status'], ['approved', 'rejected'])) {
                continue; // Skip their own requests
            }
            if (!$this->canApproveFor($actor, $leaveRequest->user_id)) {
                continue; // Skip requests outside what this actor can approve
            }
            $newStatus = LeaveRequestStatus::tryFrom($validated['status']) ?? $validated['status'];
            $leaveRequest->status = $newStatus;
            $approverId = $this->approverIdFor($actor, $leaveRequest->user_id);
            if ($validated['status'] == 'approved') {
                $leaveRequest->approved_by_id = $approverId;
                $leaveRequest->approved_at = now();
            } elseif ($validated['status'] == 'rejected') {
                $leaveRequest->rejected_by_id = $approverId;
                $leaveRequest->rejected_at = now();
            }
            $leaveRequest->save();
            app()->terminating(function () use ($leaveRequest, $validated) {
                Notification::send($leaveRequest->user, new LeaveRequestApproval($leaveRequest, $validated['status']));
            });
          }
      }

      // Process Outdoors
      if (count($outdoorIds) > 0) {
          $outdoorDuties = OutdoorDuty::whereIn('id', $outdoorIds)->get();
          foreach ($outdoorDuties as $duty) {
              if ($duty->user_id === auth()->id() && in_array($validated['status'], ['approved', 'rejected'])) {
                  continue; // Cannot approve/reject own outdoor duty
              }
              if (!$this->canApproveFor($actor, $duty->user_id)) {
                  continue; // Skip requests outside what this actor can approve
              }
              $duty->status = $validated['status'];
              $approverId = $this->approverIdFor($actor, $duty->user_id);
              if ($validated['status'] === 'approved') {
                  $duty->approved_by_id = $approverId;
                  $duty->approved_at = now();
              } elseif ($validated['status'] === 'rejected') {
                  $duty->rejected_by_id = $approverId;
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

    // This route requires admin/hr/accounts/manager/leave.approve, so a plain
    // employee cannot reach it — but among those roles it had no ownership
    // check of its own: any manager could read any OTHER manager's team's
    // leave request, and accounts could read a still-pending one, by id alone.
    // Mirror getListAjax's scope exactly rather than inventing a new one, and
    // 404 (not 403) so an id outside someone's remit doesn't confirm it exists.
    $user       = auth()->user();
    $isManager  = ($user->hasRole('manager') || $user->can('leave.approve.team')) && !$user->hasRole(['admin', 'hr']);
    $isAccounts = $user->hasRole('accounts') && !$user->hasRole(['admin', 'hr']);

    if ($isManager) {
      $scopedUserIds = User::whereIn('reporting_to_id', $user->managerIdentityIds())
        ->pluck('id')->push($user->id);
      abort_unless($scopedUserIds->contains($leaveRequest->user_id), 404);
    } elseif ($isAccounts) {
      abort_unless($leaveRequest->status === LeaveRequestStatus::APPROVED, 404);
    }

    $response = [
      'id' => $leaveRequest->id,
      'userName' => $leaveRequest->user->getFullName(),
      'userCode' => $leaveRequest->user->code,
      'leaveType' => e($leaveRequest->leaveType->name) . ($leaveRequest->from_date && $leaveRequest->from_date->lt($leaveRequest->created_at->startOfDay()) ? ' <span class="badge bg-label-warning ms-1" style="font-size:0.6rem;">BACK DATED</span>' : ''),
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
      $user = auth()->user();

      // This method serves two different pages that share nothing but the
      // endpoint: the admin/manager review list at leaveRequests.outdoorDuty.*
      // and the personal "My Outdoor Duty Requests" card on the self-service
      // My Leaves page at user.outdoor_duty.*. Role alone cannot tell them
      // apart, so an admin or HR account viewing their OWN personal page was
      // seeing every employee's requests under a "My" heading. The route name
      // is fixed by which URL was actually hit, so it can't be spoofed by the
      // client the way a request parameter could.
      $selfOnly  = $request->route()?->getName() === 'user.outdoor_duty.listAjax';
      $isAdmin   = !$selfOnly && $user->hasRole(['admin', 'hr']);
      $isManager = !$selfOnly && $user->hasRole('manager') && !$isAdmin;

      $query = OutdoorDuty::with(['user', 'approvedBy'])->select('outdoor_duties.*');

      if ($selfOnly) {
        $query->where('user_id', $user->id);
      } elseif ($isManager) {
        $subIds = User::whereIn('reporting_to_id', $user->managerIdentityIds())->notTerminated()->pluck('id')->toArray();
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
      // Optional: a multi-day request ("from date to date"). Omitted or equal
      // to od_date means the original single-day request. One OutdoorDuty row
      // per day is created, same as a single day - there is no from_date/
      // to_date span column on this table, unlike leave_requests.
      'od_to_date'           => 'nullable|date|after_or_equal:od_date',
      'od_purpose'           => 'required|string|max:500',
      'od_location'          => 'nullable|string|max:255',
      'od_expected_return'   => 'nullable|string|max:15',
    ]);

    try {
      $user   = auth()->user();
      $userId = ($user->hasRole(['admin', 'hr', 'manager']) && !empty($validated['od_user_id']))
        ? $validated['od_user_id'] : $user->id;

      $fromDate = $validated['od_date'];
      $toDate   = $validated['od_to_date'] ?? $validated['od_date'];

      // A span this wide is almost certainly a typo'd year, not a genuine
      // multi-week outdoor duty - same guard as a leave unlock window.
      $spanDays = \Carbon\Carbon::parse($fromDate)->diffInDays(\Carbon\Carbon::parse($toDate)) + 1;
      if ($spanDays > 31) {
        return response()->json(['status' => 'error', 'message' => 'An outdoor duty request cannot span more than 31 days.'], 422);
      }

      // Same one-week backdate rule as regular leave (LeaveBackdate) - the date
      // picker enforces it, but this endpoint took any date at all with no
      // server-side check, so a direct POST (or a stale page) could backdate
      // an outdoor duty indefinitely. Checked across the whole span.
      if ($backdateError = \App\Support\LeaveBackdate::error($fromDate, $toDate, $userId)) {
        return response()->json(['status' => 'error', 'message' => $backdateError], 422);
      }

      // All-or-nothing: if any day in the span already has a request, create
      // none of them rather than leaving a partial, confusing span behind.
      $conflicting = OutdoorDuty::where('user_id', $userId)
        ->whereBetween('date', [$fromDate, $toDate])
        ->whereIn('status', ['pending', 'approved'])
        ->orderBy('date')
        ->pluck('date');

      if ($conflicting->isNotEmpty()) {
        // 422, not 200 -- see outdoorDutyActionAjax's own note below: the page's
        // success callback never checked the status field, so a 200 here made a
        // rejected double-submit show "submitted successfully" and silently
        // drop the actual conflict message.
        $dates = $conflicting->map(fn ($d) => $d->format('d M Y'))->implode(', ');
        $message = $conflicting->count() === 1
          ? "An outdoor duty request already exists for {$dates}."
          : "An outdoor duty request already exists for: {$dates}.";
        return response()->json(['status' => 'error', 'message' => $message], 422);
      }

      $duties = [];
      for ($d = \Carbon\Carbon::parse($fromDate); $d->lte(\Carbon\Carbon::parse($toDate)); $d->addDay()) {
        $duties[] = OutdoorDuty::create([
          'user_id'              => $userId,
          'date'                 => $d->toDateString(),
          'purpose'              => $validated['od_purpose'],
          'location'             => $validated['od_location'] ?? null,
          'expected_return_time' => $validated['od_expected_return'] ?? null,
          'status'               => 'pending',
          'created_by_id'        => $user->id,
          'updated_by_id'        => $user->id,
        ]);
      }

      $duty = $duties[0];
      $duty->load('user');

      // Alert HR and the manager (bell + push), the same people a leave request
      // reaches. Nothing was sent before, so a request sat unseen until someone
      // opened Leave Approvals. A failed alert must not fail the request.
      // One alert for the whole span, not one per day.
      try {
        \App\Helpers\NotificationHelper::notifyAdminHR(new \App\Notifications\Leave\NewOutdoorDuty($duty));
      } catch (Exception $e) {
        Log::error('OutdoorDuty notify error: ' . $e->getMessage());
      }

      $message = count($duties) === 1
        ? 'Outdoor duty request submitted successfully.'
        : 'Outdoor duty request submitted for ' . count($duties) . ' days (' . \Carbon\Carbon::parse($fromDate)->format('d M') . ' - ' . \Carbon\Carbon::parse($toDate)->format('d M Y') . ').';
      return response()->json(['status' => 'success', 'message' => $message]);
    } catch (Exception $e) {
      Log::error('OutdoorDuty store error: ' . $e->getMessage());
      return response()->json(['status' => 'error', 'message' => 'Something went wrong. Please try again.'], 500);
    }
  }

  public function outdoorDutyActionAjax(Request $request)
  {
    $user = auth()->user();
    // Refusals are 403, not 200: the pages treat any 200 as done, so a refused
    // approval used to show "Success" and drop off the dashboard card.
    if (!$user->hasRole(['admin', 'hr', 'manager'])) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
    }

    $validated = $request->validate([
      'id'         => 'required|exists:outdoor_duties,id',
      'status'     => 'required|in:approved,rejected,cancelled',
      'adminNotes' => 'nullable|string|max:500',
    ]);

    try {
      $duty = OutdoorDuty::findOrFail($validated['id']);
      
      if ($duty->user_id === $user->id && in_array($validated['status'], ['approved', 'rejected'])) {
          return response()->json(['status' => 'error', 'message' => 'You cannot approve or reject your own outdoor duty request.'], 403);
      }
      // The same team limit leave approval has: a manager acts on their reports only.
      if (in_array($validated['status'], ['approved', 'rejected']) && !$this->canApproveFor($user, (int) $duty->user_id)) {
          return response()->json(['status' => 'error', 'message' => 'You can only approve outdoor duty for your own team.'], 403);
      }

      $duty->status        = $validated['status'];
      $duty->updated_by_id = $user->id;
      $approverId          = $this->approverIdFor($user, (int) $duty->user_id);

      if ($validated['status'] === 'approved') {
        $duty->approved_by_id = $approverId;
        $duty->approved_at    = now();
        $duty->approval_notes = $validated['adminNotes'] ?? null;
        $this->markOutdoorDutyAttendance($duty);
      } elseif ($validated['status'] === 'rejected') {
        $duty->rejected_by_id = $approverId;
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

  public function outdoorDutyGetByIdAjax($id, Request $request)
  {
    $duty = OutdoorDuty::with('user', 'approvedBy')->findOrFail($id);

    // Same scoping as outdoorDutyListAjax, applied to a single record: the
    // "eye" button on the list only ever offers rows already visible there,
    // but this endpoint had no check of its own — any signed-in user could
    // read any employee's date, purpose and location by requesting an id
    // directly. 404 rather than 403, so probing ids can't confirm one exists.
    $user      = auth()->user();
    $selfOnly  = $request->route()?->getName() === 'user.outdoor_duty.getByIdAjax';
    $isAdmin   = !$selfOnly && $user->hasRole(['admin', 'hr']);
    $isManager = !$selfOnly && $user->hasRole('manager') && !$isAdmin;

    if ($selfOnly || (!$isAdmin && !$isManager)) {
      abort_unless($duty->user_id === $user->id, 404);
    } elseif ($isManager) {
      $allowedIds = User::whereIn('reporting_to_id', $user->managerIdentityIds())->pluck('id')->push($user->id);
      abort_unless($allowedIds->contains($duty->user_id), 404);
    }

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
      $date = $duty->date;
      // The person's row for that day, whatever time it starts at. This used to
      // look only for a row starting at exactly 09:00, so a day that already had
      // a biometric punch (10:01) or a CCare mark (09:30) got a second row
      // beside it - three duplicate days by 30 Sep 2026.
      $existing = Attendance::where('user_id', $duty->user_id)
        ->whereDate('check_in_time', $date->toDateString())
        ->orderByDesc('is_outdoor_duty')
        ->first();

      if ($existing && !$existing->is_outdoor_duty) {
        $status   = strtolower((string) $existing->status);
        $onLeave  = $existing->leave_request_id || in_array($status, ['leave', 'paid_leave', 'unpaid_leave', 'on_leave', 'half-day'], true);
        $realIn   = $existing->check_in_time && $existing->check_in_time->format('H:i') !== '00:00';
        $link     = ['is_outdoor_duty' => true, 'outdoor_duty_id' => $duty->id];

        if (!$onLeave && ($status === 'absent' || !$realIn || !$existing->check_out_time)) {
          // Marked absent, or a missed punch: the approved duty explains the
          // day. A real check-in is kept; only what is missing is filled.
          $existing->update($link + [
            'check_in_time'  => ($realIn && $status !== 'absent') ? $existing->check_in_time : $date->copy()->setTime(9, 0, 0),
            'check_out_time' => $date->copy()->setTime(18, 0, 0),
            'status'         => Attendance::STATUS_PRESENT,
            'approved_by_id' => $duty->approved_by_id,
            'approved_at'    => now(),
            'admin_reason'   => 'Outdoor Duty',
          ]);
        } else {
          // A real working day or a leave: keep it as it is, just tie the duty to it.
          $existing->updateQuietly($link);
        }

        return;
      }

      $attendance = $existing ?: new Attendance();

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
