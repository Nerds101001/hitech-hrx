<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\LeaveRequestStatus;
use App\Enums\Status;
use App\Enums\UserAccountStatus;
use App\Helpers\TrackingHelper;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\DeviceStatusLog;
use App\Models\DocumentRequest;
use App\Models\ExpenseRequest;
use App\Models\FormEntry;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LoanRequest;
use App\Models\ProductOrder;
use App\Models\Settings;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Visit;
use App\Constants\Constants as AppConstants;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobStage;
use App\Models\Announcement;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
  public function index()
  {
    // Check if user is HR and return HR dashboard directly
    $user = auth()->user();
    $isManager = $user->hasRole('manager');

    if ($user && ($user->hasRole(['admin', 'hr']) || $isManager)) {
        // ... (existing admin/hr logic below or manager specific logic)
      // Calculate base HR stats
      $totalUser = User::count();
      $active = User::where('status', UserAccountStatus::ACTIVE)->count();
      $presentUsersCount = Attendance::whereDate('check_in_time', now())->where('status', 'present')->count();
      $onLeaveUsersCount = LeaveRequest::whereDate('from_date', now())
        ->where('status', LeaveRequestStatus::APPROVED)
        ->count();
      $todayAbsentUsers = $active - $presentUsersCount;

      $pendingLeaveRequests = LeaveRequest::where('status', 'pending')->count();
      $pendingExpenseRequests = ExpenseRequest::where('status', 'pending')->count();
      $pendingDocumentRequests = DocumentRequest::where('status', 'pending')->count();
      $pendingLoanRequests = LoanRequest::where('status', 'pending')->count();

      $teamOutToday = LeaveRequest::whereDate('from_date', '<=', now())
        ->whereDate('to_date', '>=', now())
        ->where('status', LeaveRequestStatus::APPROVED)
        ->with(['user', 'leaveType'])
        ->get();

      // --- Strategic Data for Revamped Dashboard ---

      // 1. Hiring Trends (Last 12 Months) - Optimized with single aggregation queries
      $twelveMonthsAgo = Carbon::now()->subMonths(11)->startOfMonth();
      
      $hiresByMonth = User::where('date_of_joining', '>=', $twelveMonthsAgo)
        ->selectRaw("DATE_FORMAT(date_of_joining, '%M %Y') as month, count(*) as count")
        ->groupBy('month')
        ->pluck('count', 'month');

      $attritionByMonth = User::where('relieved_at', '>=', $twelveMonthsAgo)
        ->selectRaw("DATE_FORMAT(relieved_at, '%M %Y') as month, count(*) as count")
        ->groupBy('month')
        ->pluck('count', 'month');

      $hiringTrend = ['labels' => [], 'hires' => [], 'attrition' => []];
      for ($i = 11; $i >= 0; $i--) {
        $monthLabel = Carbon::now()->subMonths($i)->format('F Y');
        $hiringTrend['labels'][] = Carbon::now()->subMonths($i)->format('M Y');
        $hiringTrend['hires'][] = $hiresByMonth->get($monthLabel, 0);
        $hiringTrend['attrition'][] = $attritionByMonth->get($monthLabel, 0);
      }

      // 2. Department Distribution
      $departmentData = Department::withCount('users')->get()->map(function ($dept) {
        return [
          'name' => $dept->name,
          'count' => $dept->users_count
        ];
      });

      // 3. Announcements
      $announcements = Announcement::where('is_active', true)
        ->where('start_date', '<=', now())
        ->latest()
        ->take(5)
        ->get();

      // 4. Recruitment Pipeline (Top Candidates)
      $topCandidates = JobApplication::with(['jobs', 'stage'])
        ->latest()
        ->take(3)
        ->get();

      $jobStages = JobStage::orderBy('order', 'asc')->get();

      // 5. Active Job Openings - Optimized with withCount
      $activeJobsCount = Job::where('status', 'active')->count();
      $activeJobs = Job::where('status', 'active')
        ->withCount('applications')
        ->latest()
        ->take(4)
        ->get();

      // 6. Recent Applicant Activity
      $newApplicantsToday = JobApplication::whereDate('created_at', now())->count();
      $recentActivities = JobApplication::with('jobs')

        ->latest()
        ->take(6)
        ->get();

      // 7. Celebrations (Birthdays & Work Anniversaries - Next 6 Imminent)
      $todayMd = now()->format('md');

      $upcomingBirthdays = User::whereNotNull('dob')
        ->orderByRaw("CASE WHEN DATE_FORMAT(dob, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
        ->orderByRaw("DATE_FORMAT(dob, '%m%d') ASC")
        ->get()
        ->unique('id')
        ->take(6)
        ->map(function ($u) use ($todayMd) {
          $u->is_today = (Carbon::parse($u->dob)->format('md') === $todayMd);
          return $u;
        });

      $upcomingAnniversaries = User::whereNotNull('date_of_joining')
        ->orderByRaw("CASE WHEN DATE_FORMAT(date_of_joining, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
        ->orderByRaw("DATE_FORMAT(date_of_joining, '%m%d') ASC")
        ->get()
        ->unique('id')
        ->take(6)
        ->map(function ($u) use ($todayMd) {
          $u->is_today = (Carbon::parse($u->date_of_joining)->format('md') === $todayMd);
          return $u;
        });

      // 8. Upcoming Probation Ends (Past the date + Next 30 Days)
      $upcomingProbationEnds = User::where('status', UserAccountStatus::ACTIVE)
        ->whereNotNull('probation_end_date')
        ->whereNull('probation_confirmed_at')
        ->where('probation_end_date', '<=', now()->addDays(30)->toDateString())
        ->with('reportingTo')
        ->orderBy('probation_end_date')
        ->get();

      // Extra Stats for Suggestions
      $absentCount = max(0, $active - $presentUsersCount - $onLeaveUsersCount);
      $newHiresThisMonth = User::whereMonth('date_of_joining', now()->month)->whereYear('date_of_joining', now()->year)->count();

      // 8. Pending Approvals Data (With Detailed Info)
      $pendingApprovals = collect();

      LeaveRequest::where('status', 'pending')->with(['user.designation.department', 'leaveType'])->each(function ($r) use ($pendingApprovals) {
        $days = 1;
        if ($r->from_date && $r->to_date) {
          try {
            $days = Carbon::parse($r->from_date)->diffInDays(Carbon::parse($r->to_date)) + 1;
          }
          catch (\Exception $e) {
            $days = 1;
          }
        }
        $pendingApprovals->push([
          'type' => 'Leave',
          'user' => $r->user?->name ?? 'N/A',
          'emp_id' => $r->user?->code ?? 'N/A',
          'department' => $r->user?->designation?->department?->name ?? 'HR',
          'avatar' => $r->user ? $r->user->getProfilePicture() : asset('assets/img/avatars/1.png'),
          'date' => $r->from_date ?Carbon::parse($r->from_date)->format('M d') : 'N/A',
          'raw_date' => $r->from_date ?Carbon::parse($r->from_date) : now(),
          'details' => (optional($r->leaveType)->name ?? 'Request') . ' (' . $days . ' days)',
          'days' => $days,
          'id' => $r->id
        ]);
      });

      ExpenseRequest::where('status', 'pending')->with(['user.designation.department', 'expenseType'])->each(function ($r) use ($pendingApprovals) {
        $pendingApprovals->push([
          'type' => 'Expense',
          'user' => $r->user?->name ?? 'N/A',
          'emp_id' => $r->user?->code ?? 'N/A',
          'department' => $r->user?->designation?->department?->name ?? 'Finance',
          'avatar' => $r->user ? $r->user->getProfilePicture() : asset('assets/img/avatars/1.png'),
          'date' => Carbon::parse($r->created_at)->format('M d'),
          'raw_date' => Carbon::parse($r->created_at),
          'details' => 'Amount: ' . number_format($r->amount ?? 0, 2),
          'id' => $r->id
        ]);
      });

      DocumentRequest::where('status', 'pending')->with(['user.designation.department', 'documentType'])->each(function ($r) use ($pendingApprovals) {
        $pendingApprovals->push([
          'type' => 'Document',
          'user' => $r->user?->name ?? 'N/A',
          'emp_id' => $r->user?->code ?? 'N/A',
          'department' => $r->user?->designation?->department?->name ?? 'Admin',
          'avatar' => $r->user ? $r->user->getProfilePicture() : asset('assets/img/avatars/1.png'),
          'date' => Carbon::parse($r->created_at)->format('M d'),
          'raw_date' => Carbon::parse($r->created_at),
          'details' => 'Req: ' . (optional($r->documentType)->name ?? 'Document'),
          'id' => $r->id
        ]);
      });

      LoanRequest::where('status', 'pending')->with(['user.designation.department'])->each(function ($r) use ($pendingApprovals) {
        $pendingApprovals->push([
          'type' => 'Loan',
          'user' => $r->user?->name ?? 'N/A',
          'emp_id' => $r->user?->code ?? 'N/A',
          'department' => $r->user?->designation?->department?->name ?? 'Operations',
          'avatar' => $r->user ? $r->user->getProfilePicture() : asset('assets/img/avatars/1.png'),
          'date' => Carbon::parse($r->created_at)->format('M d'),
          'raw_date' => Carbon::parse($r->created_at),
          'details' => 'Amt: ' . number_format($r->amount ?? 0, 2),
          'id' => $r->id
        ]);
      });

      $pendingApprovals = $pendingApprovals->sortByDesc('raw_date');

      // Trending dummy data for better visuals
      $trends = [
        'totalStaff' => ['value' => '+4%', 'isUp' => true],
        'present' => ['value' => '+12%', 'isUp' => true],
        'leaves' => ['value' => '-2', 'isUp' => false],
        'openings' => ['value' => '+3 New', 'isUp' => true]
      ];

      // MANAGER SPECIFIC SCOPING FOR REVAMP
      if ($isManager) {
        $teamMemberIds = User::where('reporting_to_id', $user->id)->pluck('id')->toArray();
        $teamMemberIds[] = $user->id; // Include self

        $totalUser = count($teamMemberIds);
        $active = User::whereIn('id', $teamMemberIds)->where('status', UserAccountStatus::ACTIVE)->count();
        $presentUsersCount = Attendance::whereIn('user_id', $teamMemberIds)->whereDate('check_in_time', now())->count();
        $onLeaveUsersCount = LeaveRequest::whereIn('user_id', $teamMemberIds)->whereDate('from_date', '<=', now())
            ->whereDate('to_date', '>=', now())
            ->where('status', LeaveRequestStatus::APPROVED)
            ->count();
        
        $todayAbsentUsers = $active - $presentUsersCount - $onLeaveUsersCount;
        if ($todayAbsentUsers < 0) $todayAbsentUsers = 0;

        $teamOutToday = LeaveRequest::whereIn('user_id', $teamMemberIds)
            ->whereDate('from_date', '<=', now())
            ->whereDate('to_date', '>=', now())
            ->where('status', LeaveRequestStatus::APPROVED)
            ->with(['user', 'leaveType'])
            ->get();
        
        $pendingLeaveRequests = LeaveRequest::whereIn('user_id', $teamMemberIds)->where('status', 'pending')->count();
        $pendingExpenseRequests = ExpenseRequest::whereIn('user_id', $teamMemberIds)->where('status', 'pending')->count();

        return view('tenant.users.dashboard.manager-index', [
            'totalUser' => $totalUser,
            'activeEmployees' => $active,
            'active' => $active,
            'presentUsersCount' => $presentUsersCount,
            'todayPresentUsers' => $presentUsersCount,
            'todayOnLeaveCount' => $onLeaveUsersCount,
            'todayAbsentUsers' => $todayAbsentUsers,
            'pendingLeaveRequests' => $pendingLeaveRequests,
            'pendingExpenseRequests' => $pendingExpenseRequests,
            'pendingDocumentRequests' => DocumentRequest::whereIn('user_id', $teamMemberIds)->where('status', 'pending')->count(),
            'pendingLoanRequests' => LoanRequest::whereIn('user_id', $teamMemberIds)->where('status', 'pending')->count(),
            'teamOutToday' => $teamOutToday,
            'orgBirthdays' => $upcomingBirthdays,
            'orgAnniversaries' => $upcomingAnniversaries,
            'recentNotices' => $announcements, // Reuse announcements as notices
            'trends' => $trends,
            'myLeavesCount' => 0,
            'myExpensesCount' => 0,
            'mySOSCount' => 0,
            'nextHoliday' => Holiday::where('date', '>=', now())->orderBy('date')->first(),
            'payrollTrend' => 0,
            'latestNetSalary' => 0
        ]);
      }

      // Return HR dashboard view directly
      return view('tenant.users.dashboard.hr-index', [
        'pageConfigs' => ['contentLayout' => 'wide'],
        'totalUser' => $totalUser,
        'activeEmployees' => $active,
        'active' => $active,
        'presentUsersCount' => $presentUsersCount,
        'pendingLeaveRequests' => $pendingLeaveRequests,
        'pendingExpenseRequests' => $pendingExpenseRequests,
        'pendingDocumentRequests' => $pendingDocumentRequests,
        'pendingLoanRequests' => $pendingLoanRequests,
        'todayPresentUsers' => $presentUsersCount,
        'todayAbsentUsers' => $todayAbsentUsers,
        'onLeaveUsersCount' => $onLeaveUsersCount,
        'teamOutToday' => $teamOutToday,
        'hiringTrend' => $hiringTrend,
        'departmentData' => $departmentData,
        'announcements' => $announcements,
        'topCandidates' => $topCandidates,
        'jobStages' => $jobStages,
        'activeJobs' => $activeJobs,
        'recentActivities' => $recentActivities,
        'upcomingBirthdays' => $upcomingBirthdays,
        'upcomingAnniversaries' => $upcomingAnniversaries,
        'newApplicantsToday' => $newApplicantsToday,
        'activeJobsCount' => $activeJobsCount,
        'pendingApprovals' => $pendingApprovals->sortByDesc('raw_date'),
        'trends' => $trends,
        'upcomingProbationEnds' => $upcomingProbationEnds,
        'upcomingHolidays' => Holiday::where('date', '>=', now()->toDateString())->orderBy('date', 'asc')->take(5)->get(),
        'absentCount' => $absentCount,
        'newHiresThisMonth' => $newHiresThisMonth,

        'roles' => \Spatie\Permission\Models\Role::all(),
        'departments' => Department::where('status', Status::ACTIVE)->get(),
        'teams' => Team::where('status', Status::ACTIVE)->get(),
        'designations' => \App\Models\Designation::where('status', Status::ACTIVE)->get(),
        'managers' => User::whereHas('roles', function($q) {
            $q->whereIn('name', ['admin', 'hr', 'manager']);
        })->where('status', UserAccountStatus::ACTIVE)->get(),

        'myLeavesCount' => 0,
        'myExpensesCount' => 0,
        'mySOSCount' => 0,
        'nextHoliday' => Holiday::where('date', '>=', now())->orderBy('date')->first(),
        'recentNotices' => collect(),
        'payrollTrend' => 0,
        'latestNetSalary' => 0
      ]);
    }

    $totalUser = User::count();
    $active = User::where('status', UserAccountStatus::ACTIVE)->count();
    $presentUsersCount = Attendance::whereDate('check_in_time', now())->where('status', 'present')->count();
    $presentUsersCountLastWeek = Attendance::whereBetween('created_at', [now()->startOfWeek()->subWeek(), now()->endOfWeek()->subWeek()])
      ->where('check_out_time', '!=', null)
      ->get()
      ->sum(function ($attendance) {
      return $attendance->check_in_time->diffInMinutes($attendance->check_out_time);
    });

    $thisWeekWorkingHours = Attendance::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
      ->where('check_out_time', '!=', null)
      ->get()
      ->sum(function ($attendance) {
      return $attendance->check_in_time->diffInMinutes($attendance->check_out_time);
    });

    $todayHours = Attendance::whereDate('check_in_time', now())
      ->where('check_out_time', '!=', null)
      ->get()
      ->sum(function ($attendance) {
      return $attendance->check_in_time->diffInMinutes($attendance->check_out_time);
    });

    $onLeaveUsersCount = LeaveRequest::whereDate('from_date', now())
      ->where('status', LeaveRequestStatus::APPROVED)
      ->count();

    // Holiday & team availability — same as UserDashboardController
    $user = auth()->user();
    $nextHoliday = Holiday::where('date', '>=', now()->toDateString())
      ->where('status', 1)
      ->where(function ($q) use ($user) {
      $q->whereNull('site_id')
        ->orWhere('site_id', $user->site_id);
    })
      ->orderBy('date', 'asc')
      ->first();

    $teamOutToday = LeaveRequest::whereDate('from_date', '<=', now())
      ->whereDate('to_date', '>=', now())
      ->where('status', LeaveRequestStatus::APPROVED)
      ->with(['user', 'leaveType'])
      ->get();

    return view('tenant.dashboard.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'totalUser' => $totalUser,
      'activeEmployees' => $active,
      'active' => $active,
      'presentUsersCount' => $presentUsersCount,
      'pendingLeaveRequests' => LeaveRequest::where('status', 'pending')->count(),
      'pendingExpenseRequests' => ExpenseRequest::where('status', 'pending')->count(),
      'pendingDocumentRequests' => DocumentRequest::where('status', 'pending')->count(),
      'pendingLoanRequests' => LoanRequest::where('status', 'pending')->count(),
      'thisWeekWorkingHours' => round($thisWeekWorkingHours, 2),
      'todayHours' => round($todayHours, 2),
      'tasks' => Task::where('status', 'new')->count(),
      'onGoingTasks' => Task::where('status', 'in_progress')->count(),
      'todayPresentUsers' => Attendance::whereDate('check_in_time', now())->where('status', 'present')->count(),
      'todayAbsentUsers' => $active - Attendance::whereDate('check_in_time', now())->where('status', 'present')->count(),
      'presentUsersCountLastWeek' => $presentUsersCountLastWeek,
      'absentUsersCountLastWeek' => $active - $presentUsersCountLastWeek,
      'onLeaveUsersCount' => $onLeaveUsersCount,
      'nextHoliday' => $nextHoliday,
      'teamOutToday' => $teamOutToday,
    ]);
  }

  public function getRecentActivities()
  {
    $activities = collect();

    // Fetch Orders
    $orders = ProductOrder::with('user')
      ->select('id', 'order_no', 'user_id', 'created_at')
      ->latest('created_at')
      ->limit(10)
      ->get()
      ->map(function ($order) {
      return [
      'id' => $order->id,
      'title' => $order->order_no,
      'created_at_human' => $order->created_at->diffForHumans(),
      'created_at' => $order->created_at,
      'type' => 'Order',
      'user' => $order->user ? $order->user->getUserForProfile() : 'N/A'
      ];
    });

    // Fetch Visits
    $visits = Visit::with('client')
      ->with('createdBy')
      ->select('id', 'client_id', 'created_by_id', 'created_at')
      ->latest('created_at')
      ->limit(10)
      ->get()
      ->map(function ($visit) {
      return [
      'id' => $visit->id,
      'title' => $visit->client->name ?? 'No Client Name',
      'created_at_human' => $visit->created_at->diffForHumans(),
      'created_at' => $visit->created_at,
      'type' => 'Visit',
      'user' => $visit->createdBy ? $visit->createdBy->getUserForProfile() : 'N/A'
      ];
    });

    // Fetch Form Submissions
    $forms = FormEntry::with('form')
      ->with('user')
      ->select('id', 'form_id', 'created_at')
      ->latest('created_at')
      ->limit(10)
      ->get()
      ->map(function ($form) {
      return [
      'id' => $form->id,
      'title' => $form->form->name ?? 'No Form Name',
      'created_at_human' => $form->created_at->diffForHumans(),
      'created_at' => $form->created_at,
      'type' => 'Form Submission'
      ];
    });

    // Fetch Tasks
    $tasks = Task::with('user')
      ->select('id', 'title', 'user_id', 'created_at')
      ->latest('created_at')
      ->limit(10)
      ->get()
      ->map(function ($task) {
      return [
      'id' => $task->id,
      'title' => $task->title,
      'created_at_human' => $task->created_at->diffForHumans(),
      'created_at' => $task->created_at,
      'type' => 'Task',
      'user' => $task->user ? $task->user->getUserForProfile() : 'N/A'
      ];
    });

    // Merge all collections and sort them by created_at
    $activities = $activities
      ->merge($orders)
      ->merge($visits)
      ->merge($forms)
      ->merge($tasks)
      ->sortByDesc('created_at')
      ->take(10)
      ->values();

    return Success::response($activities);
  }

  public function liveLocationView()
  {
    return view('tenant.dashboard.live_location_view', [
      'pageConfigs' => ['contentLayout' => 'wide']
    ]);
  }

  public function liveLocationAjax()
  {

    try {

      $todayAttendances = Attendance::with('user.userDevice')
        ->whereDate('created_at', '>=', now())
        ->with('user')->with('user.userDevice')
        ->get();


      $response = [];

      $settings = Settings::first();

      $trackingHelper = new TrackingHelper();
      foreach ($todayAttendances as $attendance) {

        if ($attendance->user->userDevice == null) {
          continue;
        }


        $status = 'offline';
        //  ? $status = 'online' : $status = 'offline';
        if ($trackingHelper->isUserOnline($attendance->user->userDevice->updated_at)) {
          $status = 'online';
        }

        $response[] = [
          'id' => $attendance->user_id,
          'name' => $attendance->user->getFullName(),
          'initials' => $attendance->user->getInitials(),
          'code' => $attendance->user->code,
          'profilePicture' => $attendance->user->getProfilePicture(),
          'designation' => $attendance->user->designation ? $attendance->user->designation->name : 'N/A',
          'latitude' => $attendance->user->userDevice->latitude,
          'longitude' => $attendance->user->userDevice->longitude,
          'status' => $status,
          'updatedAt' => $attendance->user->userDevice->updated_at->diffForHumans(),
          'type' => $settings->offline_check_time_type,
          'time' => $settings->offline_check_time,
        ];
      }

      return response()->json($response);
    }
    catch (Exception $e) {
      Log::error($e->getMessage());
      return response()->json($e->getMessage());
    }
  }

  public function cardView()
  {
    $teamsList = Team::where('status', 'active')
      ->get();

    $attendances = Attendance::whereDate('created_at', now())
      ->with('attendanceLogs')
      ->get();

    $trackingHelper = new TrackingHelper();

    $users = User::where('status', '=', 'active')
      ->where('team_id', '!=', null)
      ->where('shift_id', '!=', null)
      ->get();

    $userDevices = UserDevice::whereIn('user_id', $users->pluck('id'))
      ->get();

    $teams = [];
    foreach ($teamsList as $team) {

      $user = $users->where('team_id', '=', $team->id);

      $teamAttendances = $attendances->whereIn('user_id', $user->pluck('id'));

      $cardItems = [];

      foreach ($teamAttendances as $attendance) {

        $device = $userDevices
          ->where('user_id', '=', $attendance->user_id)
          ->first();

        if ($device == null || $attendance->attendanceLogs->count() == 0 || $attendance->isCheckedOut()) {
          continue;
        }


        $attendanceLogIds = $attendance->attendanceLogs->pluck('id');

        $isOnline = $trackingHelper->isUserOnline($device->updated_at);

        $visitsCount = Visit::whereIn('attendance_log_id', $attendanceLogIds)
          ->count();
        $ordersCount = ProductOrder::whereIn('attendance_log_id', $attendanceLogIds)
          ->count();

        $formsFilled = FormEntry::where('user_id', $attendance->user_id)
          ->whereDate('created_at', now())
          ->count();


        $cardItems[] = [
          'id' => $attendance->user->id,
          'name' => $attendance->user->getFullName(),
          'initials' => $attendance->user->getInitials(),
          'profilePicture' => $attendance->user->getProfilePicture(),
          'employeeCode' => $attendance->user->code,
          'phoneNumber' => $attendance->user->phone,
          'batteryLevel' => $device->battery_percentage,
          'isGpsOn' => $device->is_gps_on,
          'isWifiOn' => $device->is_wifi_on,
          'updatedAt' => $device->updated_at->diffForHumans(),
          'isOnline' => $isOnline,
          'teamId' => $attendance->user->team_id,
          'teamName' => $team->name,
          'attendanceInAt' => $attendance->check_in_time,
          'attendanceOutAt' => $attendance->check_out_time,
          'latitude' => $device->latitude,
          'longitude' => $device->longitude,
          'address' => $device->address,
          'visitsCount' => $visitsCount,
          'ordersCount' => $ordersCount,
          'formsFilled' => $formsFilled,
          'attendanceDuration' => $attendance->check_in_time && $attendance->check_out_time ? 
          $attendance->check_in_time->diff($attendance->check_out_time)->format('%H:%I:%S') : 'N/A',
        ];
      }

      if ($user->count() > 0) {

        $teams[] = [
          'id' => $team->id,
          'name' => $team->name,
          'totalEmployees' => $user->count(),
          'cardItems' => $cardItems,
        ];
      }
    }
    return view('tenant.dashboard.card_view', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'teams' => $teams
    ]);
  }

  public function cardViewAjax()
  {
    $teamsList = Team::where('status', '=', 'active')
      ->get();


    $attendances = Attendance::whereDate('created_at', '=', now())
      ->get();

    $trackingHelper = new TrackingHelper();

    $users = User::where('status', '=', 'active')
      ->where('team_id', '!=', null)
      ->where('shift_id', '!=', null)
      ->get();

    $userDevices = UserDevice::whereIn('user_id', $users->pluck('id'))
      ->get();

    $cardItems = [];
    foreach ($teamsList as $team) {

      $user = $users->where('team_id', '=', $team->id);

      $teamAttendances = $attendances->whereIn('user_id', $user->pluck('id'));


      foreach ($teamAttendances as $attendance) {

        $device = $userDevices
          ->where('user_id', '=', $attendance->user_id)
          ->first();

        if ($device == null) {
          continue;
        }

        $isOnline = $trackingHelper->isUserOnline($device->updated_at);

        $visitsCount = Visit::where('attendance_id', '=', $attendance->id)
          ->whereDate('created_at', '=', now())
          ->count();

        $cardItems[] = [
          'id' => $attendance->user->id,
          'name' => $attendance->user->first_name . ' ' . $attendance->user->last_name,
          'phoneNumber' => $attendance->user->phone_number,
          'batteryLevel' => $device->battery_percentage,
          'isGpsOn' => $device->is_gps_on,
          'isWifiOn' => $device->is_wifi_on,
          'updatedAt' => $device->updated_at->diffForHumans(),
          'isOnline' => $isOnline,
          'teamId' => $attendance->user->team_id,
          'teamName' => $team->name,
          'attendanceInAt' => $attendance->check_in_time,
          'attendanceOutAt' => $attendance->check_out_time ?? '',
          'latitude' => $device->latitude,
          'longitude' => $device->longitude,
          'address' => $device->address,
          'visitsCount' => $visitsCount,
        ];
      }
    }

    return response()->json($cardItems);
  }

  public function timelineView()
  {
    $employees = User::where('status', UserAccountStatus::ACTIVE)
      ->get();

    return view('tenant.dashboard.timeline_view', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'employees' => $employees
    ]);
  }

  public function getDeviceLocationAjax($userId, $date, $attendanceLogId = null)
  {
    $logs = DeviceStatusLog::query()
      ->where('user_id', $userId)
      ->whereDate('created_at', $date)
      ->orderBy('created_at', 'asc');

    if ($attendanceLogId && $attendanceLogId != 'null') {
      $attendanceLog = AttendanceLog::find($attendanceLogId);
      $nextCheckOutLog = AttendanceLog::where('attendance_id', $attendanceLog->attendance_id)
        ->where('created_at', '>', $attendanceLog->created_at)
        ->where('type', 'check_out')
        ->first();

      if (!$attendanceLog) {
        return Error::response('Attendance log not found');
      }

      if ($nextCheckOutLog) {
        $logs = $logs->where('created_at', '>=', $attendanceLog->created_at)
          ->where('created_at', '<=', $nextCheckOutLog->created_at);
      }
      else {
        $logs->where('created_at', '>=', $attendanceLog->created_at);
      }
    }

    $logs = $logs->get();

    $trackingHelper = new TrackingHelper();

    $filteredLogs = $trackingHelper->getFilteredLocationPoints($logs);

    $response = [];

    foreach ($filteredLogs['filteredPoints'] as $log) {
      $response[] = [
        'latitude' => $log->latitude,
        'longitude' => $log->longitude,
        'address' => $log->address,
        'created_at' => $log->created_at->format(AppConstants::TimeFormat),
      ];
    }

    $result = [
      'logs' => $response,
      'rawLogs' => $logs,
      'totalTravelledDistance' => $filteredLogs['totalTravelledDistance'],
      'averageTravelledSpeed' => $filteredLogs['averageTravelledSpeed'],
    ];

    return Success::response($result);
  }

  public function getAttendanceLogAjax($userId, $date)
  {
    $attendance = Attendance::where('user_id', $userId)
      ->whereDate('created_at', $date)
      ->first();

    if (!$attendance) {
      return Success::response([]);
    }

    $attendanceLogs = AttendanceLog::where('attendance_id', $attendance->id)
      ->where('type', 'check_in')
      ->orderBy('created_at', 'asc')
      ->get();

    $attendanceLogs = $attendanceLogs->map(function ($log) {
      return [
      'id' => $log->id,
      'latitude' => $log->latitude,
      'longitude' => $log->longitude,
      'address' => $log->address,
      'created_at' => $log->created_at->format(AppConstants::TimeFormat),
      ];
    });

    return Success::response($attendanceLogs);
  }

  public function getActivityAjax($userId, $date, $attendanceLogId = null)
  {
    $employeeId = $userId;

    $trackingHelper = new TrackingHelper();

    $attendance = Attendance::where('user_id', $employeeId)
      ->whereDate('created_at', $date)
      ->first();

    if (!$attendance) {
      return Success::response([]);
    }

    $activities = [];
    if ($attendanceLogId && $attendanceLogId != 'null') {
      $attendanceLog = AttendanceLog::find($attendanceLogId);
      if (!$attendanceLog) {
        return Success::response([]);
      }

      $nextCheckOutLog = AttendanceLog::where('attendance_id', $attendance->id)
        ->where('created_at', '>', $attendanceLog->created_at)
        ->where('type', 'check_out')
        ->first();

      if (!$nextCheckOutLog) {
        $activities = Activity::where('created_at', '>=', $attendanceLog->created_at)
          ->where('created_by_id', $employeeId)
          ->get();
      }
      else {
        //Filter activities from this log to next log created_at
        $activities = Activity::where('created_at', '>=', $attendanceLog->created_at)
          ->where('created_at', '<=', $nextCheckOutLog->created_at)
          ->where('created_by_id', $employeeId)
          ->get();
      }
    }
    else {

      $activities = Activity::whereDate('created_at', $date)
        ->where('created_by_id', $employeeId)
        ->get();
    }


    if ($activities->count() == 0) {
      return Success::response([]);
    }

    //$activities = $activities->where('accuracy', '>', 20)->toArray();

    //return Success::response($activities);

    $filteredTrackings = $trackingHelper->getFilteredDataV2($activities);

    $timeLineItems = [];

    for ($i = 0; $i < count($filteredTrackings); $i++) {

      $elapseTime = "0";

      $tracking = $filteredTrackings[$i];
      $nextTracking = null;
      if ($tracking->type == 'checked_in') {
        if ($i < count($filteredTrackings) - 1 && count($filteredTrackings) != 1) {
          $nextTracking = $filteredTrackings[$i + 1];
          $elapseTime = $tracking->created_at->diff($nextTracking->created_at)->format('%H:%I:%S');
        }
        else {
          $elapseTime = '0';
        }
        $timeLineItems[] = [
          'id' => $tracking->id,
          'type' => 'checkIn',
          'accuracy' => $tracking->accuracy,
          'activity' => $tracking->activity,
          'batteryPercentage' => $tracking->battery_percentage,
          'isGPSOn' => $tracking->is_gps_on,
          'isWifiOn' => $tracking->is_wifi_on,
          'latitude' => $tracking->latitude,
          'longitude' => $tracking->longitude,
          'address' => $tracking->address,
          'signalStrength' => $tracking->signal_strength,
          'trackingType' => $tracking->type,
          'startTime' => $tracking->created_at->format('h:i A'),
          'endTime' => $nextTracking != null ? $nextTracking->created_at->format('h:i A') : $tracking->created_at->format('h:i A'),
          'elapseTime' => $elapseTime,
        ];
        continue;
      }

      if ($tracking->type == 'checked_out') {
        $elapseTime = $tracking->created_at->format('%H:%I:%S');

        $timeLineItems[] = [
          'id' => $tracking->id,
          'type' => 'checkOut',
          'accuracy' => $tracking->accuracy,
          'activity' => $tracking->activity,
          'batteryPercentage' => $tracking->battery_percentage,
          'isGPSOn' => $tracking->is_gps_on,
          'isWifiOn' => $tracking->is_wifi_on,
          'latitude' => $tracking->latitude,
          'longitude' => $tracking->longitude,
          'address' => $tracking->address,
          'signalStrength' => $tracking->signal_strength,
          'trackingType' => $tracking->type,
          'startTime' => $elapseTime,
          'endTime' => $tracking->created_at->format('h:i A'),
          'elapseTime' => $elapseTime,
        ];
        continue;
      }

      $nextTracking = null;

      if ($i + 1 < count($filteredTrackings)) {
        $nextTracking = $filteredTrackings[$i + 1];
        $elapseTime = $tracking->created_at->diff($nextTracking->created_at)->format('%H:%I:%S');
      }
      else {
        $elapseTime = $tracking->created_at->format('%H:%I:%S');
      }

      switch ($tracking->activity) {
        case 'ActivityType.STILL':
          $timeLineItems[] = [
            'id' => $tracking->id,
            'type' => 'still',
            'accuracy' => $tracking->accuracy ?? 0,
            'activity' => $tracking->activity,
            'batteryPercentage' => $tracking->battery_percentage,
            'isGPSOn' => $tracking->is_gps_on,
            'isWifiOn' => $tracking->is_wifi_on,
            'latitude' => $tracking->latitude,
            'longitude' => $tracking->longitude,
            'address' => $tracking->address,
            'signalStrength' => $tracking->signal_strength,
            'trackingType' => $tracking->type,
            'startTime' => $tracking->created_at->format('h:i A'),
            'endTime' => $nextTracking != null ? $nextTracking->created_at->format('h:i A') : $tracking->created_at->format('h:i A'),
            'elapseTime' => $elapseTime,
          ];
          break;
        case 'ActivityType.WALKING':
          $timeLineItems[] = [
            'id' => $tracking->id,
            'type' => 'walk',
            'accuracy' => $tracking->accuracy ?? 0,
            'activity' => $tracking->activity,
            'batteryPercentage' => $tracking->battery_percentage,
            'isGPSOn' => $tracking->is_gps_on,
            'isWifiOn' => $tracking->is_wifi_on,
            'latitude' => $tracking->latitude,
            'longitude' => $tracking->longitude,
            'address' => $tracking->address,
            'signalStrength' => $tracking->signal_strength,
            'trackingType' => $tracking->type,
            'startTime' => $tracking->created_at->format('h:i A'),
            'endTime' => $nextTracking ? $nextTracking->created_at->format('h:i A') : $tracking->created_at->format('h:i A'),
            'elapseTime' => $elapseTime,
          ];
          break;
        default:

          $distance = 0;
          if ($i + 1 < count($filteredTrackings)) {
            $nextTracking = $filteredTrackings[$i + 1];
          }


          $timeLineItems[] = [
            'id' => $tracking->id,
            'type' => 'vehicle',
            'accuracy' => $tracking->accuracy ?? 0,
            'activity' => $tracking->activity,
            'batteryPercentage' => $tracking->battery_percentage,
            'isGPSOn' => $tracking->is_gps_on,
            'isWifiOn' => $tracking->is_wifi_on,
            'latitude' => $tracking->latitude,
            'longitude' => $tracking->longitude,
            'address' => $tracking->address,
            'signalStrength' => $tracking->signal_strength,
            'trackingType' => $tracking->type,
            'startTime' => $tracking->created_at->format('h:i A'),
            'endTime' => $nextTracking ? $nextTracking->created_at->format('h:i A') : $tracking->created_at->format('h:i A'),
            'elapseTime' => $elapseTime,
            'distance' => $distance,
          ];
          break;
      }
    }


    return Success::response($timeLineItems);
  }

  public function getStatsForTimeLineAjax($userId, $date, $attendanceLogId = null)
  {
    $attendance = Attendance::where('user_id', $userId)
      ->whereDate('created_at', $date)
      ->with('attendanceLogs')
      ->first();

    if (!$attendance) {
      return Error::response([]);
    }

    $attendanceLogIds = $attendance->attendanceLogs->pluck('id');


    if ($attendanceLogId && $attendanceLogId != 'null') {
      $attendanceLogIds = [$attendanceLogId];
    }

    $visits = Visit::whereIn('attendance_log_id', $attendanceLogIds)
      ->get();

    $visits = $visits->map(function ($visit) {
      return [
      'id' => $visit->id,
      'latitude' => $visit->latitude,
      'longitude' => $visit->longitude,
      'address' => $visit->address,
      'img_url' => asset('storage/' . AppConstants::BaseFolderVisitImages . $visit->img_url),
      'created_at' => $visit->created_at->format(AppConstants::TimeFormat),
      'client_name' => $visit->client->name,
      ];
    });

    $breaks = AttendanceBreak::whereIn('attendance_log_id', $attendanceLogIds)
      ->get();

    $breaks = $breaks->map(function ($break) {
      return [
      'id' => $break->id,
      'start_time' => $break->start_time->format(AppConstants::TimeFormat),
      'end_time' => $break->end_time ? $break->end_time->format(AppConstants::TimeFormat) : null,
      'duration' => $break->end_time ? $break->start_time->diff($break->end_time)->format('%H:%I:%S') : null,
      'created_at' => $break->created_at->format(AppConstants::TimeFormat),
      ];
    });

    $orders = ProductOrder::whereIn('attendance_log_id', $attendanceLogIds)
      ->get();

    $orders = $orders->map(function ($order) {
      return [
      'id' => $order->id,
      'order_number' => $order->order_no,
      'total_amount' => $order->total,
      'status' => $order->status,
      'total_items' => $order->orderLines->count(),
      'created_at' => $order->created_at->format(AppConstants::TimeFormat),
      'user_remarks' => $order->user_remarks,
      ];
    });

    return Success::response([
      'userId' => $userId,
      'date' => $date,
      'attendanceDuration' => $attendance->check_in_time && $attendance->check_out_time ? 
      $attendance->check_in_time->diff($attendance->check_out_time)->format('%H:%I:%S') : 'N/A',
      'attendanceLogId' => $attendanceLogId,
      'name' => $attendance->user->getFullName(),
      'code' => $attendance->user->code,
      'designation' => $attendance->user->designation ? $attendance->user->designation->name : 'N/A',
      'visits' => $visits,
      'breaks' => $breaks,
      'orders' => $orders,
      'visitsCount' => $visits->count(),
      'breaksCount' => $breaks->count(),
      'ordersCount' => $orders->count(),
    ]);
  }

  public function getDepartmentPerformanceAjax()
  {
    $departments = Department::where('status', Status::ACTIVE)
      ->with('designations')
      ->with('designations.users')
      ->get();

    $departmentPerformance = [];

    foreach ($departments as $department) {
      $departmentPerformance[] = [
        'id' => $department->id,
        'name' => $department->name,
        'code' => $department->code,
        'totalEmployees' => $department->designations->sum(function ($designation) {
        return $designation->users->count();
      }),
        'totalPresentEmployees' => $department->designations->sum(function ($designation) {
        return $designation->users->sum(function ($user) {
            return Attendance::where('user_id', $user->id)->whereDate('created_at', now())->count();
          }
          );
        }),
        'totalAbsentEmployees' => $department->designations->sum(function ($designation) {
        return $designation->users->count() - $designation->users->sum(function ($user) {
            return Attendance::where('user_id', $user->id)->whereDate('created_at', now())->count();
          }
          );
        }),
      ];
    }

    return Success::response($departmentPerformance);
  }

}
