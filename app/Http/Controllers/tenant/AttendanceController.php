<?php

namespace App\Http\Controllers\tenant;

use App\Enums\UserAccountStatus;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Models\Shift;
use App\Models\Holiday;
use App\Models\Team;
use App\Constants\Constants as AppConstants;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class AttendanceController extends Controller
{
  public function index()
  {
    $currentUser = auth()->user();
    $scopedIds = $this->getScopedUserIds($currentUser);

    $activeUsersCountQuery = User::where('status', UserAccountStatus::ACTIVE);
    if ($scopedIds) $activeUsersCountQuery->whereIn('id', $scopedIds);
    $activeUsersCount = $activeUsersCountQuery->count();
    
    $todayPresentCountQuery = Attendance::whereDate('check_in_time', today())->where('status', 'present');
    if ($scopedIds) $todayPresentCountQuery->whereIn('user_id', $scopedIds);
    $todayPresentCount = $todayPresentCountQuery->count();
      
    $todayAbsentCountQuery = Attendance::whereDate('check_in_time', today())->where('status', 'absent');
    if ($scopedIds) $todayAbsentCountQuery->whereIn('user_id', $scopedIds);
    $todayAbsentCount = $todayAbsentCountQuery->count();
      
    $lateCountQuery = Attendance::whereDate('check_in_time', today())->where('status', 'late');
    if ($scopedIds) $lateCountQuery->whereIn('user_id', $scopedIds);
    $lateCount = $lateCountQuery->count();

    $onLeaveCountQuery = \App\Models\LeaveRequest::where('status', \App\Enums\LeaveRequestStatus::APPROVED)
        ->whereDate('from_date', '<=', today())
        ->whereDate('to_date', '>=', today());
    if ($scopedIds) $onLeaveCountQuery->whereIn('user_id', $scopedIds);
    $onLeaveCount = $onLeaveCountQuery->count();

    $usersQuery = User::where('status', UserAccountStatus::ACTIVE);
    if ($scopedIds) $usersQuery->whereIn('id', $scopedIds);
    $users = $usersQuery->get();

    $shifts = Shift::get();
    $teams = Team::get();

    return view('tenant.attendance.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'users' => $users,
      'shifts' => $shifts,
      'teams' => $teams,
      'todayPresentCount' => $todayPresentCount,
      'todayAbsentCount' => $todayAbsentCount,
      'onLeaveCount' => $onLeaveCount,
      'lateCount' => $lateCount,
      'activeUsersCount' => $activeUsersCount
    ]);
  }

  /**
   * Get IDs of users the current user is authorized to see/manage.
   * Admin/HR: All users.
   * Manager (Restricted): Direct reports + Self.
   */
  private function getScopedUserIds($user)
  {
      if ($user->hasRole(['admin', 'hr'])) {
          return null; // All
      }
      
      if ($user->hasRole('manager')) {
          $ids = User::where('reporting_to_id', $user->id)->pluck('id')->toArray();
          $ids[] = $user->id;
          return $ids;
      }
      
      return [$user->id]; // Default to self
  }

  public function indexAjax(Request $request)
  {
    $currentUser = auth()->user();
    $scopedIds = $this->getScopedUserIds($currentUser);

    $query = Attendance::query()
      ->with(['attendanceLogs', 'user', 'shift', 'updatedBy']);

    if ($scopedIds) {
        $query->whereIn('user_id', $scopedIds);
    }

    // User filter
    if ($request->has('userId') && $request->input('userId')) {
      $query->where('user_id', $request->input('userId'));
    }

    // Shift filter
    if ($request->has('shiftId') && $request->input('shiftId')) {
        $query->where('shift_id', $request->input('shiftId'));
    }

    // Team Filter
    if ($request->has('teamId') && $request->input('teamId')) {
        $query->whereHas('user', function($q) use ($request) {
            $q->where('team_id', $request->input('teamId'));
        });
    }

    // Date range filter
    if ($request->has('startDate') && $request->input('startDate') && $request->has('endDate') && $request->input('endDate')) {
        $startDate = Carbon::parse($request->input('startDate'))->startOfDay();
        $endDate = Carbon::parse($request->input('endDate'))->endOfDay();
        $query->whereBetween('check_in_time', [$startDate, $endDate]);
    } 
    // Single date filter (fallback)
    elseif ($request->has('date') && $request->input('date')) {
      try {
          $date = Carbon::parse($request->input('date'))->toDateString();
          $query->whereDate('check_in_time', $date);
      } catch (\Exception $e) {
          $query->whereDate('check_in_time', Carbon::today());
      }
    } else {
      $query->whereDate('check_in_time', Carbon::today());
    }

    // Search term filter
    if ($request->has('searchTerm') && $request->input('searchTerm')) {
        $searchTerm = $request->input('searchTerm');
        $query->whereHas('user', function($q) use ($searchTerm) {
            $q->where('first_name', 'like', "%{$searchTerm}%")
              ->orWhere('last_name', 'like', "%{$searchTerm}%")
              ->orWhere('code', 'like', "%{$searchTerm}%");
        });
    }

    return DataTables::of($query)
      ->addIndexColumn()
      ->addColumn('date', function ($attendance) {
        return $attendance->check_in_time ? $attendance->check_in_time->format('d/m/Y') : 'N/A';
      })
      ->editColumn('check_in_time', function ($attendance) {
        return $attendance->check_in_time ? $attendance->check_in_time->format('H:i') : '--:--';
      })
      ->editColumn('check_out_time', function ($attendance) {
        return $attendance->check_out_time ? $attendance->check_out_time->format('H:i') : '--:--';
      })
      ->addColumn('shift', function ($attendance) {
        return $attendance->shift ? '<span class="badge bg-label-teal px-3 py-1 rounded-pill fw-bold" style="font-size:0.65rem;">'.$attendance->shift->name.'</span>' : '<span class="text-muted">N/A</span>';
      })
      ->addColumn('working_hours', function ($attendance) {
          if ($attendance->check_in_time && $attendance->check_out_time) {
              $diff = $attendance->check_in_time->diff($attendance->check_out_time);
              return '<span class="fw-black text-teal">'.sprintf('%d:%02dh', $diff->h, $diff->i).'</span>';
          }
          return '<span class="fw-bold text-muted">0:00h</span>';
      })
      ->addColumn('status', function ($attendance) {
          $status = $attendance->status ?: 'Present';
          
          // Dynamic enforcement of 8-hour (Full Day) rule
           if (empty($attendance->admin_reason) && $attendance->check_in_time && $attendance->check_out_time) {
               $mins = $attendance->check_in_time->diffInMinutes($attendance->check_out_time);
               if ($mins >= 480) {
                   $status = 'Present';
               } elseif ($mins < 465 && (strtolower($status) === 'present')) {
                   $status = 'Half-Day';
               }
           }

          $color = 'bg-teal';
          $icon = 'bx-check-circle';
          $s = strtolower($status);
          
          if($s == 'absent') { $color = 'bg-red'; $icon = 'bx-x-circle'; $status = 'Absent'; }
          elseif($s == 'half-day') { $color = 'bg-orange'; $icon = 'bx-time'; $status = 'Half-Day'; }
          elseif($s == 'late') { $color = 'bg-warning'; $icon = 'bx-time'; $status = 'Late'; }
          elseif($s == 'paid_leave' || ($s == 'leave' && $attendance->leaveRequest?->leaveType?->is_paid)) { $color = 'bg-teal'; $icon = 'bx-calendar-check'; $status = 'Paid Leave'; }
          elseif($s == 'unpaid_leave' || $s == 'on_leave' || $s == 'leave') { $color = 'bg-purple-vibrant'; $icon = 'bx-calendar-x'; $status = 'Unpaid Leave'; }
          elseif($s == 'work_from_home' || $s == 'wfh') { $color = 'bg-indigo-vibrant'; $icon = 'bx-home'; $status = 'WFH'; }
          else { $status = 'Present'; } // Default normalization
          
          $editBadge = '';
          if ($attendance->admin_reason) {
              $editBadge = '<i class="bx bxs-edit-alt ms-1 text-white opacity-75" title="Manual Adjustment"></i>';
          }

          $shortLeaveIcon = '';
          if ($attendance->leave_request_id) {
              $shortLeaveIcon = ' <i class="bx bxs-time-five ms-1 text-white opacity-75" title="Short Leave Applied"></i>';
          }

          return '<span class="badge '.$color.' border-0 px-3 py-2 rounded-2 fw-black text-uppercase ls-1 text-white shadow-sm" style="font-size:0.65rem; min-width:80px;"><i class="bx '.$icon.' me-1"></i>'.$status.$editBadge.$shortLeaveIcon.'</span>';
      })
      ->addColumn('actions', function ($attendance) {
          $editorData = '';
          if ($attendance->admin_reason) {
              $editorName = $attendance->updatedBy?->getFullName() ?? 'Admin';
              $reason = addslashes($attendance->admin_reason);
              $editorData = "data-editor='{$editorName}' data-reason='{$reason}'";
          }

          return '<div class="d-flex align-items-center gap-2">'.
                 '<button class="btn btn-sm btn-icon hitech-action-icon" onclick="viewLogs('.$attendance->id.')" title="View Logs"><i class="bx bx-list-ul"></i></button>'.
                 '<button class="btn btn-sm btn-icon hitech-action-icon" onclick="editRecord('.$attendance->id.')" title="Edit" '.$editorData.'><i class="bx bx-edit"></i></button>'.
                 '</div>';
      })
      ->addColumn('user', function ($attendance) {
        $name = $attendance->user ? addslashes($attendance->user->getFullName()) : 'Unknown';
        $code = $attendance->user ? $attendance->user->code : '--';
        $initials = $attendance->user?->getInitials() ?? 'NA';
        
        // Pass "Today" metrics or empty for now, Popup logic will handle it
        $clickAction = 'onclick="showEmployeeSummary(\''.$name.'\', \''.$code.'\', 0, 0, 0, 0, \'Current Filter\')"';

        $profileUrl = $attendance->user?->getProfilePicture();
        if ($profileUrl) {
          $profileOutput = '<img src="' . $profileUrl . '"  alt="Avatar" class="avatar rounded-circle " />';
        } else {
          $profileOutput = '<span class="avatar-initial rounded-circle bg-label-teal fw-black">' . $initials . '</span>';
        }

        return '<div class="d-flex justify-content-start align-items-center user-name cursor-pointer" '.$clickAction.'>' .
          '<div class="avatar-wrapper">' .
          '<div class="avatar avatar-md me-3 shadow-sm">' .
          $profileOutput .
          '</div>' .
          '</div>' .
          '<div class="d-flex flex-column">' .
          '<span class="text-teal text-truncate fw-black hover-teal" style="font-size: 0.85rem; text-decoration: underline dotted;">' .
          $attendance->user?->getFullName() .
          '</span>' .
          '<small class="text-muted fw-bold" style="font-size: 0.7rem;">' .
          $attendance->user?->code .
          '</small>' .
          '</div>' .
          '</div>';
      })
      ->rawColumns(['user', 'status', 'actions', 'shift', 'working_hours', 'check_in_time', 'check_out_time'])
      ->with('stats', function() use ($query) {
          $stats = (clone $query)
              ->selectRaw("
                  COUNT(*) as total,
                  SUM(CASE WHEN (LOWER(status) = 'present' OR status IS NULL OR LOWER(status) = 'paid_leave') AND admin_reason IS NULL AND (LOWER(status) = 'paid_leave' OR TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time) < 480) THEN 1 ELSE 0 END) as half_days,
                  SUM(CASE WHEN LOWER(status) = 'absent' THEN 1 ELSE 0 END) as absents,
                  SUM(CASE WHEN LOWER(status) IN ('on_leave', 'leave', 'work_from_home', 'wfh', 'unpaid_leave') THEN 1 ELSE 0 END) as leaves,
                  SUM(CASE WHEN LOWER(status) = 'late' THEN 1 ELSE 0 END) as lates,
                  SUM(CASE WHEN (LOWER(status) = 'present' OR status IS NULL OR LOWER(status) = 'paid_leave') AND (admin_reason IS NOT NULL OR LOWER(status) = 'paid_leave' OR TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time) >= 480) THEN 1 ELSE 0 END) as presents
              ")
              ->first();

          $totalCount = max($stats->total, 1);
          $presentCount = $stats->presents;
          $lateCount = $stats->lates + $stats->half_days;
          
          return [
              'present' => $presentCount,
              'absent' => $stats->absents,
              'leave' => $stats->leaves,
              'late' => $lateCount,
              'presentPercentage' => round(($presentCount / $totalCount) * 100)
          ];
      })
      ->make(true);
  }

  public function registryAjax(Request $request)
  {
      $month = $request->input('month', now()->month);
      $year = $request->input('year', now()->year);
      $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
      $endOfMonth = $startOfMonth->copy()->endOfMonth();
      $daysInMonth = $startOfMonth->daysInMonth;
      
      $currentUser = auth()->user();
      $scopedIds = $this->getScopedUserIds($currentUser);

      // Get filtered users
      $usersQ = User::whereIn('status', [UserAccountStatus::ACTIVE, UserAccountStatus::ONBOARDING_SUBMITTED]);

      if ($scopedIds) {
          $usersQ->whereIn('id', $scopedIds);
      }
      
      if ($request->userId) $usersQ->where('id', $request->userId);
      if ($request->shiftId) $usersQ->where('shift_id', $request->shiftId);
      if ($request->teamId) $usersQ->where('team_id', $request->teamId);
      if ($request->departmentId) $usersQ->where('department_id', $request->departmentId);
      
      if ($request->searchTerm) {
          $term = $request->searchTerm;
          $usersQ->where(function($q) use ($term) {
              $q->where('first_name', 'like', "%$term%")
                ->orWhere('last_name', 'like', "%$term%")
                ->orWhere('code', 'like', "%$term%");
          });
      }

      $users = $usersQ->with([
          'attendance' => function($q) use ($month, $year) {
              $q->whereMonth('check_in_time', $month)
                ->whereYear('check_in_time', $year)
                ->with(['updatedBy', 'leaveRequest.leaveType']);
          },
          'leaveRequests' => function($q) use ($startOfMonth, $endOfMonth) {
              $q->where('status', LeaveRequestStatus::APPROVED)
                ->where(function($q2) use ($startOfMonth, $endOfMonth) {
                    $q2->whereBetween('from_date', [$startOfMonth, $endOfMonth])
                       ->orWhereBetween('to_date', [$startOfMonth, $endOfMonth]);
                })->with('leaveType');
          }
      ])->get();

      // Get holidays for this month
      $holidays = Holiday::whereYear('date', $year)
          ->whereMonth('date', $month)
          ->get()
          ->pluck('name', 'date');

      $data = [];
      foreach ($users as $user) {
          /** @var \App\Models\User $user */
          $row = [
              'employee' => $user->getFullName(),
              'code' => $user->code,
              'presents' => 0,
              'absents' => 0,
              'lates' => 0,
              'leaves' => 0
          ];

          for ($day = 1; $day <= $daysInMonth; $day++) {
              $dateObj = Carbon::create($year, $month, $day);
              $dateStr = $dateObj->toDateString();
              
              $attendance = $user->attendance->first(function($a) use ($dateStr) {
                  return $a->check_in_time->toDateString() == $dateStr;
              });

              $dayData = [
                  'status' => 'Missing',
                  'in' => '--',
                  'out' => '--',
                  'hours' => '--',
                  'class' => 'bg-light text-muted opacity-50',
                  'user_id' => $user->id,
                  'full_date' => $dateStr
              ];
              
              $holidayName = null;
              foreach ($holidays as $hDate => $hName) {
                  if (Carbon::parse($hDate)->toDateString() == $dateStr) { $holidayName = $hName; break; }
              }

              if ($holidayName) {
                  $dayData = ['status' => 'Holiday', 'in' => $holidayName, 'out' => '--', 'hours' => 'HOL', 'class' => 'bg-info bg-opacity-25 text-info border-info border-opacity-50'];
              } elseif ($attendance) {
                  $dayData['id'] = $attendance->id;
                  $dayData['in'] = $attendance->check_in_time->format('H:i');
                  $dayData['out'] = $attendance->check_out_time ? $attendance->check_out_time->format('H:i') : '--';
                  
                  if ($attendance->check_in_time && $attendance->check_out_time) {
                      $diff = $attendance->check_in_time->diff($attendance->check_out_time); $dayData['hours'] = sprintf('%d:%02dh', $diff->h, $diff->i);
                  } else {
                      $dayData['hours'] = '0:00h';
                  }

                  $dayData['is_edited'] = !empty($attendance->admin_reason);
                  $dayData['editor_name'] = $attendance->updatedBy?->getFullName() ?? 'Admin';
                  $dayData['admin_reason'] = $attendance->admin_reason;
                  $dayData['attachment'] = $attendance->attachment ? asset('storage/' . $attendance->attachment) : null;
                  $dayData['is_short_leave'] = !empty($attendance->leave_request_id);
                  $dayData['user_id'] = $user->id;
                  $dayData['full_date'] = $dateStr;

                  $s = strtolower($attendance->status);
                  
                    // Dynamic enforcement of 8-hour (Full Day) rule
                    if (empty($attendance->admin_reason) && $attendance->check_in_time && $attendance->check_out_time) {
                        $mins = $attendance->check_in_time->diffInMinutes($attendance->check_out_time);
                        if ($mins >= 480) {
                            $s = 'present';
                        } elseif ($mins < 480 && ($s === 'present')) {
                            $s = 'half-day';
                        }
                    }

                   if ($s == 'present') {
                       $dayData['status'] = 'Present'; $dayData['class'] = 'bg-teal text-white'; $row['presents']++;
                   } elseif ($s == 'late') {
                       $dayData['status'] = 'Late'; $dayData['class'] = 'bg-warning text-white'; $row['lates']++;
                   } elseif ($s == 'half-day' || $s == 'half-Day') {
                       $dayData['status'] = 'Half Day'; $dayData['class'] = 'bg-orange text-white'; $row['lates']++;
                   } elseif ($s == 'absent') {
                      $dayData['status'] = 'Absent'; $dayData['class'] = 'bg-red text-white'; $row['absents']++;
                  } elseif ($s == 'paid_leave' || ($s == 'leave' && $attendance->leaveRequest?->leaveType?->is_paid)) {
                      $dayData['status'] = 'Paid Leave'; $dayData['class'] = 'bg-teal text-white'; $row['presents']++;
                  } elseif ($s == 'unpaid_leave' || $s == 'on_leave' || $s == 'leave') {
                      $dayData['status'] = 'Unpaid Leave'; $dayData['class'] = 'bg-purple-vibrant text-white';
                  } elseif ($s == 'work_from_home' || $s == 'wfh') {
                      $dayData['status'] = 'WFH'; $dayData['class'] = 'bg-indigo-vibrant text-white';
                  }
              } else {
                  // No attendance log. Check for approved leaves
                  $leave = $user->leaveRequests->first(function($l) use ($dateStr) {
                      return Carbon::parse($l->from_date)->toDateString() <= $dateStr && 
                             Carbon::parse($l->to_date)->toDateString() >= $dateStr;
                  });

                  /** @var \App\Models\User $user */
                  $isWorkingDay = \App\Services\LeavePolicyService::isWorkingDay($user, $dateObj);
                  if ($leave) {
                      $isPaid = $leave->leaveType?->is_paid ?? false;
                      $dayData['status'] = $isPaid ? 'Paid Leave' : 'Leave';
                      $dayData['in'] = $leave->leaveType->name ?? 'Leave';
                      $dayData['out'] = '--';
                      $dayData['hours'] = $leave->leaveType->code ?? 'LV';
                      $dayData['class'] = ($isPaid ? 'bg-teal' : 'bg-purple-vibrant') . ' text-white border-0';
                      $row['leaves']++;
                  } elseif (!$isWorkingDay) {
                      $dayData['status'] = 'OFF'; 
                      $dayData['hours'] = 'OFF'; 
                      $dayData['class'] = 'bg-secondary bg-opacity-10 text-muted';
                  } elseif ($dateObj->isFuture() && !$dateObj->isToday()) {
                      // Future Dates: Scheduled
                      $dayData['status'] = 'Scheduled'; 
                      $dayData['in'] = 'Upcoming'; 
                      $dayData['out'] = '--'; 
                      $dayData['hours'] = '--';
                      $dayData['class'] = 'bg-white border text-muted opacity-50';
                  } elseif ($dateObj->isPast() && !$dateObj->isToday()) {
                      // It's a past day, not off, not holiday, no attendance, no leave => ABSENT
                      $dayData['status'] = 'Absent'; 
                      $dayData['in'] = 'No Log'; 
                      $dayData['out'] = '--'; 
                      $dayData['hours'] = '--'; 
                      $dayData['class'] = 'bg-red text-white';
                      $row['absents']++;
                  } else {
                      // Today: No log yet
                      $dayData['status'] = 'Today'; 
                      $dayData['in'] = 'Today'; 
                      $dayData['out'] = '--'; 
                      $dayData['hours'] = '--'; 
                      $dayData['class'] = 'bg-white border-primary border-dashed text-primary';
                  }
              }
              
              $row['day_'.$day] = $dayData;
          }
          $data[] = $row;
      }

      return response()->json([
          'daysInMonth' => $daysInMonth,
          'data' => $data,
          'month' => (int)$month,
          'year' => (int)$year
      ]);
  }

    public function chartAjax(Request $request)
    {
        $period = $request->input('period', '7days');
        $teamId = $request->input('teamId');
        $userId = $request->input('userId');

        $endDate = Carbon::now();
        $startDate = Carbon::now();

        if ($period == 'today') {
            $startDate = Carbon::now()->startOfDay();
        } elseif ($period == 'yesterday') {
            $startDate = Carbon::now()->subtract(1, 'days')->startOfDay();
            $endDate = Carbon::now()->subtract(1, 'days')->endOfDay();
        } elseif ($period == '7days') {
            $startDate = Carbon::now()->subtract(6, 'days')->startOfDay();
        } elseif ($period == '1month') {
            $startDate = Carbon::now()->subtract(30, 'days')->startOfDay();
        } elseif ($period == '3months') {
            $startDate = Carbon::now()->subtract(3, 'months')->startOfDay();
        } elseif ($period == '1year') {
            $startDate = Carbon::now()->subtract(1, 'years')->startOfDay();
        }

        $categories = [];
        $presentData = [];
        $absentData = [];

        $currentUser = auth()->user();
        $scopedIds = $this->getScopedUserIds($currentUser);

        // Determine Total staff count for this filter to calculate absents
        // Matches registryAjax to include active and submitted onboarding staff
        $userCount = User::whereIn('status', [\App\Enums\UserAccountStatus::ACTIVE, \App\Enums\UserAccountStatus::ONBOARDING_SUBMITTED]);
        if ($scopedIds) $userCount->whereIn('id', $scopedIds);
        if ($teamId) $userCount->where('team_id', $teamId);
        if ($userId) $userCount->where('id', $userId);
        if ($request->shiftId) $userCount->where('shift_id', $request->shiftId);
        if ($request->searchTerm) {
            $term = $request->searchTerm;
            $userCount->where(function($q) use ($term) {
                $q->where('first_name', 'like', "%$term%")
                  ->orWhere('last_name', 'like', "%$term%")
                  ->orWhere('code', 'like', "%$term%");
            });
        }
        $totalStaff = $userCount->count();

        $attendancesByDate = Attendance::whereBetween('check_in_time', [$startDate, $endDate])
            ->selectRaw("DATE(check_in_time) as date, COUNT(*) as count")
            ->whereIn('status', ['present', 'late', 'work_from_home', 'Present', 'Late', 'Work From Home'])
            ->when($scopedIds, fn($q) => $q->whereIn('user_id', $scopedIds))
            ->when($teamId, fn($q) => $q->whereHas('user', fn($u) => $u->where('team_id', $teamId)))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($request->shiftId, fn($q) => $q->where('shift_id', $request->shiftId))
            ->when($request->searchTerm, function($q) use ($request) {
                $term = $request->searchTerm;
                $q->whereHas('user', function($u) use ($term) {
                    $u->where('first_name', 'like', "%{$term}%")
                      ->orWhere('last_name', 'like', "%{$term}%")
                      ->orWhere('code', 'like', "%{$term}%");
                });
            })
            ->groupBy('date')
            ->pluck('count', 'date');

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateStr = $date->toDateString();
            $label = $period == 'today' ? $date->format('H:i') : $date->format('d M');
            if ($period == '7days') $label = $date->format('D');
            
            $categories[] = $label;

            $pCount = $attendancesByDate->get($dateStr, 0);
            $presentData[] = $pCount;

            // Count Absents (Simple Formula: Total Filtered Staff - Present)
            $aCount = $totalStaff - $pCount;
            if ($aCount < 0) $aCount = 0; 
            $absentData[] = $aCount;
        }

        return response()->json([
            'categories' => $categories,
            'series' => [
                ['name' => 'Present', 'data' => $presentData],
                ['name' => 'Absent', 'data' => $absentData]
            ]
        ]);
    }

    public function editAjax($id)
    {
        $attendance = Attendance::with('user')->findOrFail($id);
        return response()->json([
            'success' => true,
            'attendance' => $attendance,
            'user' => $attendance->user->getFullName()
        ]);
    }

    public function updateAjax(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
        
        // Validation: If marking as Full Day (Present) from an Absent state, proof is required
        if ($request->status === 'present' && !$attendance->attachment && !$request->hasFile('attachment')) {
            return response()->json(['success' => false, 'message' => 'Proof of adjustment (attachment) is required when marking as Full Day.'], 422);
        }

        if (empty($request->admin_reason)) {
            return response()->json(['success' => false, 'message' => 'Adjustment reason is required.'], 422);
        }

        $data = [
            'status' => $request->status,
            'admin_reason' => $request->admin_reason,
            'updated_by_id' => auth()->id(),
            'check_in_time' => $request->check_in_time ? \Carbon\Carbon::parse($attendance->check_in_time->format('Y-m-d') . ' ' . $request->check_in_time) : $attendance->check_in_time,
            'check_out_time' => $request->check_out_time ? \Carbon\Carbon::parse($attendance->check_in_time->format('Y-m-d') . ' ' . $request->check_out_time) : $attendance->check_out_time,
        ];

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('attendance/attachments', 'public');
        }

        $attendance->update($data);

        return response()->json(['success' => true, 'message' => 'Attendance updated successfully.']);
    }
    public function storeAdjustmentAjax(Request $request)
    {
        // Validation: If marking as Full Day (Present), proof is required
        if ($request->status === 'present' && !$request->hasFile('attachment')) {
            return response()->json(['success' => false, 'message' => 'Proof of adjustment (attachment) is required when marking as Full Day.'], 422);
        }

        if (empty($request->admin_reason)) {
            return response()->json(['success' => false, 'message' => 'Adjustment reason is required.'], 422);
        }

        $date = $request->date; // YYYY-MM-DD
        $userId = $request->user_id;

        $checkIn = \Carbon\Carbon::parse($date . ' ' . ($request->check_in_time ?: '09:00'));
        $checkOut = \Carbon\Carbon::parse($date . ' ' . ($request->check_out_time ?: '18:00'));

        $user = User::findOrFail($userId);

        $attendance = Attendance::create([
            'user_id' => $userId,
            'shift_id' => $user->shift_id,
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
            'status' => $request->status,
            'admin_reason' => $request->admin_reason,
            'updated_by_id' => auth()->id(),
            'attachment' => $request->hasFile('attachment') ? $request->file('attachment')->store('attendance/attachments', 'public') : null
        ]);

        return response()->json(['success' => true, 'message' => 'Attendance created and adjusted successfully.']);
    }
}
