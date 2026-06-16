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
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
  public function index()
  {
    // Check if user is HR and return HR dashboard directly
    $user = auth()->user();
    $todayVisits = $user ? $user->todayVisits()->get() : collect();
    $isManager = $user ? $user->hasRole('manager') : false;

    // If user has 'employee' role, always show employee dashboard —
    // even if they were accidentally assigned 'accounts' role via bulk department assignment.
    // Only pure accounts/admin/hr role holders (with no employee role) get the admin dashboard.
    $isEmployee = $user && $user->hasRole('employee');

    if (!$isEmployee && $user && ($user->hasRole(['admin', 'hr', 'accounts']) || $isManager)) {
        // admin / hr / accounts (non-employee) get the full strategic dashboard
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

      // 1. Hiring Trends (Last 12 Months) — cached 1 hour (historical data, rarely changes)
      $tenantId = auth()->user()->tenant_id ?? 'shared';
      $twelveMonthsAgo = Carbon::now()->subMonths(11)->startOfMonth();

      $hiringTrend = Cache::remember("dashboard.hiring_trend.{$tenantId}", 3600, function () use ($twelveMonthsAgo) {
          $hiresByMonth = User::where('date_of_joining', '>=', $twelveMonthsAgo)
              ->selectRaw("DATE_FORMAT(date_of_joining, '%M %Y') as month, count(*) as count")
              ->groupBy('month')
              ->pluck('count', 'month');

          $attritionByMonth = User::where('relieved_at', '>=', $twelveMonthsAgo)
              ->selectRaw("DATE_FORMAT(relieved_at, '%M %Y') as month, count(*) as count")
              ->groupBy('month')
              ->pluck('count', 'month');

          $trend = ['labels' => [], 'hires' => [], 'attrition' => []];
          for ($i = 11; $i >= 0; $i--) {
              $monthLabel = Carbon::now()->subMonths($i)->format('F Y');
              $trend['labels'][]    = Carbon::now()->subMonths($i)->format('M Y');
              $trend['hires'][]     = $hiresByMonth->get($monthLabel, 0);
              $trend['attrition'][] = $attritionByMonth->get($monthLabel, 0);
          }
          return $trend;
      });

      // 2. Department Distribution — cached 1 hour (changes only when employees move departments)
      $departmentData = Cache::remember("dashboard.dept_distribution.{$tenantId}", 3600, function () {
          return Department::select('departments.id', 'departments.name')
              ->selectRaw('COUNT(users.id) as users_count')
              ->join('users', function ($join) {
                  $join->on('users.department_id', '=', 'departments.id')
                       ->where('users.status', '=', UserAccountStatus::ACTIVE->value);
              })
              ->groupBy('departments.id', 'departments.name')
              ->orderByDesc('users_count')
              ->take(10)
              ->get()
              ->map(fn($dept) => ['name' => $dept->name, 'count' => $dept->users_count]);
      });

      // 3. Announcements
      $announcements = Announcement::where('is_active', true)
        ->where('start_date', '<=', now())
        ->latest()
        ->take(5)
        ->get();

      // 4. Recruitment Pipeline (Top Candidates) — only needed columns, cached 15 min
      [$topCandidates, $jobStages, $activeJobsCount, $activeJobs, $recentActivities] =
          Cache::remember("dashboard.recruitment.{$tenantId}", 900, function () {
              $topCandidates = JobApplication::with(['jobs:id,title', 'stage:id,title,order'])
                  ->latest()->take(3)->get();

              $jobStages = JobStage::select('id', 'title', 'order')->orderBy('order')->get();

              // 5. Active Job Openings
              $activeJobsCount = Job::where('status', 'active')->count();
              $activeJobs = Job::select('id', 'title', 'status', 'created_at')
                  ->where('status', 'active')
                  ->withCount('applications')
                  ->latest()->take(4)->get();

              // 6. Recent Applicant Activity
              $recentActivities = JobApplication::with('jobs:id,title')
                  ->latest()->take(6)->get();

              return [$topCandidates, $jobStages, $activeJobsCount, $activeJobs, $recentActivities];
          });

      $newApplicantsToday = JobApplication::whereDate('created_at', now())->count();

      // 7. Celebrations — cached 1 hour keyed to today's date (changes at midnight only)
      $todayMd = now()->format('md');
      $todayDate = now()->toDateString();

      [$upcomingBirthdays, $upcomingAnniversaries] =
          Cache::remember("dashboard.celebrations.{$tenantId}.{$todayDate}", 3600, function () use ($todayMd) {
              $birthdays = User::whereNotNull('dob')
                  ->orderByRaw("CASE WHEN DATE_FORMAT(dob, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
                  ->orderByRaw("DATE_FORMAT(dob, '%m%d') ASC")
                  ->take(15)->get()->unique('id')
                  ->map(function ($u) use ($todayMd) {
                      $u->is_today = (Carbon::parse($u->dob)->format('md') === $todayMd);
                      return $u;
                  });

              $anniversaries = User::whereNotNull('date_of_joining')
                  ->orderByRaw("CASE WHEN DATE_FORMAT(date_of_joining, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
                  ->orderByRaw("DATE_FORMAT(date_of_joining, '%m%d') ASC")
                  ->take(15)->get()->unique('id')
                  ->map(function ($u) use ($todayMd) {
                      $u->is_today = (Carbon::parse($u->date_of_joining)->format('md') === $todayMd);
                      return $u;
                  });

              return [$birthdays, $anniversaries];
          });

        $todayBirthdays           = $upcomingBirthdays->filter(fn($u) => $u->is_today);

        // Add is_wished flag for today's birthdays
        if ($todayBirthdays->isNotEmpty()) {
            $alreadyWishedUserIds = \Illuminate\Support\Facades\DB::table('notifications')
                ->where('type', \App\Notifications\BirthdayWishNotification::class)
                ->whereJsonContains('data->sender_id', auth()->id())
                ->whereYear('created_at', now()->year)
                ->pluck('notifiable_id')
                ->toArray();
                
            $todayBirthdays = $todayBirthdays->map(function ($u) use ($alreadyWishedUserIds) {
                $u->is_wished = in_array($u->id, $alreadyWishedUserIds);
                return $u;
            });
        }

        $upcomingBirthdaysFiltered = $upcomingBirthdays->filter(fn($u) => !$u->is_today)->take(2);
      $todayAnniversaries           = $upcomingAnniversaries->filter(fn($u) => $u->is_today);
      $upcomingAnniversariesFiltered = $upcomingAnniversaries->filter(fn($u) => !$u->is_today)->take(2);

      // 8. Upcoming Probation Ends — cached 30 min
      $upcomingProbationEnds = Cache::remember("dashboard.probation.{$tenantId}", 1800, function () {
          return User::where('status', UserAccountStatus::ACTIVE)
              ->whereNotNull('probation_end_date')
              ->whereNull('probation_confirmed_at')
              ->where('probation_end_date', '<=', now()->addDays(30)->toDateString())
              ->with('reportingTo:id,first_name,last_name')
              ->orderBy('probation_end_date')
              ->get();
      });

      // Extra Stats for Suggestions
      $absentCount = max(0, $active - $presentUsersCount - $onLeaveUsersCount);
      $newHiresThisMonth = User::whereMonth('date_of_joining', now()->month)->whereYear('date_of_joining', now()->year)->count();

      // 9. Pending Approvals — fetched fresh (5-min cache so approvals feel responsive)
      $pendingApprovals = Cache::remember("dashboard.pending_approvals.{$tenantId}", 300, function () {
          $list = collect();

          // Fetch all four approval types in one go each (no .each() — avoids chunked N+1)
          $leavesPending = LeaveRequest::where('status', 'pending')
              ->with(['user.designation.department', 'leaveType'])
              ->get();
          foreach ($leavesPending as $r) {
              $days = 1;
              if ($r->from_date && $r->to_date) {
                  try { $days = Carbon::parse($r->from_date)->diffInDays(Carbon::parse($r->to_date)) + 1; }
                  catch (\Exception $e) { $days = 1; }
              }
              $list->push([
                  'type'       => 'Leave',
                  'user'       => $r->user?->name ?? 'N/A',
                  'emp_id'     => $r->user?->code ?? 'N/A',
                  'department' => $r->user?->designation?->department?->name ?? 'HR',
                  'avatar'     => $r->user ? $r->user->getProfilePicture() : 'https://ui-avatars.com/api/?name=' . urlencode($r->user?->name ?? 'User') . '&background=004D4D&color=fff',
                  'date'       => $r->from_date ? Carbon::parse($r->from_date)->format('M d') : 'N/A',
                  'raw_date'   => $r->from_date ? Carbon::parse($r->from_date) : now(),
                  'time_ago'   => $r->created_at ? $r->created_at->diffForHumans() : 'Recently',
                  'details'    => (optional($r->leaveType)->name ?? 'Request') . ' (' . $days . ' days)',
                  'days'       => $days,
                  'id'         => $r->id,
              ]);
          }

          $expensesPending = ExpenseRequest::where('status', 'pending')
              ->with(['user.designation.department', 'expenseType'])
              ->get();
          foreach ($expensesPending as $r) {
              $list->push([
                  'type'       => 'Expense',
                  'user'       => $r->user?->name ?? 'N/A',
                  'emp_id'     => $r->user?->code ?? 'N/A',
                  'department' => $r->user?->designation?->department?->name ?? 'Finance',
                  'avatar'     => $r->user ? $r->user->getProfilePicture() : 'https://ui-avatars.com/api/?name=' . urlencode($r->user?->name ?? 'User') . '&background=004D4D&color=fff',
                  'date'       => Carbon::parse($r->created_at)->format('M d'),
                  'raw_date'   => Carbon::parse($r->created_at),
                  'time_ago'   => $r->created_at->diffForHumans(),
                  'details'    => 'Amount: ' . number_format($r->amount ?? 0, 2),
                  'id'         => $r->id,
              ]);
          }

          $docsPending = DocumentRequest::where('status', 'pending')
              ->with(['user.designation.department', 'documentType'])
              ->get();
          foreach ($docsPending as $r) {
              $list->push([
                  'type'       => 'Document',
                  'user'       => $r->user?->name ?? 'N/A',
                  'emp_id'     => $r->user?->code ?? 'N/A',
                  'department' => $r->user?->designation?->department?->name ?? 'Admin',
                  'avatar'     => $r->user ? $r->user->getProfilePicture() : 'https://ui-avatars.com/api/?name=' . urlencode($r->user?->name ?? 'User') . '&background=004D4D&color=fff',
                  'date'       => Carbon::parse($r->created_at)->format('M d'),
                  'raw_date'   => Carbon::parse($r->created_at),
                  'time_ago'   => $r->created_at->diffForHumans(),
                  'details'    => 'Req: ' . (optional($r->documentType)->name ?? 'Document'),
                  'id'         => $r->id,
              ]);
          }

          $loansPending = LoanRequest::where('status', 'pending')
              ->with(['user.designation.department'])
              ->get();
          foreach ($loansPending as $r) {
              $list->push([
                  'type'       => 'Loan',
                  'user'       => $r->user?->name ?? 'N/A',
                  'emp_id'     => $r->user?->code ?? 'N/A',
                  'department' => $r->user?->designation?->department?->name ?? 'Operations',
                  'avatar'     => $r->user ? $r->user->getProfilePicture() : 'https://ui-avatars.com/api/?name=' . urlencode($r->user?->name ?? 'User') . '&background=004D4D&color=fff',
                  'date'       => Carbon::parse($r->created_at)->format('M d'),
                  'raw_date'   => Carbon::parse($r->created_at),
                  'time_ago'   => $r->created_at->diffForHumans(),
                  'details'    => 'Amt: ' . number_format($r->amount ?? 0, 2),
                  'id'         => $r->id,
              ]);
          }

          return $list->sortByDesc('raw_date');
      });

      // Improved Trends Calculation
      $yesterday = now()->subDay()->toDateString();
      $yesterdayPresent = Attendance::whereDate('check_in_time', $yesterday)->count();
      $presentTrendValue = $yesterdayPresent > 0 ? (($presentUsersCount - $yesterdayPresent) / $yesterdayPresent) * 100 : ($presentUsersCount > 0 ? 100 : 0);
      
      $yesterdayLeaves = LeaveRequest::whereDate('from_date', '<=', $yesterday)
        ->whereDate('to_date', '>=', $yesterday)
        ->where('status', \App\Enums\LeaveRequestStatus::APPROVED)
        ->count();
      $leavesDiff = $onLeaveUsersCount - $yesterdayLeaves;

      $newJobsThisWeek = Job::where('created_at', '>=', now()->subDays(7))->count();

      $trends = [
        'totalStaff' => [
          'value' => ($newHiresThisMonth > 0 ? '+' : '') . $newHiresThisMonth . ' New', 
          'isUp' => $newHiresThisMonth >= 0
        ],
        'present' => [
          'value' => ($presentTrendValue >= 0 ? '+' : '') . round($presentTrendValue, 1) . '%', 
          'isUp' => $presentTrendValue >= 0
        ],
        'leaves' => [
          'value' => ($leavesDiff >= 0 ? '+' : '') . abs($leavesDiff) . ($leavesDiff >= 0 ? ' More' : ' Less'), 
          'isUp' => $leavesDiff <= 0 
        ],
        'openings' => [
          'value' => ($newJobsThisWeek > 0 ? '+' : '') . $newJobsThisWeek . ' New', 
          'isUp' => true
        ]
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

        $teamPendingLeaveRequests = LeaveRequest::whereIn('user_id', $teamMemberIds)
            ->where('status', 'pending')
            ->with(['user', 'leaveType'])
            ->latest()
            ->take(5)
            ->get();

        return view('tenant.users.dashboard.manager-index', [
            'todayVisits' => $todayVisits,
            'totalUser' => $totalUser,
            'activeEmployees' => $active,
            'active' => $active,
            'presentUsersCount' => $presentUsersCount,
            'todayPresentUsers' => $presentUsersCount,
            'todayOnLeaveCount' => $onLeaveUsersCount,
            'todayAbsentUsers' => $todayAbsentUsers,
            'pendingLeaveRequests' => $pendingLeaveRequests,
            'teamPendingLeaveRequests' => $teamPendingLeaveRequests,
            'pendingExpenseRequests' => 0,
            'pendingDocumentRequests' => DocumentRequest::whereIn('user_id', $teamMemberIds)->where('status', 'pending')->count(),
            'pendingLoanRequests' => LoanRequest::whereIn('user_id', $teamMemberIds)->where('status', 'pending')->count(),
            'teamOutToday' => $teamOutToday,
            'todayBirthdays' => $todayBirthdays,
            'upcomingBirthdays' => $upcomingBirthdaysFiltered,
            'todayAnniversaries' => $todayAnniversaries,
            'upcomingAnniversaries' => $upcomingAnniversariesFiltered,
            'orgBirthdays' => $todayBirthdays->merge($upcomingBirthdaysFiltered), // Legacy fallback
            'orgAnniversaries' => $todayAnniversaries->merge($upcomingAnniversariesFiltered), // Legacy fallback
            'recentNotices' => $announcements, // Reuse announcements as notices
            'trends' => $trends,
            'myLeavesCount' => 0,
            'myExpensesCount' => 0,
            'mySOSCount' => 0,
            'nextHoliday' => Holiday::where('date', '>=', now())->orderBy('date')->first(),
            'payrollTrend' => 0,
            'latestNetSalary' => 0,
            'departments' => \App\Models\Department::withoutGlobalScopes()->where('status', \App\Enums\Status::ACTIVE)->get(),
            'roles' => \Spatie\Permission\Models\Role::get(),
            'designations' => \App\Models\Designation::withoutGlobalScopes()->where('status', 'active')->get(),
            'managers' => \App\Models\User::withoutGlobalScopes()->whereHas('roles', function($q) {
                $q->whereIn('name', ['admin', 'hr', 'manager', 'accounts']);
            })->where('status', UserAccountStatus::ACTIVE)->get()
        ]);
      }


      $roles = \Spatie\Permission\Models\Role::get();
      $departments  = Department::withoutGlobalScopes()->where('status', Status::ACTIVE)->get();
      $teams        = Team::withoutGlobalScopes()->where('status', Status::ACTIVE)->get();
      $designations = \App\Models\Designation::withoutGlobalScopes()->where('status', 'active')->get();
      $managers     = User::withoutGlobalScopes()->whereHas('roles', function($q) {
          $q->whereIn('name', ['admin', 'hr', 'manager', 'accounts']);
      })->where('status', UserAccountStatus::ACTIVE)->get();

      // Fallback: If no data found, try without status filter
      if ($departments->isEmpty()) {
          $departments = Department::withoutGlobalScopes()->get();
      }
      if ($designations->isEmpty()) {
          $designations = \App\Models\Designation::withoutGlobalScopes()->get();
      }
      if ($managers->isEmpty()) {
          $managers = User::withoutGlobalScopes()->where('status', UserAccountStatus::ACTIVE)->get();
      }

      // Return HR dashboard view directly
      return view('tenant.users.dashboard.hr-index', [
        'todayVisits' => $todayVisits,
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
        'roles' => $roles,
        'departments' => $departments,
        'teams' => $teams,
        'designations' => $designations,
        'managers' => $managers,
        'myLeavesCount' => 0,
        'myExpensesCount' => 0,
        'mySOSCount' => 0,
        'nextHoliday' => Holiday::where('date', '>=', now())->orderBy('date')->first(),
        'recentNotices' => collect(),
        'payrollTrend' => 0,
        'latestNetSalary' => 0
      ]);
    }

    // All non-admin/non-hr/non-manager users → hand off to UserDashboardController
    // which properly routes: employee role → employee-index (personal data only)
    return app(\App\Http\Controllers\tenant\users\UserDashboardController::class)->index();
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

      $todayAttendances = Attendance::with(['user.userDevice', 'user.designation'])
        ->whereDate('created_at', today())
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
    
    $allUserIds = $users->pluck('id')->toArray();
    $allAttendanceLogIds = $attendances->flatMap->attendanceLogs->pluck('id')->toArray();

    $visitCounts = empty($allAttendanceLogIds) ? collect() : Visit::whereIn('attendance_log_id', $allAttendanceLogIds)
        ->selectRaw('attendance_log_id, count(*) as count')
        ->groupBy('attendance_log_id')
        ->pluck('count', 'attendance_log_id');

    $orderCounts = empty($allAttendanceLogIds) ? collect() : ProductOrder::whereIn('attendance_log_id', $allAttendanceLogIds)
        ->selectRaw('attendance_log_id, count(*) as count')
        ->groupBy('attendance_log_id')
        ->pluck('count', 'attendance_log_id');

    $formCounts = empty($allUserIds) ? collect() : FormEntry::whereIn('user_id', $allUserIds)
        ->whereDate('created_at', now())
        ->selectRaw('user_id, count(*) as count')
        ->groupBy('user_id')
        ->pluck('count', 'user_id');

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

        $visitsCount = 0;
        $ordersCount = 0;
        foreach($attendanceLogIds as $alId) {
            $visitsCount += $visitCounts->get($alId, 0);
            $ordersCount += $orderCounts->get($alId, 0);
        }

        $formsFilled = $formCounts->get($attendance->user_id, 0);


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

    $allAttendanceIds = $attendances->pluck('id')->toArray();
    $visitCountsAjax = empty($allAttendanceIds) ? collect() : Visit::whereIn('attendance_id', $allAttendanceIds)
        ->whereDate('created_at', '=', now())
        ->selectRaw('attendance_id, count(*) as count')
        ->groupBy('attendance_id')
        ->pluck('count', 'attendance_id');

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

        $visitsCount = $visitCountsAjax->get($attendance->id, 0);

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
    $employees = User::where('status', UserAccountStatus::ACTIVE)->get();

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
    // Two bulk queries instead of one query per user (eliminates N×M×K N+1 problem)
    $totalByDept = User::where('status', UserAccountStatus::ACTIVE)
        ->select('department_id', DB::raw('count(*) as total'))
        ->whereNotNull('department_id')
        ->groupBy('department_id')
        ->pluck('total', 'department_id');

    $presentByDept = Attendance::whereDate('check_in_time', today())
        ->join('users', 'attendances.user_id', '=', 'users.id')
        ->select('users.department_id', DB::raw('count(*) as present_count'))
        ->whereNotNull('users.department_id')
        ->groupBy('users.department_id')
        ->pluck('present_count', 'department_id');

    $departments = Department::where('status', Status::ACTIVE)
        ->select('id', 'name', 'code')
        ->get();

    $departmentPerformance = $departments->map(function ($dept) use ($totalByDept, $presentByDept) {
        $total   = $totalByDept->get($dept->id, 0);
        $present = $presentByDept->get($dept->id, 0);
        return [
            'id'                     => $dept->id,
            'name'                   => $dept->name,
            'code'                   => $dept->code,
            'totalEmployees'         => $total,
            'totalPresentEmployees'  => $present,
            'totalAbsentEmployees'   => max(0, $total - $present),
        ];
    })->values();

    return Success::response($departmentPerformance);
  }

}
