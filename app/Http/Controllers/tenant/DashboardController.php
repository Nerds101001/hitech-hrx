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
use App\Models\Payslip;
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
      // Calculate base HR stats.
      //
      // Three long-standing metric bugs are corrected here, because the cards,
      // the 7-day chart and the insights line each used to count differently
      // and therefore disagreed with each other on the same screen:
      //   • "Total Employees" counted every user ever created, including
      //     terminated staff — now excludes them.
      //   • "Present" counted only status='present', while the chart counted
      //     present + late + WFH. One definition now, used everywhere: anyone
      //     who is at work in any form, deduplicated per user.
      //   • "On Leave" only matched leaves STARTING today, so anyone in the
      //     middle of a multi-day leave vanished — now matches leaves that
      //     SPAN today.
      //   • "Absent" was active − present, which silently counted people on
      //     approved leave as absent — leave is now subtracted.
      $totalUser = User::notTerminated()->count();
      $active = User::where('status', UserAccountStatus::ACTIVE)->count();

      // Tallied through the same AttendanceDayStatus resolver as every other
      // attendance surface, instead of matching raw status text - that raw
      // match counted an uncorrected miss-punch as present (its raw status
      // defaults to 'Present' with no check-out recorded) and never
      // recognised a worked designated off-Saturday/holiday as present at
      // all, since its raw status never says 'present'.
      $presentUsersCount = $this->countPresentToday(null);

      $onLeaveUsersCount = LeaveRequest::whereDate('from_date', '<=', now())
        ->whereDate('to_date', '>=', now())
        ->where('status', LeaveRequestStatus::APPROVED)
        ->distinct('user_id')
        ->count('user_id');

      $todayAbsentUsers = max(0, $active - $presentUsersCount - $onLeaveUsersCount);

      // Outdoor duty is approved on the same Leave page, so it counts as pending leave.
      $pendingLeaveRequests = LeaveRequest::where('status', 'pending')->count()
        + \App\Models\OutdoorDuty::where('status', 'pending')->count();
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
              $birthdays = User::notTerminated()->whereNotNull('dob')
                  ->orderByRaw("CASE WHEN DATE_FORMAT(dob, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
                  ->orderByRaw("DATE_FORMAT(dob, '%m%d') ASC")
                  ->take(15)->get()->unique('id')
                  ->map(function ($u) use ($todayMd) {
                      $u->is_today = (Carbon::parse($u->dob)->format('md') === $todayMd);
                      return $u;
                  });

              $anniversaries = User::notTerminated()->whereNotNull('date_of_joining')
                  ->orderByRaw("CASE WHEN DATE_FORMAT(date_of_joining, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
                  ->orderByRaw("DATE_FORMAT(date_of_joining, '%m%d') ASC")
                  ->take(15)->get()->unique('id')
                  ->map(function ($u) use ($todayMd) {
                      $u->is_today = (Carbon::parse($u->date_of_joining)->format('md') === $todayMd);
                      return $u;
                  });

              return [$birthdays, $anniversaries];
          });

        // Add is_wished flag for today's birthdays
        $alreadyWishedUserIds = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('type', \App\Notifications\BirthdayWishNotification::class)
            ->whereJsonContains('data->sender_id', auth()->id())
            ->whereYear('created_at', now()->year)
            ->pluck('notifiable_id')
            ->toArray();
            
        foreach ($upcomingBirthdays as $u) {
            $u->is_wished = in_array($u->id, $alreadyWishedUserIds);
        }

        // Add is_wished flag for today's anniversaries
        $alreadyWishedAnniversaryIds = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('type', \App\Notifications\WorkAnniversaryWishNotification::class)
            ->whereJsonContains('data->sender_id', auth()->id())
            ->whereYear('created_at', now()->year)
            ->pluck('notifiable_id')
            ->toArray();
            
        foreach ($upcomingAnniversaries as $u) {
            $u->is_wished = in_array($u->id, $alreadyWishedAnniversaryIds);
        }

        $todayBirthdays = $upcomingBirthdays->filter(fn($u) => $u->is_today);
        $upcomingBirthdaysFiltered = $upcomingBirthdays->filter(fn($u) => !$u->is_today)->take(2);
        $todayAnniversaries = $upcomingAnniversaries->filter(fn($u) => $u->is_today);
        $upcomingAnniversariesFiltered = $upcomingAnniversaries->filter(fn($u) => !$u->is_today)->take(2);

      // 8. Probation: overdue confirmations + genuinely upcoming ends.
      // This query used to have NO lower bound, so every employee whose
      // probation lapsed years ago and was never confirmed was reported as
      // "ending within 30 days" — in practice all 30 entries were overdue,
      // the oldest from 2013. Overdue cases are real work, so they are still
      // surfaced, but ordered first and counted separately so the widget can
      // label them honestly instead of calling a 2013 date "upcoming".
      $probationCutoff = now()->addDays(30)->toDateString();
      $upcomingProbationEnds = Cache::remember("dashboard.probation.{$tenantId}", 1800, function () use ($probationCutoff) {
          return User::where('status', UserAccountStatus::ACTIVE)
              ->whereNotNull('probation_end_date')
              ->whereNull('probation_confirmed_at')
              ->where('probation_end_date', '<=', $probationCutoff)
              ->with('reportingTo:id,first_name,last_name')
              ->orderByDesc('probation_end_date')
              ->limit(25)
              ->get();
      });
      // Counted in full, not from the list above: it stops at 25 rows, and
      // counting it capped the overdue / due-soon figures at 25 as well.
      [$probationOverdueCount, $probationDueSoonCount] = Cache::remember("dashboard.probation_counts.{$tenantId}", 1800, function () use ($probationCutoff) {
          $q = User::where('status', UserAccountStatus::ACTIVE)
              ->whereNotNull('probation_end_date')
              ->whereNull('probation_confirmed_at');
          $today = now()->toDateString();
          return [
              (clone $q)->where('probation_end_date', '<', $today)->count(),
              (clone $q)->whereBetween('probation_end_date', [$today, $probationCutoff])->count(),
          ];
      });

      // Extra Stats for Suggestions
      $absentCount = max(0, $active - $presentUsersCount - $onLeaveUsersCount);
      $newHiresThisMonth = User::whereMonth('date_of_joining', now()->month)->whereYear('date_of_joining', now()->year)->count();

      // 9. Pending Approvals — fetched fresh (5-min cache so approvals feel responsive)
      $pendingApprovals = Cache::remember("dashboard.pending_approvals.{$tenantId}", 300, function () {
          $list = collect();

          // Fetch all four approval types in one go each (no .each() — avoids chunked N+1)
          $leavesPending = LeaveRequest::where('status', 'pending')
              ->with(['user.department', 'leaveType'])
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
                  'department' => $r->user?->department?->name ?? 'HR',
                  'avatar'     => $r->user ? $r->user->getProfilePicture() : 'https://ui-avatars.com/api/?name=' . urlencode($r->user?->name ?? 'User') . '&background=004D4D&color=fff',
                  'date'       => $r->from_date ? Carbon::parse($r->from_date)->format('M d') : 'N/A',
                  'raw_date'   => $r->from_date ? Carbon::parse($r->from_date) : now(),
                  'time_ago'   => $r->created_at ? $r->created_at->diffForHumans() : 'Recently',
                  'details'    => (optional($r->leaveType)->name ?? 'Request') . ' (' . $days . ' days)',
                  'days'       => $days,
                  'id'         => $r->id,
              ]);
          }

          // Outdoor duty waits on the same Leave page; it was missing from this card.
          foreach (\App\Models\OutdoorDuty::where('status', 'pending')->with('user.department')->get() as $r) {
              $list->push([
                  'type'       => 'Outdoor Duty',
                  'user'       => $r->user?->name ?? 'N/A',
                  'emp_id'     => $r->user?->code ?? 'N/A',
                  'department' => $r->user?->department?->name ?? 'HR',
                  'avatar'     => $r->user ? $r->user->getProfilePicture() : 'https://ui-avatars.com/api/?name=' . urlencode($r->user?->name ?? 'User') . '&background=004D4D&color=fff',
                  'date'       => $r->date ? $r->date->format('M d') : 'N/A',
                  'raw_date'   => $r->date ?? now(),
                  'time_ago'   => $r->created_at ? $r->created_at->diffForHumans() : 'Recently',
                  'details'    => 'Outdoor Duty' . ($r->location ? ' - ' . $r->location : ''),
                  'days'       => 1,
                  'id'         => $r->id,
              ]);
          }

          $expensesPending = ExpenseRequest::where('status', 'pending')
              ->with(['user.department', 'expenseType'])
              ->get();
          foreach ($expensesPending as $r) {
              $list->push([
                  'type'       => 'Expense',
                  'user'       => $r->user?->name ?? 'N/A',
                  'emp_id'     => $r->user?->code ?? 'N/A',
                  'department' => $r->user?->department?->name ?? 'Finance',
                  'avatar'     => $r->user ? $r->user->getProfilePicture() : 'https://ui-avatars.com/api/?name=' . urlencode($r->user?->name ?? 'User') . '&background=004D4D&color=fff',
                  'date'       => Carbon::parse($r->created_at)->format('M d'),
                  'raw_date'   => Carbon::parse($r->created_at),
                  'time_ago'   => $r->created_at->diffForHumans(),
                  'details'    => 'Amount: ' . number_format($r->amount ?? 0, 2),
                  'id'         => $r->id,
              ]);
          }

          $docsPending = DocumentRequest::where('status', 'pending')
              ->with(['user.department', 'documentType'])
              ->get();
          foreach ($docsPending as $r) {
              $list->push([
                  'type'       => 'Document',
                  'user'       => $r->user?->name ?? 'N/A',
                  'emp_id'     => $r->user?->code ?? 'N/A',
                  'department' => $r->user?->department?->name ?? 'Admin',
                  'avatar'     => $r->user ? $r->user->getProfilePicture() : 'https://ui-avatars.com/api/?name=' . urlencode($r->user?->name ?? 'User') . '&background=004D4D&color=fff',
                  'date'       => Carbon::parse($r->created_at)->format('M d'),
                  'raw_date'   => Carbon::parse($r->created_at),
                  'time_ago'   => $r->created_at->diffForHumans(),
                  'details'    => 'Req: ' . (optional($r->documentType)->name ?? 'Document'),
                  'id'         => $r->id,
              ]);
          }

          $loansPending = LoanRequest::where('status', 'pending')
              ->with(['user.department'])
              ->get();
          foreach ($loansPending as $r) {
              $list->push([
                  'type'       => 'Loan',
                  'user'       => $r->user?->name ?? 'N/A',
                  'emp_id'     => $r->user?->code ?? 'N/A',
                  'department' => $r->user?->department?->name ?? 'Operations',
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

      // The "Pending Approvals" card's header "All" link used to be
      // hardcoded to the Leave Approval page regardless of what the list
      // actually contained - so a card full of Document requests still sent
      // the click to Leave. Routes to whichever type has the most pending
      // items instead, since one link can't correctly represent four
      // different approval types at once.
      $approvalTypeUrls = [
          'Leave'    => url('/leaveRequests'),
          'Outdoor Duty' => url('/leaveRequests'),
          'Expense'  => url('/expenseRequests'),
          'Document' => url('/documentmanagement'),
          'Loan'     => url('/loan'),
      ];
      $dominantApprovalType = $pendingApprovals->countBy('type')->sortDesc()->keys()->first();
      $pendingApprovalsUrl = $approvalTypeUrls[$dominantApprovalType] ?? $approvalTypeUrls['Leave'];

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


      // ============================================================
      // ADMIN COMMAND CENTER — dedicated strategic dashboard.
      // Everything here is REAL data pulled from live tables. Where a
      // widget in the design had no backing source, it is replaced by a
      // real module instead of a placeholder:
      //   • "LMS" -> the real Training module (training_modules / progress)
      //   • "KPI Summary" -> kpi_checks buckets, else today's workforce split
      //   • Workforce chart -> real New-Joins / Exits (transfers/promotions
      //     are not tracked, so they are not shown)
      // HR / Manager keep their existing dashboards untouched.
      // ============================================================

      // ---- FINANCE COMMAND CENTER (accounts role) -----------------
      // Accounts used to be handed the HR dashboard at "/" and a separate
      // legacy page at /user/dashboard — neither showed the money. This is
      // their own finance view: cash-out queue, claim pipeline, payroll cost
      // and the approved-WFH attendance queue they own.
      if ($user->hasRole('accounts') && !$user->hasRole(['admin', 'hr'])) {
        return $this->financeCommandCenter($user, $active, $presentUsersCount, $onLeaveUsersCount);
      }

      if ($user->hasRole('admin')) {

        // Indian short-form money formatter (Cr / L / K)
        $short = function ($n) {
          $n = (float) $n;
          if ($n >= 10000000) return number_format($n / 10000000, 2) . ' Cr';
          if ($n >= 100000)   return number_format($n / 100000, 2) . ' L';
          if ($n >= 1000)     return number_format($n / 1000, 1) . 'K';
          return number_format($n);
        };
        // Sales figures (pipeline / targets / reports) are stored in LAKHS,
        // so format the raw value as Cr / L, never K.
        $shortL = function ($n) {
          $n = (float) $n;
          if ($n >= 100) return number_format($n / 100, 2) . ' Cr';
          if ($n >= 1)   return number_format($n, 2) . ' L';
          return number_format($n * 100000);
        };

        // --- KPI: net payroll for the most recent processed cycle
        //     (payroll_records is the source of truth; payslip money columns
        //     are not populated). Using the latest cycle rather than strictly
        //     "this month" so the card shows the last real payroll run. ---
        $monthlyPayroll = 0.0; $payrollPeriodLabel = now()->format('M Y');
        try {
          $latestCycle = \App\Models\PayrollCycle::orderByDesc('pay_period_start')->first();
          if ($latestCycle) {
            $monthlyPayroll = (float) \App\Models\PayrollRecord::where('payroll_cycle_id', $latestCycle->id)->sum('net_salary');
            if ($latestCycle->pay_period_start) $payrollPeriodLabel = Carbon::parse($latestCycle->pay_period_start)->format('M Y');
          }
        } catch (\Throwable $e) { $monthlyPayroll = 0.0; }

        // --- Sales pipeline value + stage funnel (open book, is_locked = 0) ---
        $pipelineValue = 0.0; $salesFunnel = collect();
        try {
          $pipelineValue = (float) \App\Models\SalesPipeline::where('is_locked', false)->sum('total_business_potential');
          $salesFunnel = \App\Models\SalesPipeline::where('is_locked', false)
              ->selectRaw('COALESCE(NULLIF(status_stage, ""), "Unspecified") AS stage, COUNT(*) AS deals, SUM(total_business_potential) AS value')
              ->groupBy('status_stage')
              ->orderByDesc('value')
              ->take(6)
              ->get();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        // --- Sales target vs achievement, current month (real: salesperson_targets
        //     for target, derived achievement from locked pipeline months) ---
        // Company-wide target for each of the three tiers (Bingo / Kingo /
        // Super Kingo), plus achievement derived from the Sales Report (locked
        // pipeline rows) for the current month. The card toggles between tiers.
        $mk = now()->format('Y-m');
        $salesTargets = ['Bingo' => 0.0, 'Kingo' => 0.0, 'Super Kingo' => 0.0];
        $salesAchieved = 0.0; $topPerformers = collect();
        try {
          $sumRow = \App\Models\SalespersonTarget::where('month_year', $mk)
              ->selectRaw('SUM(bingo_target) b, SUM(kingo_target) k, SUM(super_kingo_target) s')->first();
          if ($sumRow) $salesTargets = ['Bingo' => (float) $sumRow->b, 'Kingo' => (float) $sumRow->k, 'Super Kingo' => (float) $sumRow->s];

          $achievedBySp = DB::table('sales_pipeline_months as m')
              ->join('sales_pipelines as sp', 'sp.id', '=', 'm.sales_pipeline_id')
              ->where('m.month_year', $mk)
              ->where('sp.is_locked', true)          // locked = Sales Report (realized sales)
              ->whereNull('sp.deleted_at')
              // The salesperson of each month: after a leaver handover, the month goes to whoever held it then.
              // (grouped by the alias: strict MySQL rejects grouping on the subquery itself)
              ->groupBy('spid')
              ->selectRaw(\App\Models\SalesPipelineHandover::holderSql('salesperson_id', 'sp', 'm') . ' AS spid, SUM(m.sale_amount) AS achieved')
              ->pluck('achieved', 'spid');
          $salesAchieved = (float) $achievedBySp->sum();

          $topPerformers = \App\Models\SalespersonTarget::with('salesperson:id,first_name,last_name')
              ->where('month_year', $mk)->get()
              ->map(function ($t) use ($achievedBySp) {
                  $ach = (float) ($achievedBySp[$t->salesperson_id] ?? 0);
                  $tgt = (float) $t->bingo_target;
                  return (object) [
                      'name'     => optional($t->salesperson)->name ?? 'Unknown',
                      'target'   => $tgt,
                      'achieved' => $ach,
                      'pct'      => $tgt > 0 ? round($ach / $tgt * 100, 1) : null,
                  ];
              })
              ->filter(fn($r) => $r->target > 0 || $r->achieved > 0)
              ->sortByDesc('achieved')->take(20)->values();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
        $salesTargetPct = [];
        foreach ($salesTargets as $type => $tgt) $salesTargetPct[$type] = $tgt > 0 ? round($salesAchieved / $tgt * 100, 1) : 0;
        $salesAchievementPct = $salesTargetPct['Bingo'] ?? 0;

        // --- Assets overview (status: available/assigned/maintenance/retired) ---
        $assetTotal = 0; $assetAssigned = 0; $assetMaintenance = 0; $assetAvailable = 0; $assetValue = 0.0;
        try {
          $assetTotal       = \App\Models\Asset::count();
          $assetAssigned    = \App\Models\Asset::where('status', 'assigned')->count();
          $assetMaintenance = \App\Models\Asset::where('status', 'maintenance')->count();
          $assetAvailable   = \App\Models\Asset::where('status', 'available')->count();
          $assetValue       = (float) \App\Models\Asset::sum('purchase_cost');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        // --- Training module (there is no LMS; use the real training system) ---
        $trainingModules = 0; $trainingEnrollments = 0; $trainingCompleted = 0; $trainingInProgress = 0;
        try {
          $trainingModules     = DB::table('training_modules')->whereNull('deleted_at')->count();
          $trainingEnrollments = DB::table('user_training_progress')->count();
          $trainingCompleted   = DB::table('user_training_progress')->where('status', 'completed')->count();
          $trainingInProgress  = DB::table('user_training_progress')->where('status', 'in_progress')->count();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
        $trainingCompletionPct = $trainingEnrollments > 0 ? round($trainingCompleted / $trainingEnrollments * 100) : 0;

        // --- Attrition YTD ---
        $relievedThisYear = 0; $attritionRate = 0;
        try {
          $relievedThisYear = User::whereYear('relieved_at', now()->year)->count();
          $attritionRate = $active > 0 ? round($relievedThisYear / $active * 100, 1) : 0;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        // --- Leave trend: approved requests by month & type, current year ---
        $leaveTrend = ['labels' => [], 'series' => []];
        try {
          $rows = DB::table('leave_requests as lr')
              ->join('leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')
              ->selectRaw('MONTH(lr.from_date) as m, lt.name as type, COUNT(*) as total')
              ->where('lr.status', 'approved')
              ->whereYear('lr.from_date', now()->year)
              ->whereNull('lr.deleted_at')
              ->groupBy('m', 'lt.name')
              ->get();
          $monthsCount = (int) now()->month;
          $labels = [];
          for ($i = 1; $i <= $monthsCount; $i++) { $labels[] = Carbon::create(now()->year, $i, 1)->format('M'); }
          $series = [];
          foreach ($rows->pluck('type')->unique()->values() as $type) {
              $data = array_fill(1, $monthsCount, 0);
              foreach ($rows->where('type', $type) as $r) { if ($r->m <= $monthsCount) $data[(int)$r->m] = (int) $r->total; }
              $series[] = ['name' => $type, 'data' => array_values($data)];
          }
          $leaveTrend = ['labels' => $labels, 'series' => $series];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        // --- "KPI Summary": real kpi_checks buckets, else today's workforce split ---
        $kpiSummary = ['labels' => ['On Duty', 'On Leave', 'Absent'], 'values' => [(int)$presentUsersCount, (int)$onLeaveUsersCount, (int)max(0, $absentCount)], 'source' => 'workforce'];
        try {
          $k = DB::table('kpi_checks')
              ->whereDate('period_end', '>=', now()->subDays(45)->toDateString())
              ->selectRaw("SUM(status='completed_on_time') as ontrack, SUM(status IN('pending','completed_late')) as atrisk, SUM(status='missed') as offtrack, COUNT(*) as total")
              ->first();
          if ($k && (int) $k->total > 0) {
              $kpiSummary = ['labels' => ['On Track', 'At Risk', 'Off Track'], 'values' => [(int)$k->ontrack, (int)$k->atrisk, (int)$k->offtrack], 'source' => 'kpi'];
          }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        $activeApprovalsTotal = $pendingLeaveRequests + $pendingExpenseRequests + $pendingDocumentRequests + $pendingLoanRequests;
        $attPct = $active > 0 ? round($presentUsersCount / $active * 100, 1) : 0;

        // --- Attendance over the last 7 days (today alone is frequently empty) ---
        // One grouped query instead of 7 identical-shaped per-day queries —
        // was showing up 8x in the dashboard's own query log (7 here + the
        // $presentUsersCount query above sharing the same shape).
        $att7 = []; $att7pctSum = 0;
        try {
          $att7RangeStart = now()->subDays(6)->startOfDay();
          // Same "present" definition as the KPI card above — now the same
          // AttendanceDayStatus resolver every attendance page shares,
          // instead of matching raw status text.
          $att7ByDate = $this->presentCountsByDate($att7RangeStart, now()->endOfDay(), null);
          for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $present = (int) ($att7ByDate[$d->toDateString()] ?? 0);
            $pct = $active > 0 ? (int) round($present / $active * 100) : 0;
            $att7[] = ['label' => $d->format('D'), 'pct' => $pct, 'present' => $present];
            $att7pctSum += $pct;
          }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
        $att7Avg = count($att7) ? round($att7pctSum / count($att7), 1) : 0;

        $upcomingHolidays = Holiday::upcomingForUser($user);

        // ============================================================
        // CEO COMMAND CENTER — the money view of the company, on top of
        // the operational dashboard. Everything below is live data.
        // ============================================================

        // --- Cash out: every rupee awaiting a decision or a payment ---
        $ceoCashOut = ['payroll' => 0.0, 'claims' => 0.0, 'expenses' => 0.0, 'loans' => 0.0];
        try {
          $ceoCashOut['payroll'] = (float) Payslip::where('status', '!=', 'paid')
            ->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)
            ->sum('net_salary');
          $ceoCashOut['claims'] = (float) \App\Models\TravelClaim::whereIn('status', ['submitted', 'verified', 'approved'])
            ->sum(DB::raw('COALESCE(net_payable, total_amount, 0)'));
          $ceoCashOut['expenses'] = (float) ExpenseRequest::where('status', 'pending')->sum('amount');
          $ceoCashOut['loans']    = (float) LoanRequest::where('status', 'pending')->sum('amount');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
        $ceoCashOutTotal = array_sum($ceoCashOut);

        // --- Payroll cost trend (12 months) + current monthly salary run ---
        $ceoPayrollTrend = ['labels' => [], 'data' => []];
        $ceoMonthlySalaryCost = 0.0;
        try {
          $ceoMonthlySalaryCost = (float) User::where('status', UserAccountStatus::ACTIVE)->sum('base_salary');
          $pStart = now()->copy()->startOfMonth()->subMonths(11);
          $pByMonth = Payslip::where('created_at', '>=', $pStart)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') ym, SUM(net_salary) total")
            ->groupBy('ym')->pluck('total', 'ym');
          for ($i = 0; $i < 12; $i++) {
            $m = $pStart->copy()->addMonths($i);
            $ceoPayrollTrend['labels'][] = $m->format('M y');
            $ceoPayrollTrend['data'][]   = round((float) ($pByMonth[$m->format('Y-m')] ?? 0), 2);
          }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        // --- Top & bottom performers vs target (current month) ---
        $ceoTopReps = collect();
        $ceoBottomReps = collect();
        try {
          $monthKey = now()->format('Y-m');
          $achievedByRep = \App\Models\SalesPipeline::query()
            ->join('sales_pipeline_months as m', 'm.sales_pipeline_id', '=', 'sales_pipelines.id')
            ->where('m.month_year', $monthKey)
            ->whereNotNull('sales_pipelines.salesperson_id')
            ->selectRaw(\App\Models\SalesPipelineHandover::holderSql('salesperson_id', 'sales_pipelines', 'm') . ' sp_id, SUM(COALESCE(m.sale_amount,0)) achieved')
            ->groupBy('sp_id')
            ->pluck('achieved', 'sp_id');

          $targetsByRep = \App\Models\SalespersonTarget::where('month_year', $monthKey)
            ->pluck('bingo_target', 'salesperson_id');

          $repIds = collect($achievedByRep->keys())->merge($targetsByRep->keys())->unique()->filter();
          if ($repIds->isNotEmpty()) {
            $repNames = User::whereIn('id', $repIds)->pluck(DB::raw("CONCAT(first_name,' ',last_name)"), 'id');
            $rows = $repIds->map(function ($id) use ($achievedByRep, $targetsByRep, $repNames) {
              $ach = (float) ($achievedByRep[$id] ?? 0);
              $tgt = (float) ($targetsByRep[$id] ?? 0);
              return [
                'name' => $repNames[$id] ?? 'Unknown',
                'achieved' => $ach,
                'target' => $tgt,
                'pct' => $tgt > 0 ? round($ach / $tgt * 100, 1) : null,
              ];
            })->filter(fn($r) => $r['pct'] !== null)->values();

            $ceoTopReps    = $rows->sortByDesc('pct')->take(5)->values();
            $ceoBottomReps = $rows->sortBy('pct')->take(5)->values();
          }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        // --- Headcount cost per department (where the money goes) ---
        $ceoDeptCost = collect();
        try {
          $ceoDeptCost = User::query()
            ->where('users.status', UserAccountStatus::ACTIVE)
            ->whereNotNull('users.department_id')
            ->join('departments', 'departments.id', '=', 'users.department_id')
            // Alias must not be `name` — User::getNameAttribute() would override it.
            ->selectRaw('departments.name dept_name, COUNT(users.id) headcount, SUM(COALESCE(users.base_salary,0)) cost')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('cost')
            ->limit(8)
            ->get();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        // --- AI Insights: real signals derived from the numbers above ---
        $aiInsights = [];
        $aiInsights[] = ['icon' => 'bx-user-check', 'tone' => $attPct >= 90 ? 'success' : ($attPct >= 75 ? 'warning' : 'danger'), 'title' => $attPct . '% present today', 'sub' => $presentUsersCount . ' of ' . $active . ' active staff checked in', 'url' => url('/attendance')];
        if ($probationOverdueCount > 0)          $aiInsights[] = ['icon' => 'bx-error-circle', 'tone' => 'danger', 'title' => $probationOverdueCount . ' probations overdue', 'sub' => 'End date passed, still unconfirmed', 'url' => url('/probation/dashboard')];
        if ($probationDueSoonCount > 0)          $aiInsights[] = ['icon' => 'bx-time-five', 'tone' => 'warning', 'title' => $probationDueSoonCount . ' probations ending', 'sub' => 'Within 30 days — review confirmations', 'url' => url('/probation/dashboard')];
        if ($activeApprovalsTotal > 0)           $aiInsights[] = ['icon' => 'bx-check-shield', 'tone' => 'info', 'title' => $activeApprovalsTotal . ' approvals pending', 'sub' => 'Leave, expense, document & loan requests', 'url' => $pendingApprovalsUrl];
        if (($salesTargets['Bingo'] ?? 0) > 0)   $aiInsights[] = ['icon' => 'bx-trending-up', 'tone' => $salesAchievementPct >= 80 ? 'success' : 'warning', 'title' => 'Sales at ' . $salesAchievementPct . '% of target', 'sub' => 'Bingo target for ' . now()->format('F'), 'url' => url('/sales-visits/kingo-bingo')];
        if ($relievedThisYear > 0)               $aiInsights[] = ['icon' => 'bx-user-x', 'tone' => 'danger', 'title' => $attritionRate . '% attrition YTD', 'sub' => $relievedThisYear . ' employees relieved this year', 'url' => url('/employees')];
        if ($newHiresThisMonth > 0)              $aiInsights[] = ['icon' => 'bx-user-plus', 'tone' => 'success', 'title' => $newHiresThisMonth . ' new hires this month', 'sub' => 'Onboarding in progress', 'url' => url('/employees')];

        // --- Today's events (real counts) ---
        $eventsToday = [
          ['icon' => 'bx-cake', 'label' => 'Birthdays Today', 'count' => $todayBirthdays->count(), 'tone' => 'primary', 'url' => route('employee.celebrations')],
          ['icon' => 'bx-gift', 'label' => 'Work Anniversaries', 'count' => $todayAnniversaries->count(), 'tone' => 'info', 'url' => route('employee.celebrations')],
          ['icon' => 'bx-time', 'label' => 'Probation Ending', 'count' => $probationOverdueCount + $probationDueSoonCount, 'tone' => 'warning', 'url' => url('/probation/dashboard')],
          ['icon' => 'bx-calendar-star', 'label' => 'Holidays This Month', 'count' => Holiday::countThisMonthForUser($user), 'tone' => 'success', 'url' => url('/holidays')],
          ['icon' => 'bx-calendar-event', 'label' => 'Leaves Pending', 'count' => $pendingLeaveRequests, 'tone' => 'danger', 'url' => url('/leaveRequests')],
          ['icon' => 'bx-user-plus', 'label' => 'Joined This Month', 'count' => $newHiresThisMonth, 'tone' => 'primary', 'url' => url('/employees')],
        ];

        // --- Recent activity feed (real: new joiners + latest approvals) ---
        $recentFeed = collect();
        try {
          foreach (User::whereNotNull('date_of_joining')->where('date_of_joining', '>=', now()->subDays(30)->toDateString())->orderByDesc('date_of_joining')->take(4)->get(['id', 'first_name', 'last_name', 'date_of_joining']) as $u) {
              $recentFeed->push(['type' => 'People', 'icon' => 'bx-user-plus', 'tone' => 'primary', 'title' => trim($u->first_name . ' ' . $u->last_name) . ' joined', 'time' => Carbon::parse($u->date_of_joining)->diffForHumans(), 'raw' => Carbon::parse($u->date_of_joining), 'url' => url('/employees/view/' . $u->id)]);
          }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
        foreach ($pendingApprovals->take(5) as $a) {
          $recentFeed->push(['type' => $a['type'], 'icon' => 'bx-check-shield', 'tone' => 'warning', 'title' => ($a['user'] ?? 'Someone') . ' — ' . $a['type'] . ' request', 'time' => $a['time_ago'] ?? 'Recently', 'raw' => $a['raw_date'] ?? now(), 'url' => $approvalTypeUrls[$a['type']] ?? $pendingApprovalsUrl]);
        }
        $recentFeed = $recentFeed->sortByDesc('raw')->take(8)->values();

        // --- Module tiles (all real live counts) ---
        $moduleTiles = [
          ['name' => 'People Mgmt',  'icon' => 'bx-group',          'value' => number_format($active),        'sub' => 'Employees',   'url' => url('/employees'),                    'tone' => 'primary'],
          ['name' => 'Recruitment',  'icon' => 'bx-user-plus',      'value' => (string) $activeJobsCount,      'sub' => 'Open Roles',  'url' => url('/job'),                          'tone' => 'info'],
          ['name' => 'Attendance',   'icon' => 'bx-time-five',      'value' => $attPct . '%',                  'sub' => 'Today',       'url' => url('/attendance'),                   'tone' => 'success'],
          ['name' => 'Leave Mgmt',   'icon' => 'bx-calendar-event', 'value' => (string) $pendingLeaveRequests, 'sub' => 'Pending',     'url' => url('/leaveRequests'),                'tone' => 'warning'],
          ['name' => 'Payroll',      'icon' => 'bx-money',          'value' => '₹' . $short($monthlyPayroll),  'sub' => $payrollPeriodLabel, 'url' => url('/payroll'),                'tone' => 'success'],
          ['name' => 'Performance',  'icon' => 'bx-bar-chart-alt-2','value' => $trainingCompletionPct . '%',   'sub' => 'Training',    'url' => url('/performance/scorecards'),       'tone' => 'primary'],
          ['name' => 'Sales',        'icon' => 'bx-doughnut-chart', 'value' => '₹' . $shortL($pipelineValue),  'sub' => 'Pipeline',    'url' => url('/sales-visits/sales-pipeline'),  'tone' => 'info'],
          ['name' => 'Assets',       'icon' => 'bx-archive',        'value' => (string) $assetTotal,           'sub' => 'Total',       'url' => url('/asset-management'),             'tone' => 'warning'],
          ['name' => 'Training',     'icon' => 'bx-book',           'value' => (string) $trainingModules,      'sub' => 'Modules',     'url' => url('/training/manage'),              'tone' => 'primary'],
        ];

        // Telephony call tracking — wrapped defensively since the table is
        // brand new and may not be migrated on every environment yet.
        try {
          $telephonyStats = [
            'total'     => \App\Models\TelephonyCall::count(),
            'today'     => \App\Models\TelephonyCall::whereDate('created_at', today())->count(),
            'hrx'       => \App\Models\TelephonyCall::where('origin', \App\Enums\CallOrigin::HRX)->count(),
            'external'  => \App\Models\TelephonyCall::where('origin', \App\Enums\CallOrigin::EXTERNAL)->count(),
            'connected' => \App\Models\TelephonyCall::where('status', \App\Enums\TelephonyCallStatus::CONNECTED)->count(),
          ];
        } catch (\Throwable $e) {
          $telephonyStats = ['total' => 0, 'today' => 0, 'hrx' => 0, 'external' => 0, 'connected' => 0];
        }

        return view('tenant.users.dashboard.admin-index', [
            'recentNotices' => \App\Support\DashboardAnnouncements::forUser($user ?? auth()->user()),
          'importantContacts'    => \App\Services\HrNotificationService::importantContactsFor($user ?? auth()->user()),
          'todayVisits'           => $todayVisits,
          'pageConfigs'           => ['contentLayout' => 'wide'],
          // Celebrations card — computed above for the HR dashboard, but the
          // Admin view was never handed them, so its card rendered empty.
          'upcomingBirthdays'     => $upcomingBirthdays,
          'upcomingAnniversaries' => $upcomingAnniversaries,
          // headline stats
          'activeEmployees'       => $active,
          'totalUser'             => $totalUser,
          'active'                => $active,
          'presentUsersCount'     => $presentUsersCount,
          'attPct'                => $attPct,
          'todayAbsentUsers'      => $todayAbsentUsers,
          'absentCount'           => $absentCount,
          'onLeaveUsersCount'     => $onLeaveUsersCount,
          'newHiresThisMonth'     => $newHiresThisMonth,
          'activeJobsCount'       => $activeJobsCount,
          'trends'                => $trends,
          // finance / sales KPIs
          'monthlyPayroll'        => $monthlyPayroll,
          'monthlyPayrollShort'   => $short($monthlyPayroll),
          'monthlyPayrollLabel'   => $payrollPeriodLabel,
          'pipelineValue'         => $pipelineValue,
          'pipelineValueShort'    => $shortL($pipelineValue),
          'attritionRate'         => $attritionRate,
          'relievedThisYear'      => $relievedThisYear,
          'activeApprovalsTotal'  => $activeApprovalsTotal,
          'pendingLeaveRequests'  => $pendingLeaveRequests,
          'pendingExpenseRequests' => $pendingExpenseRequests,
          'pendingDocumentRequests' => $pendingDocumentRequests,
          'pendingLoanRequests'   => $pendingLoanRequests,
          // charts
          'hiringTrend'           => $hiringTrend,
          'departmentData'        => $departmentData,
          'leaveTrend'            => $leaveTrend,
          'kpiSummary'            => $kpiSummary,
          'salesFunnel'           => $salesFunnel,
          'salesTargets'          => $salesTargets,
          'salesTargetPct'        => $salesTargetPct,
          'salesAchieved'         => $salesAchieved,
          'salesAchievedShort'    => $shortL($salesAchieved),
          'salesAchievementPct'   => $salesAchievementPct,
          'topPerformers'         => $topPerformers,
          'att7'                  => $att7,
          'att7Avg'               => $att7Avg,
          // assets / training
          'assetTotal'            => $assetTotal,
          'assetAssigned'         => $assetAssigned,
          'assetMaintenance'      => $assetMaintenance,
          'assetAvailable'        => $assetAvailable,
          'assetValueShort'       => $short($assetValue),
          'trainingModules'       => $trainingModules,
          'trainingEnrollments'   => $trainingEnrollments,
          'trainingCompleted'     => $trainingCompleted,
          'trainingInProgress'    => $trainingInProgress,
          'trainingCompletionPct' => $trainingCompletionPct,
          // rails
          'aiInsights'            => $aiInsights,
          'eventsToday'           => $eventsToday,
          'upcomingHolidays'      => $upcomingHolidays,
          'pendingApprovals'      => $pendingApprovals->sortByDesc('raw_date'),
          'pendingApprovalsUrl'   => $pendingApprovalsUrl,
          'recentFeed'            => $recentFeed,
          'moduleTiles'           => $moduleTiles,
          // celebrations
          'todayBirthdays'        => $todayBirthdays,
          'todayAnniversaries'    => $todayAnniversaries,
          'upcomingProbationEnds' => $upcomingProbationEnds,
          'probationOverdueCount' => $probationOverdueCount,
          'probationDueSoonCount' => $probationDueSoonCount,
          // CEO Command Center
          'ceoCashOut'            => $ceoCashOut,
          'ceoCashOutTotal'       => $ceoCashOutTotal,
          'ceoPayrollTrend'       => $ceoPayrollTrend,
          'ceoMonthlySalaryCost'  => $ceoMonthlySalaryCost,
          'ceoTopReps'            => $ceoTopReps,
          'ceoBottomReps'         => $ceoBottomReps,
          'ceoDeptCost'           => $ceoDeptCost,
          'telephonyStats'        => $telephonyStats,
        ]);
      }

      // MANAGER SPECIFIC SCOPING FOR REVAMP
      if ($isManager) {
        // Direct reports only — NOT the manager themself. Every stat built from
        // this list (Team Size, Present/On Leave/Absent, Pending Approvals,
        // team deployment/attendance trend, upcoming leaves) is "how is my
        // team doing", so including self inflated Team Size by one and would
        // have put the manager's own leave/expense/document/loan requests in
        // their own "Pending Approvals" queue.
        $teamMemberIds = User::where('reporting_to_id', $user->id)->notTerminated()->pluck('id')->toArray();

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
        
        $pendingLeaveRequests = LeaveRequest::whereIn('user_id', $teamMemberIds)->where('status', 'pending')->count()
            + \App\Models\OutdoorDuty::whereIn('user_id', $teamMemberIds)->where('status', 'pending')->count();

        $teamPendingLeaveRequests = LeaveRequest::whereIn('user_id', $teamMemberIds)
            ->where('status', 'pending')
            ->with(['user', 'leaveType'])
            ->latest()
            ->take(5)
            ->get();

        // Team Deployment: EVERY direct report with their status today
        $deployMembers = User::where('reporting_to_id', $user->id)
            ->where('status', UserAccountStatus::ACTIVE)
            ->with('designation')
            ->get();
        $deployMemberIds = $deployMembers->pluck('id');
        // Present: resolved through the same AttendanceDayStatus resolver
        // every attendance page shares, not "has any Attendance row today at
        // all" - that used to count a dummy leave-linked row (no real punch)
        // as present, and never checked whether a real punch actually
        // cleared enough minutes to mean anything.
        $todayAttendanceByUser = Attendance::whereIn('user_id', $deployMemberIds)
            ->whereDate('check_in_time', now())
            ->with(['user', 'leaveRequest.leaveType'])
            ->get()->groupBy('user_id');
        $presentTeamIds = $todayAttendanceByUser->filter(function ($rows) {
            $best = $rows->sortByDesc(fn($a) => ($a->check_in_time && $a->check_in_time->format('H:i') !== '00:00') ? 1 : 0)->first();
            $holiday = $best->check_in_time ? \App\Services\AttendanceDayStatus::holidayForUserOnDateSingle($best->user, $best->check_in_time->toDateString()) : null;
            return \App\Services\AttendanceDayStatus::isPresentLikeRow($best, $holiday);
        })->keys();
        $onLeaveTeamIds = LeaveRequest::whereIn('user_id', $deployMemberIds)
            ->whereDate('from_date', '<=', now())->whereDate('to_date', '>=', now())
            ->where('status', LeaveRequestStatus::APPROVED)->pluck('user_id')->unique();
        $teamDeployment = $deployMembers->map(function ($m) use ($presentTeamIds, $onLeaveTeamIds) {
            if ($presentTeamIds->contains($m->id)) {
                $status = 'present';
            } elseif ($onLeaveTeamIds->contains($m->id)) {
                $status = 'leave';
            } elseif (!\App\Services\LeavePolicyService::isWorkingDay($m, now())) {
                // A Sunday, off-Saturday, or holiday - not a real absence,
                // and previously indistinguishable from one on this widget.
                $status = 'off';
            } else {
                $status = 'absent';
            }
            return (object) ['user' => $m, 'deploy_status' => $status];
        });

        // Team attendance over the last 7 days (present % of team per day) --
        // one grouped query instead of 7 identical-shaped per-day queries.
        $teamAtt7 = [];
        try {
            $teamAtt7RangeStart = now()->subDays(6)->startOfDay();
            // Same resolver-based "present" as every other attendance
            // surface - this used to count ANY Attendance row for the day,
            // including a dummy leave-linked row with no real punch.
            $teamAtt7ByDate = $this->presentCountsByDate($teamAtt7RangeStart, now()->endOfDay(), $teamMemberIds);
            for ($i = 6; $i >= 0; $i--) {
                $d = now()->subDays($i);
                $p = (int) ($teamAtt7ByDate[$d->toDateString()] ?? 0);
                $teamAtt7[] = ['label' => $d->format('D'), 'pct' => $active > 0 ? (int) round($p / $active * 100) : 0];
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard tile failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
        // Upcoming approved team leaves
        $teamUpcomingLeaves = LeaveRequest::whereIn('user_id', $teamMemberIds)
            ->where('status', LeaveRequestStatus::APPROVED)
            ->whereDate('from_date', '>', now())
            ->with(['user', 'leaveType'])->orderBy('from_date')->take(8)->get();

        // Sales figures for a manager. Shared with UserDashboardController,
        // which renders this same view at /user/dashboard.
        $sm = \App\Support\SalesManagerSummary::build($deployMembers, $teamDeployment, $pendingLeaveRequests);

        // Team-scoped approval counts. Hoisted so the rail and the view below
        // read the same numbers — the admin branch's own copies of these are
        // company-wide and never run for a manager.
        $mgrPendingExpense  = 0;
        $mgrPendingDocument = DocumentRequest::whereIn('user_id', $teamMemberIds)->where('status', 'pending')->count();
        $mgrPendingLoan     = LoanRequest::whereIn('user_id', $teamMemberIds)->where('status', 'pending')->count();

        $mgrRail = \App\Support\ManagerDashboard::rail([
            'teamSize' => $active, 'present' => $presentUsersCount ?? 0,
            'onLeave' => $onLeaveUsersCount ?? 0, 'absent' => $todayAbsentUsers ?? 0,
            'pendingLeave' => $pendingLeaveRequests, 'pendingExpense' => $mgrPendingExpense,
            'pendingDocument' => $mgrPendingDocument, 'pendingLoan' => $mgrPendingLoan,
            'todayBirthdays' => $todayBirthdays ?? [], 'todayAnniversaries' => $todayAnniversaries ?? [],
            'sm' => $sm, 'user' => $user,
        ]);

        return view('tenant.users.dashboard.manager-index', array_merge($mgrRail, [
            'recentNotices' => \App\Support\DashboardAnnouncements::forUser($user ?? auth()->user()),
            'importantContacts' => \App\Services\HrNotificationService::importantContactsFor($user ?? auth()->user()),
            'mgrVariant' => \App\Support\ManagerDashboard::variant($user),
            'mgrDept' => \App\Support\ManagerDashboard::departmentName($user),
            'sm' => $sm,
            'teamAtt7' => $teamAtt7,
            'teamUpcomingLeaves' => $teamUpcomingLeaves,
            'teamDeployment' => $teamDeployment,
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
            'pendingExpenseRequests' => $mgrPendingExpense,
            'pendingDocumentRequests' => $mgrPendingDocument,
            'pendingLoanRequests' => $mgrPendingLoan,
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
            'nextHoliday' => Holiday::upcomingForUser($user, 1)->first(),
            'payrollTrend' => 0,
            'latestNetSalary' => 0,
            'departments' => \App\Models\Department::withTrashed()->where('status', \App\Enums\Status::ACTIVE)->orderBy('name')->get(),
            'roles' => \Spatie\Permission\Models\Role::get(),
            'designations' => \App\Models\Designation::withTrashed()->where('status', 'active')->get(),
            'managers' => \App\Models\User::withTrashed()->whereHas('roles', function($q) {
                $q->whereIn('name', ['admin', 'hr', 'manager', 'accounts']);
            })->where('status', UserAccountStatus::ACTIVE)->get()
        ]));
      }


      $roles = \Spatie\Permission\Models\Role::get();
      $departments  = Department::withTrashed()->where('status', Status::ACTIVE)->orderBy('name')->get();
      $teams        = Team::withTrashed()->where('status', Status::ACTIVE)->get();
      $designations = \App\Models\Designation::withTrashed()->where('status', 'active')->get();
      $managers     = User::withTrashed()->whereHas('roles', function($q) {
          $q->whereIn('name', ['admin', 'hr', 'manager', 'accounts']);
      })->where('status', UserAccountStatus::ACTIVE)->get();

      // Fallback: If no data found, try without status filter
      if ($departments->isEmpty()) {
          $departments = Department::withTrashed()->orderBy('name')->get();
      }
      if ($designations->isEmpty()) {
          $designations = \App\Models\Designation::withTrashed()->get();
      }
      if ($managers->isEmpty()) {
          $managers = User::withTrashed()->where('status', UserAccountStatus::ACTIVE)->get();
      }

      // Return HR dashboard view directly
      return view('tenant.users.dashboard.hr-index', [
          'recentNotices' => \App\Support\DashboardAnnouncements::forUser($user ?? auth()->user()),
        'importantContacts' => \App\Services\HrNotificationService::importantContactsFor($user ?? auth()->user()),
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
        'pendingApprovalsUrl' => $pendingApprovalsUrl,
        'trends' => $trends,
        'upcomingProbationEnds' => $upcomingProbationEnds,
        'probationOverdueCount' => $probationOverdueCount,
        'probationDueSoonCount' => $probationDueSoonCount,
        'upcomingHolidays' => Holiday::upcomingForUser($user),
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
        'nextHoliday' => Holiday::upcomingForUser($user, 1)->first(),
        'recentNotices' => collect(),
        'payrollTrend' => 0,
        'latestNetSalary' => 0
      ]);
    }

    // All non-admin/non-hr/non-manager users → hand off to UserDashboardController
    // which properly routes: employee role → employee-index (personal data only)
    return app(\App\Http\Controllers\tenant\users\UserDashboardController::class)->index();
  }

  /**
   * Live volatile stats for dashboard auto-refresh (present / leave / absent /
   * approvals), scoped to the viewer: managers see their team, everyone else
   * sees the org. Returns formatted strings that drop straight into [data-live].
   */
  public function liveStats()
  {
    $user = auth()->user();
    $isManager = $user->hasRole('manager') && !$user->hasRole(['admin', 'hr', 'accounts']);

    if ($isManager) {
      $ids = User::where('reporting_to_id', $user->id)->pluck('id')->push($user->id)->all();
      $active   = User::whereIn('id', $ids)->where('status', UserAccountStatus::ACTIVE)->count();
      $present  = $this->countPresentToday($ids);
      $onLeave  = LeaveRequest::whereIn('user_id', $ids)->whereDate('from_date', '<=', now())->whereDate('to_date', '>=', now())->where('status', LeaveRequestStatus::APPROVED)->count();
      $approvals = LeaveRequest::whereIn('user_id', $ids)->where('status', 'pending')->count()
        + \App\Models\OutdoorDuty::whereIn('user_id', $ids)->where('status', 'pending')->count()
        + DocumentRequest::whereIn('user_id', $ids)->where('status', 'pending')->count()
        + LoanRequest::whereIn('user_id', $ids)->where('status', 'pending')->count();
    } else {
      $active   = User::where('status', UserAccountStatus::ACTIVE)->count();
      $present  = $this->countPresentToday(null);
      $onLeave  = LeaveRequest::whereDate('from_date', '<=', now())->whereDate('to_date', '>=', now())->where('status', LeaveRequestStatus::APPROVED)->count();
      $approvals = LeaveRequest::where('status', 'pending')->count()
        + \App\Models\OutdoorDuty::where('status', 'pending')->count()
        + ExpenseRequest::where('status', 'pending')->count()
        + DocumentRequest::where('status', 'pending')->count()
        + LoanRequest::where('status', 'pending')->count();
    }

    $pct = $active > 0 ? round($present / $active * 100, 1) : 0;
    return response()->json([
      'active'        => number_format($active),
      'present_pct'   => $pct . '%',
      'present_count' => number_format($present),
      'on_leave'      => number_format($onLeave),
      'absent'        => number_format(max(0, $active - $present - $onLeave)),
      'approvals'     => number_format($approvals),
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

  /**
   * FINANCE COMMAND CENTER — the accounts team's own dashboard.
   *
   * Accounts owns payroll, reimbursements, travel claims, loans and (per the
   * owner's WFH flow) marking attendance for approved work-from-home days
   * from the tracking-software report. Everything below is real data.
   */
  private function financeCommandCenter($user, int $active, int $presentUsersCount, int $onLeaveUsersCount)
  {
    $money = function ($n) {
      $n = (float) $n;
      if ($n >= 10000000) return '₹' . number_format($n / 10000000, 2) . ' Cr';
      if ($n >= 100000)   return '₹' . number_format($n / 100000, 2) . ' L';
      return '₹' . number_format($n);
    };

    $monthStart = now()->copy()->startOfMonth();
    $thisMonthKey = now()->format('Y-m');

    // ---- Travel claims pipeline (draft → submitted → verified → approved → paid)
    $claimAgg = \App\Models\TravelClaim::selectRaw('status, COUNT(*) c, SUM(COALESCE(net_payable, total_amount, 0)) amt')
      ->groupBy('status')->get()->keyBy('status');
    $claimStat = fn($s) => [
      'count'  => (int) ($claimAgg[$s]->c ?? 0),
      'amount' => (float) ($claimAgg[$s]->amt ?? 0),
    ];

    $awaitingVerification = $claimStat('submitted');
    $awaitingApproval     = $claimStat('verified');
    $awaitingPayment      = $claimStat('approved');
    $onObjection          = $claimStat('objection');

    // ---- Other payables
    $expensePending = [
      'count'  => ExpenseRequest::where('status', 'pending')->count(),
      'amount' => (float) ExpenseRequest::where('status', 'pending')->sum('amount'),
    ];
    $loanPending = [
      'count'  => LoanRequest::where('status', 'pending')->count(),
      'amount' => (float) LoanRequest::where('status', 'pending')->sum('amount'),
    ];

    // ---- Cash-out queue: everything waiting for money to leave the company
    $cashOutQueue = [
      ['label' => 'Travel claims to pay',   'count' => $awaitingPayment['count'],      'amount' => $awaitingPayment['amount'],      'tone' => 'danger',  'url' => url('/travel-claims/admin/dashboard')],
      ['label' => 'Travel claims to verify','count' => $awaitingVerification['count'], 'amount' => $awaitingVerification['amount'], 'tone' => 'warning', 'url' => url('/travel-claims/admin/dashboard')],
      ['label' => 'Expense reimbursements', 'count' => $expensePending['count'],       'amount' => $expensePending['amount'],       'tone' => 'info',    'url' => url('/expenseRequests')],
      ['label' => 'Loan requests',          'count' => $loanPending['count'],          'amount' => $loanPending['amount'],          'tone' => 'primary', 'url' => url('/loan')],
    ];
    $totalCashOut = array_sum(array_column($cashOutQueue, 'amount'));
    $totalCashOutCount = array_sum(array_column($cashOutQueue, 'count'));

    // ---- Payroll: this month's run + 12-month cost trend
    $payrollThisMonth = (float) Payslip::whereYear('created_at', now()->year)
      ->whereMonth('created_at', now()->month)->sum('net_salary');
    $payslipsPending  = Payslip::where('status', '!=', 'paid')
      ->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
    $monthlySalaryCost = (float) User::where('status', UserAccountStatus::ACTIVE)->sum('base_salary');

    $payrollTrendStart = now()->copy()->startOfMonth()->subMonths(11);
    $payrollByMonth = Payslip::where('created_at', '>=', $payrollTrendStart)
      ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') ym, SUM(net_salary) total")
      ->groupBy('ym')->pluck('total', 'ym');
    $payrollTrendChart = ['labels' => [], 'data' => []];
    for ($i = 0; $i < 12; $i++) {
      $m = $payrollTrendStart->copy()->addMonths($i);
      $payrollTrendChart['labels'][] = $m->format('M y');
      $payrollTrendChart['data'][]   = round((float) ($payrollByMonth[$m->format('Y-m')] ?? 0), 2);
    }

    // ---- Claim pipeline chart (count by stage, in workflow order)
    $claimPipelineChart = [
      'labels' => ['Draft', 'Submitted', 'Verified', 'Approved', 'Objection'],
      'data'   => [
        $claimStat('draft')['count'], $awaitingVerification['count'],
        $awaitingApproval['count'], $awaitingPayment['count'], $onObjection['count'],
      ],
    ];

    // ---- Approved WFH awaiting attendance marking (accounts owns this).
    // Per the owner's flow: once a WFH leave is approved, accounts marks the
    // attendance from the external tracking-software report.
    $wfhQueue = collect();
    try {
      $wfhType = \App\Models\LeaveType::where('code', 'WFH')->first();
      if ($wfhType) {
        $wfhQueue = LeaveRequest::with('user:id,first_name,last_name,code')
          ->where('leave_type_id', $wfhType->id)
          ->where('status', LeaveRequestStatus::APPROVED)
          ->whereDate('to_date', '>=', now()->subDays(30)->toDateString())
          ->orderByDesc('from_date')
          ->limit(10)
          ->get();
      }
    } catch (\Throwable $e) {
      $wfhQueue = collect();
    }

    // ---- Recent money movement (claims actually paid)
    $recentPayments = \App\Models\TravelClaim::with('user:id,first_name,last_name,code')
      ->whereNotNull('paid_at')
      ->orderByDesc('paid_at')
      ->limit(8)
      ->get();

    // ---- Top claimants this financial month
    $topClaimants = \App\Models\TravelClaim::with('user:id,first_name,last_name')
      ->where('claim_month', $thisMonthKey)
      ->selectRaw('user_id, SUM(COALESCE(net_payable, total_amount, 0)) amt, COUNT(*) c')
      ->groupBy('user_id')->orderByDesc('amt')->limit(6)->get();

    return view('tenant.users.dashboard.finance-index', [
        'recentNotices' => \App\Support\DashboardAnnouncements::forUser($user ?? auth()->user()),
      'importantContacts'   => \App\Services\HrNotificationService::importantContactsFor($user ?? auth()->user()),
      'pageConfigs'         => ['contentLayout' => 'wide'],
      'money'               => $money,
      'activeHeadcount'     => $active,
      'presentUsersCount'   => $presentUsersCount,
      'onLeaveUsersCount'   => $onLeaveUsersCount,
      'payrollThisMonth'    => $payrollThisMonth,
      'payslipsPending'     => $payslipsPending,
      'monthlySalaryCost'   => $monthlySalaryCost,
      'totalCashOut'        => $totalCashOut,
      'totalCashOutCount'   => $totalCashOutCount,
      'cashOutQueue'        => $cashOutQueue,
      'awaitingVerification'=> $awaitingVerification,
      'awaitingApproval'    => $awaitingApproval,
      'awaitingPayment'     => $awaitingPayment,
      'onObjection'         => $onObjection,
      'payrollTrendChart'   => $payrollTrendChart,
      'claimPipelineChart'  => $claimPipelineChart,
      'wfhQueue'            => $wfhQueue,
      'recentPayments'      => $recentPayments,
      'topClaimants'        => $topClaimants,
    ]);
  }

  /**
   * User ids whose monitoring data (live location, activity timeline,
   * device location) the caller may see:
   *   - admin / hr  → everyone (null, unrestricted)
   *   - manager     → their tagged team (direct reports via reporting_to_id) + self
   *   - anyone else → only themselves
   * Per the owner's instruction: live GPS/timeline of the whole company is
   * admin/HR only; a manager sees only their own team, never all staff.
   */
  private function monitoringAllowedIds($user): ?array
  {
    if (!$user) {
      return [0];
    }
    if ($user->hasRole(['admin', 'Admin', 'hr', 'HR'])) {
      return null; // unrestricted
    }
    if ($user->hasRole(['manager', 'Manager'])) {
      return User::where('reporting_to_id', $user->id)->notTerminated()->pluck('id')->push($user->id)->unique()->values()->all();
    }
    return [$user->id];
  }

  /** True when the caller may see this specific user's monitoring data. */
  private function canMonitorUser($user, $targetUserId): bool
  {
    $allowed = $this->monitoringAllowedIds($user);
    return $allowed === null || in_array((int) $targetUserId, $allowed, true);
  }

  /**
   * Count of distinct users genuinely at work today - present, late, WFH, a
   * credited worked day off, or a partial day - via the same
   * AttendanceDayStatus resolver every attendance page shares, instead of
   * matching today's Attendance rows against raw status text. $userIds
   * scopes to a specific set of users (e.g. a manager's team); null means
   * everyone.
   */
  private function countPresentToday(?array $userIds): int
  {
    $query = Attendance::whereDate('check_in_time', now())->with(['user', 'leaveRequest.leaveType']);
    if ($userIds !== null) {
      $query->whereIn('user_id', $userIds);
    }

    $count = 0;
    foreach ($query->get()->groupBy('user_id') as $userRows) {
      $best = $userRows->sortByDesc(fn($a) => ($a->check_in_time && $a->check_in_time->format('H:i') !== '00:00') ? 1 : 0)->first();
      $holiday = $best->check_in_time ? \App\Services\AttendanceDayStatus::holidayForUserOnDateSingle($best->user, $best->check_in_time->toDateString()) : null;
      if (\App\Services\AttendanceDayStatus::isPresentLikeRow($best, $holiday)) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Distinct present-like users per date across a range, keyed 'Y-m-d' - the
   * range version of countPresentToday(), for the 7-day trend charts. Same
   * resolver, batched by month to avoid a holiday lookup per row.
   */
  private function presentCountsByDate($startDate, $endDate, ?array $userIds): \Illuminate\Support\Collection
  {
    $query = Attendance::whereBetween('check_in_time', [$startDate, $endDate])->with(['user', 'leaveRequest.leaveType']);
    if ($userIds !== null) {
      $query->whereIn('user_id', $userIds);
    }

    $holidayMapsByMonth = [];
    $counts = [];
    foreach ($query->get()->groupBy(fn($a) => $a->check_in_time->toDateString()) as $dateStr => $dayRows) {
      $count = 0;
      foreach ($dayRows->groupBy('user_id') as $userRows) {
        $best = $userRows->sortByDesc(fn($a) => ($a->check_in_time && $a->check_in_time->format('H:i') !== '00:00') ? 1 : 0)->first();
        $d = $best->check_in_time;
        $monthKey = $d->year . '-' . $d->month;
        if (!isset($holidayMapsByMonth[$monthKey])) {
          $holidayMapsByMonth[$monthKey] = \App\Services\AttendanceDayStatus::holidayMapsForMonth($d->month, $d->year);
        }
        $holiday = \App\Services\AttendanceDayStatus::holidayForUserOnDate($holidayMapsByMonth[$monthKey], $best->user, $best->user->site_id, $dateStr);
        if (\App\Services\AttendanceDayStatus::isPresentLikeRow($best, $holiday)) {
          $count++;
        }
      }
      $counts[$dateStr] = $count;
    }

    return collect($counts);
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

      $allowedIds = $this->monitoringAllowedIds(auth()->user());
      $todayAttendances = Attendance::with(['user.userDevice', 'user.designation'])
        ->whereDate('created_at', today())
        ->when($allowedIds !== null, fn($q) => $q->whereIn('user_id', $allowedIds))
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
    // SECURITY: this first paint used to load EVERY active user and every
    // attendance row with no scoping, while cardViewAjax() (the auto-refresh)
    // scoped correctly — so a manager or monitoring delegate briefly saw the
    // whole company before the refresh silently narrowed it to their team.
    // Same allowed-id set as the refresh now applies to both.
    $allowedIds = $this->monitoringAllowedIds(auth()->user());

    $teamsList = Team::cachedActive();

    $attendances = Attendance::whereDate('created_at', now())
      ->when($allowedIds !== null, fn($q) => $q->whereIn('user_id', $allowedIds))
      ->with('attendanceLogs')
      ->get();

    $trackingHelper = new TrackingHelper();

    $users = User::where('status', '=', 'active')
      ->where('team_id', '!=', null)
      ->where('shift_id', '!=', null)
      ->when($allowedIds !== null, fn($q) => $q->whereIn('id', $allowedIds))
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
    $allowedIds = $this->monitoringAllowedIds(auth()->user());

    $teamsList = Team::cachedActive();


    $attendances = Attendance::whereDate('created_at', '=', now())
      ->when($allowedIds !== null, fn($q) => $q->whereIn('user_id', $allowedIds))
      ->get();

    $trackingHelper = new TrackingHelper();

    $users = User::where('status', '=', 'active')
      ->where('team_id', '!=', null)
      ->where('shift_id', '!=', null)
      ->when($allowedIds !== null, fn($q) => $q->whereIn('id', $allowedIds))
      ->get();

    $userDevices = UserDevice::whereIn('user_id', $users->pluck('id'))
      ->get();

    $cardItems = [];

    // Visits hang off attendance_log_id (see Api\VisitController::store —
    // attendance_id is never populated). This refresh used to group by
    // attendance_id and therefore always returned 0, so every card's visit
    // count silently changed the moment the first auto-refresh landed.
    // Now it matches the page-load query exactly.
    $allAttendanceLogIdsAjax = $attendances->flatMap(fn($a) => $a->attendanceLogs->pluck('id'))->all();
    $visitCountsAjax = empty($allAttendanceLogIdsAjax) ? collect() : Visit::whereIn('attendance_log_id', $allAttendanceLogIdsAjax)
        ->whereDate('created_at', '=', now())
        ->selectRaw('attendance_log_id, count(*) as count')
        ->groupBy('attendance_log_id')
        ->pluck('count', 'attendance_log_id');

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

        // Sum across every log of this attendance, exactly like the page load.
        $visitsCount = 0;
        foreach ($attendance->attendanceLogs->pluck('id') as $alId) {
          $visitsCount += $visitCountsAjax->get($alId, 0);
        }

        $cardItems[] = [
          'id' => $attendance->user->id,
          'name' => $attendance->user->first_name . ' ' . $attendance->user->last_name,
          // users.phone_number does not exist — the column is `phone`. The
          // page load already reads $user->phone; this refresh returned null.
          'phoneNumber' => $attendance->user->phone,
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
    // The per-user AJAX endpoints already abort(403) for out-of-scope ids,
    // but this picker listed EVERY active employee by name — so a manager
    // or monitoring delegate could still read the full staff roster off the
    // dropdown. Scoped to the same allowed set as the data behind it.
    $allowedIds = $this->monitoringAllowedIds(auth()->user());
    $employees = User::where('status', UserAccountStatus::ACTIVE)
      ->when($allowedIds !== null, fn($q) => $q->whereIn('id', $allowedIds))
      ->get();

    return view('tenant.dashboard.timeline_view', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'employees' => $employees
    ]);
  }

  public function getDeviceLocationAjax($userId, $date, $attendanceLogId = null)
  {
    if (!$this->canMonitorUser(auth()->user(), $userId)) {
      abort(403);
    }
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
    if (!$this->canMonitorUser(auth()->user(), $userId)) {
      abort(403);
    }
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
    if (!$this->canMonitorUser(auth()->user(), $userId)) {
      abort(403);
    }
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
    if (!$this->canMonitorUser(auth()->user(), $userId)) {
      abort(403);
    }
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
    // Scope to whoever the caller may monitor — this endpoint used to return
    // every department's headcount and attendance to anyone past the
    // monitoring gate, including a timeline-only delegate.
    $allowedIds = $this->monitoringAllowedIds(auth()->user());

    // Two bulk queries instead of one query per user (eliminates N×M×K N+1 problem)
    $totalByDept = User::where('status', UserAccountStatus::ACTIVE)
        ->when($allowedIds !== null, fn($q) => $q->whereIn('id', $allowedIds))
        ->select('department_id', DB::raw('count(*) as total'))
        ->whereNotNull('department_id')
        ->groupBy('department_id')
        ->pluck('total', 'department_id');

    $presentByDept = Attendance::whereDate('check_in_time', today())
        ->when($allowedIds !== null, fn($q) => $q->whereIn('attendances.user_id', $allowedIds))
        ->join('users', 'attendances.user_id', '=', 'users.id')
        ->select('users.department_id', DB::raw('count(*) as present_count'))
        ->whereNotNull('users.department_id')
        ->groupBy('users.department_id')
        ->pluck('present_count', 'department_id');

    $departments = Department::cachedActive();

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
