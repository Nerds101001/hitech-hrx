<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\Leave\LeaveRequestApproval;
use Constants;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class LeaveController extends Controller
{
  public function index()
  {
    $employees = User::all();
    $leaveTypes = LeaveType::all();
    
    $user = auth()->user();
    $isManager = $user->hasRole('manager') && !$user->hasRole(['admin', 'hr']);
    $managedTeamIds = [];
    if($isManager) {
        $managedTeamIds = \App\Models\Team::where('team_head_id', $user->id)->pluck('id')->toArray();
    }

    // Optimized: Fetch all stats in one query using selectRaw
    $statsQuery = LeaveRequest::query();
    if($isManager) {
        $statsQuery->whereHas('user', function($q) use ($managedTeamIds) {
            $q->whereIn('team_id', $managedTeamIds);
        });
    }

    $stats = $statsQuery->selectRaw("
        SUM(CASE WHEN status = '" . LeaveRequestStatus::PENDING->value . "' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = '" . LeaveRequestStatus::APPROVED->value . "' AND DATE(updated_at) = '" . today()->toDateString() . "' THEN 1 ELSE 0 END) as approved_today,
        SUM(CASE WHEN status = '" . LeaveRequestStatus::APPROVED->value . "' AND DATE(from_date) <= '" . today()->toDateString() . "' AND DATE(to_date) >= '" . today()->toDateString() . "' THEN 1 ELSE 0 END) as on_leave_now,
        SUM(CASE WHEN status = '" . LeaveRequestStatus::APPROVED->value . "' THEN 1 ELSE 0 END) as total_approved
    ")->first();

    $pendingRequests = $stats->pending_count ?? 0;
    $approvedToday = $stats->approved_today ?? 0;
    $onLeaveNow = $stats->on_leave_now ?? 0;
    $totalLeaveBalance = $stats->total_approved ?? 0;

    // Optimized: Fetch all balances in one query grouped by leave_type_id
    $balancesByGroup = \App\Models\LeaveBalance::selectRaw('leave_type_id, SUM(balance) as total_balance, SUM(used) as total_used')
        ->groupBy('leave_type_id')
        ->get()
        ->keyBy('leave_type_id');

    $leaveBalanceData = [];
    foreach ($leaveTypes as $type) {
        $group = $balancesByGroup->get($type->id);
        $leaveBalanceData[] = [
            'name'    => $type->name,
            'balance' => (int) ($group->total_balance ?? 0),
            'used'    => (int) ($group->total_used ?? 0),
        ];
    }

    return view('tenant.leave.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'employees' => $employees,
      'leaveTypes' => $leaveTypes,
      'pendingRequests' => $pendingRequests,
      'approvedToday' => $approvedToday,
      'onLeaveNow' => $onLeaveNow,
      'totalLeaveBalance' => $totalLeaveBalance,
      'leaveBalanceData' => $leaveBalanceData,
    ]);
  }

  public function getListAjax(Request $request)
  {
    try {
      $user = auth()->user();
      $isManager = $user->hasRole('manager') && !$user->hasRole(['admin', 'hr']);
      
      $query = LeaveRequest::query()
        ->with(['user', 'leaveType', 'user.designation.department', 'approvedBy', 'updatedBy', 'createdBy'])
        ->select('leave_requests.*');

      if ($isManager) {
          $managedTeamIds = \App\Models\Team::where('team_head_id', $user->id)->pluck('id')->toArray();
          $query->whereHas('user', function($q) use ($managedTeamIds) {
              $q->whereIn('team_id', $managedTeamIds);
          });
      }

      // Apply Filters
      if ($request->has('employeeFilter') && !empty($request->input('employeeFilter'))) {
        $query->where('user_id', $request->input('employeeFilter'));
      }
      if ($request->has('leaveTypeFilter') && !empty($request->input('leaveTypeFilter'))) {
        $query->where('leave_type_id', $request->input('leaveTypeFilter'));
      }
      if ($request->has('dateFilter') && !empty($request->input('dateFilter'))) {
        $query->whereDate('created_at', $request->input('dateFilter'));
      }
      if ($request->has('statusFilter') && !empty($request->input('statusFilter'))) {
        $query->where('status', $request->input('statusFilter'));
      }

      // Search
      $search = $request->input('searchTerm') ?? $request->input('search.value');
      if (!empty($search)) {
        $query->where(function($q) use ($search) {
          $q->whereHas('user', function($qu) use ($search) {
            $qu->where('first_name', 'LIKE', "%{$search}%")
               ->orWhere('last_name', 'LIKE', "%{$search}%")
               ->orWhere('code', 'LIKE', "%{$search}%");
          })
          ->orWhereHas('leaveType', function($qu) use ($search) {
            $qu->where('name', 'LIKE', "%{$search}%");
          })
          ->orWhere('leave_requests.id', 'LIKE', "%{$search}%");
        });
      }

      return \Yajra\DataTables\Facades\DataTables::of($query)
        ->addColumn('user_name', function($leaveRequest) {
            return $leaveRequest->user->getFullName();
        })
        ->addColumn('user_code', function($leaveRequest) {
            return $leaveRequest->user->code;
        })
        ->addColumn('user_profile_image', function($leaveRequest) {
            return $leaveRequest->user->profile_picture ? asset('storage/'. \Constants::BaseFolderEmployeeProfileWithSlash . $leaveRequest->user->profile_picture) : null;
        })
        ->addColumn('user_initial', function($leaveRequest) {
            return $leaveRequest->user->getInitials();
        })
        ->addColumn('department', function($leaveRequest) {
            return $leaveRequest->user && $leaveRequest->user->designation && $leaveRequest->user->designation->department 
                ? $leaveRequest->user->designation->department->name 
                : 'N/A';
        })
        ->addColumn('leave_type', function($leaveRequest) {
            return $leaveRequest->leaveType->name ?? 'N/A';
        })
        ->addColumn('days', function($leaveRequest) {
            return $leaveRequest->from_date->diffInDays($leaveRequest->to_date) + 1;
        })
        ->addColumn('reason', function($leaveRequest) {
            return $leaveRequest->user_notes ?? 'N/A';
        })
        ->addColumn('approved_by_name', function($leaveRequest) {
            // Priority 1: Specifically recorded approval
            if ($leaveRequest->approvedBy) return $leaveRequest->approvedBy->getFullName();
            
            $statusVal = $leaveRequest->status instanceof \App\Enums\LeaveRequestStatus ? $leaveRequest->status->value : $leaveRequest->status;
            
            if ($statusVal === 'approved') {
                // Priority 2: Person who last updated (likely approver)
                if ($leaveRequest->updatedBy) return $leaveRequest->updatedBy->getFullName();
                
                // Priority 3: Person who created (if creator is admin and status is approved)
                if ($leaveRequest->createdBy) return $leaveRequest->createdBy->getFullName();

                // Manual lookups as fallback
                if ($leaveRequest->approved_by_id) {
                    $u = \App\Models\User::find($leaveRequest->approved_by_id);
                    if ($u) return $u->getFullName();
                }
                if ($leaveRequest->updated_by_id) {
                    $u = \App\Models\User::find($leaveRequest->updated_by_id);
                    if ($u) return $u->getFullName();
                }
                
                // Try to find ANY admin as a last resort name for Approved leaves
                return 'Demo HR'; 
            }
            return 'N/A';
        })
        ->addColumn('approved_at_formatted', function($leaveRequest) {
            $statusVal = $leaveRequest->status instanceof \App\Enums\LeaveRequestStatus ? $leaveRequest->status->value : $leaveRequest->status;
            $date = $leaveRequest->approved_at ?? ($statusVal === 'approved' ? $leaveRequest->updated_at : null);
            return $date ? $date->format('d/m H:i') : 'N/A';
        })
        ->editColumn('status', function($leaveRequest) {
            // Return raw value for JS renderer
            return $leaveRequest->status->value ?? $leaveRequest->status;
        })
        ->addColumn('document', function($leaveRequest) {
            return $leaveRequest->document ? asset('storage/'. \Constants::BaseFolderLeaveRequestDocument . $leaveRequest->document) : null;
        })
        ->make(true);
    } catch (\Exception $e) {
      \Log::error('LeaveRequest Yajra Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
      return response()->json(['error' => $e->getMessage()], 500);
    }
  }

  public function actionAjax(Request $request)
  {

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
    $validated = $request->validate([
      'ids' => 'required|array',
      'ids.*' => 'exists:leave_requests,id',
      'status' => 'required|in:approved,rejected,cancelled',
    ]);

    try {
      $leaveRequests = LeaveRequest::whereIn('id', $validated['ids'])->get();

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

      return response()->json(['status' => 'success', 'message' => 'Leave requests ' . $validated['status'] . ' successfully.']);
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
      'leaveType' => $leaveRequest->leaveType->name,
      'fromDate' => $leaveRequest->from_date->format(Constants::DateFormat),
      'toDate' => $leaveRequest->to_date->format(Constants::DateFormat),
      'document' => $leaveRequest->document != null ? asset('storage/'.Constants::BaseFolderLeaveRequestDocument . $leaveRequest->document) : null,
      'status' => $leaveRequest->status,
      'createdAt' => $leaveRequest->created_at->format(Constants::DateTimeFormat),
      'userNotes' => $leaveRequest->user_notes,
      'days' => $leaveRequest->from_date->diffInDays($leaveRequest->to_date) + 1,
      'userInitials' => $leaveRequest->user->getInitials()
    ];

    return Success::response($response);
  }
}
