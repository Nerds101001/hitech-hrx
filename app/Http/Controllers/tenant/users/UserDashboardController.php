<?php

namespace App\Http\Controllers\tenant\users;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\DocumentRequest;
use App\Models\ExpenseRequest;
use App\Models\LeaveRequest;
use App\Models\LoanRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\SOSLog;
use App\Models\Visit;
use App\Models\LeaveType;
use App\Models\ExpenseType;
use App\Models\Holiday;
use App\Models\Notice;
use App\Models\Payslip;
use App\Models\Settings;
use App\Helpers\NotificationHelper;
use App\Notifications\Leave\NewLeaveRequest;
use App\Notifications\Expense\NewExpenseRequest;
use App\Services\LeavePolicyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Constants\Constants as AppConstants;

class UserDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $userStatus = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;

        // Redirect to Onboarding Form if status is Onboarding or Requested
        if (in_array($userStatus, [UserAccountStatus::ONBOARDING->value, UserAccountStatus::ONBOARDING_REQUESTED->value])) {
            return redirect()->route('onboarding.form');
        }

        // Show Training Portal if submitted + training required + training not yet completed
        if ($userStatus === UserAccountStatus::ONBOARDING_SUBMITTED->value) {
            if ($user->is_training_required && $user->training_status !== 'completed') {
                return redirect()->route('training.portal');
            }
            // Otherwise show the "Under Review" waiting screen
            return view('tenant.users.dashboard.review-restricted');
        }

        $isHR = $user->hasRole('hr');
        $isFieldEmployee = $user->hasRole('employee') || $user->hasRole('office_employee');
        $isManager = $user->hasRole('manager');

        // Common Personal Stats
        $myLeavesCount = LeaveRequest::where('user_id', $user->id)->count();
        $myExpensesCount = ExpenseRequest::where('user_id', $user->id)->count();
        $myAttendanceCount = Attendance::where('user_id', $user->id)->count();
        $mySOSCount = SOSLog::where('user_id', $user->id)->count();

        // --- Revamp Data Points ---
        $user = auth()->user();
        $nextHoliday = Holiday::where('date', '>=', now()->toDateString())
            ->where('status', 1)
            ->where(function ($q) use ($user) {
                $q->whereNull('site_id')
                  ->orWhere('site_id', $user->site_id);
            })
            ->orderBy('date', 'asc')
            ->first();

        $recentNotices = Notice::where('status', 1)
            ->where(function ($q) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now());
            })
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Team Out Today (Approved leaves for today)
        $teamOutTodayQuery = LeaveRequest::whereDate('from_date', '<=', now())
            ->whereDate('to_date', '>=', now())
            ->where('status', LeaveRequestStatus::APPROVED)
            ->with('user');

        // Scoping for Manager
        if ($isManager) {
            $teamOutTodayQuery->whereHas('user', function ($q) use ($user) {
                $q->where('team_id', $user->team_id);
            });
        }
        $teamOutToday = $teamOutTodayQuery->get();

        // Payroll Trend (Last 2 payslips comparison)
        $latestPayslips = Payslip::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->take(2)
            ->get();
        $payrollTrend = 0;
        $latestNetSalary = 0;
        if ($latestPayslips->count() >= 1) {
            $latestNetSalary = $latestPayslips[0]->net_salary;
            if ($latestPayslips->count() == 2 && $latestPayslips[1]->net_salary > 0) {
                $payrollTrend = (($latestPayslips[0]->net_salary - $latestPayslips[1]->net_salary) / $latestPayslips[1]->net_salary) * 100;
            }
        }
        // --------------------------

        // Global Stats (Needed for HR and Admin)
        $totalUser = User::count();
        $active = User::where('status', UserAccountStatus::ACTIVE)->count();
        $presentUsersCount = Attendance::whereDate('created_at', now())->count();
        
        // Pending Requests (All for now, could be scoped to team for manager)
        $pendingLeaveRequests = LeaveRequest::where('status', 'pending')->count();
        $pendingExpenseRequests = ExpenseRequest::where('status', 'pending')->count();

        // 1. HR/Admin Dashboard (First Priority)
        if ($isHR || $user->hasRole('admin')) {
            // HR-specific calculations
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

            $todayHours = Attendance::whereDate('created_at', now())
                ->where('check_out_time', '!=', null)
                ->get()
                ->sum(function ($attendance) {
                    return $attendance->check_in_time->diffInMinutes($attendance->check_out_time);
            });

            $onLeaveUsersCount = LeaveRequest::whereDate('from_date', now())
                ->where('status', LeaveRequestStatus::APPROVED)
                ->count();

            // Data for Onboarding Modal in Dashboard
            $departments = \App\Models\Department::where('status', \App\Enums\Status::ACTIVE)->get();
            $roles = \Spatie\Permission\Models\Role::all();
            $designations = \App\Models\Designation::where('status', \App\Enums\Status::ACTIVE)->get();
            $managers = \App\Models\User::whereHas('roles', function($q) {
                $q->whereIn('name', ['admin', 'hr', 'manager', 'accounts']);
            })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->get();

            return view('tenant.users.dashboard.hr-index', [
                'totalUser' => $totalUser,
                'activeEmployees' => $active,
                'active' => $active,
                'presentUsersCount' => $presentUsersCount,
                'pendingLeaveRequests' => $pendingLeaveRequests,
                'pendingExpenseRequests' => $pendingExpenseRequests,
                'pendingDocumentRequests' => DocumentRequest::where('status', 'pending')->count(),
                'pendingLoanRequests' => LoanRequest::where('status', 'pending')->count(),
                'thisWeekWorkingHours' => round($thisWeekWorkingHours, 2),
                'todayHours' => round($todayHours, 2),
                'tasks' => Task::where('status', 'new')->count(),
                'onGoingTasks' => Task::where('status', 'in_progress')->count(),
                'todayPresentUsers' => $presentUsersCount,
                'todayAbsentUsers' => $active - $presentUsersCount,
                'presentUsersCountLastWeek' => $presentUsersCountLastWeek,
                'absentUsersCountLastWeek' => $active - $presentUsersCountLastWeek,
                'onLeaveUsersCount' => $onLeaveUsersCount,
                'isSelfService' => false,
                'myLeavesCount' => $myLeavesCount,
                'myExpensesCount' => $myExpensesCount,
                'mySOSCount' => $mySOSCount,
                'nextHoliday' => $nextHoliday,
                'recentNotices' => $recentNotices,
                'teamOutToday' => $teamOutToday,
                'payrollTrend' => $payrollTrend,
                'latestNetSalary' => $latestNetSalary,
                'departments' => $departments,
                'roles' => $roles,
                'designations' => $designations,
                'managers' => $managers
            ]);
        }

        // 2. Employee Dashboard — also catches users with NO role assigned yet
        // (accounts-dept bulk role assignment issue: anyone not explicitly admin/hr/manager/accounts gets employee view)
        $isPrivilegedRole = $user->hasRole(['admin', 'hr', 'accounts', 'manager']);
        if ($isFieldEmployee || (!$isPrivilegedRole && !$isManager)) {
            $settings = Settings::first();
            
            // Celebrations logic
            $todayMd = now()->format('md');
            
            // Birthdays
            $allBirthdays = User::whereNotNull('dob')
                ->where('status', UserAccountStatus::ACTIVE)
                ->orderByRaw("CASE WHEN DATE_FORMAT(dob, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
                ->orderByRaw("DATE_FORMAT(dob, '%m%d') ASC")
                ->take(10)
                ->get();
            
            $todayBirthdays = $allBirthdays->filter(fn($u) => Carbon::parse($u->dob)->format('md') === $todayMd);
            $upcomingBirthdays = $allBirthdays->filter(fn($u) => Carbon::parse($u->dob)->format('md') !== $todayMd)->take(3);

            // Anniversaries
            $allAnniversaries = User::whereNotNull('date_of_joining')
                ->where('status', UserAccountStatus::ACTIVE)
                ->orderByRaw("CASE WHEN DATE_FORMAT(date_of_joining, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
                ->orderByRaw("DATE_FORMAT(date_of_joining, '%m%d') ASC")
                ->take(10)
                ->get();

            $todayAnniversaries = $allAnniversaries->filter(fn($u) => Carbon::parse($u->date_of_joining)->format('md') === $todayMd);
            $upcomingAnniversaries = $allAnniversaries->filter(fn($u) => Carbon::parse($u->date_of_joining)->format('md') !== $todayMd)->take(3);

            return view('tenant.users.dashboard.employee-index', compact(
                'myLeavesCount',
                'myExpensesCount',
                'myAttendanceCount',
                'mySOSCount',
                'nextHoliday',
                'recentNotices',
                'payrollTrend',
                'latestNetSalary',
                'settings',
                'todayBirthdays',
                'upcomingBirthdays',
                'todayAnniversaries',
                'upcomingAnniversaries'
            ));
        }


        // Global Stats (Needed for Manager and HR)
        $totalUser = User::count();
        $active = User::where('status', UserAccountStatus::ACTIVE)->count();
        $presentUsersCount = Attendance::whereDate('created_at', now())->count();
        
        // Pending Requests (All for now, could be scoped to team for manager)
        $pendingLeaveRequests = LeaveRequest::where('status', 'pending')->count();
        $pendingExpenseRequests = ExpenseRequest::where('status', 'pending')->count();

        // 2. Manager Dashboard
        if ($isManager) {
            // Get Subordinates (Direct Reports)
            $teamMembers = User::where('reporting_to_id', $user->id)
                ->where('status', UserAccountStatus::ACTIVE)
                ->get();
            
            $teamMemberIds = $teamMembers->pluck('id')->toArray();

            // Override global counts for the view to ensure "Team Only" view
            $active = count($teamMemberIds);
            $totalUser = $active;

            // Scoped Pending Requests
            $pendingLeaveRequests = LeaveRequest::whereIn('user_id', $teamMemberIds)
                ->where('status', 'pending')
                ->count();
            $pendingExpenseRequests = ExpenseRequest::whereIn('user_id', $teamMemberIds)
                ->where('status', 'pending')
                ->count();

            // Daily Digest Stats (Present/Absent/OnLeave)
            $todayPresentCount = Attendance::whereIn('user_id', $teamMemberIds)
                ->whereDate('check_in_time', now())
                ->count();
            
            $todayOnLeaveCount = LeaveRequest::whereIn('user_id', $teamMemberIds)
                ->whereDate('from_date', '<=', now())
                ->whereDate('to_date', '>=', now())
                ->where('status', LeaveRequestStatus::APPROVED)
                ->count();
            
            $todayAbsentCount = count($teamMemberIds) - $todayPresentCount - $todayOnLeaveCount;
            if ($todayAbsentCount < 0) $todayAbsentCount = 0;

            $todayPresentUsersList = Attendance::whereIn('user_id', $teamMemberIds)
                ->whereDate('check_in_time', now())
                ->with('user')
                ->get();

            // Team Birthdays & Anniversaries (Next 30 days)
            $teamBirthdays = User::whereIn('id', $teamMemberIds)
                ->whereMonth('dob', '>=', now()->month)
                ->orderByRaw('MONTH(dob), DAY(dob)')
                ->limit(5)
                ->get();
            
            $pendingDocumentRequests = DocumentRequest::whereIn('user_id', $teamMemberIds)
                ->where('status', 'pending')
                ->count();
            
            $pendingLoanRequests = LoanRequest::whereIn('user_id', $teamMemberIds)
                ->where('status', 'pending')
                ->count();

            // MNC Grade: Org Celebrations (Split Today vs Upcoming)
            $todayMd = now()->format('md');
            
            // Birthdays
            $allBirthdays = User::whereNotNull('dob')
                ->where('status', UserAccountStatus::ACTIVE)
                ->orderByRaw("CASE WHEN DATE_FORMAT(dob, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
                ->orderByRaw("DATE_FORMAT(dob, '%m%d') ASC")
                ->take(15) // Get broad set
                ->get();
            
            $todayBirthdays = $allBirthdays->filter(fn($u) => Carbon::parse($u->dob)->format('md') === $todayMd);
            $upcomingBirthdays = $allBirthdays->filter(fn($u) => Carbon::parse($u->dob)->format('md') !== $todayMd)->take(2);

            // Anniversaries
            $allAnniversaries = User::whereNotNull('date_of_joining')
                ->where('status', UserAccountStatus::ACTIVE)
                ->orderByRaw("CASE WHEN DATE_FORMAT(date_of_joining, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
                ->orderByRaw("DATE_FORMAT(date_of_joining, '%m%d') ASC")
                ->take(15)
                ->get();

            $todayAnniversaries = $allAnniversaries->filter(fn($u) => Carbon::parse($u->date_of_joining)->format('md') === $todayMd);
            $upcomingAnniversaries = $allAnniversaries->filter(fn($u) => Carbon::parse($u->date_of_joining)->format('md') !== $todayMd)->take(2);

            return view('tenant.users.dashboard.manager-index', [
                'pendingLeaveRequests' => $pendingLeaveRequests,
                'pendingExpenseRequests' => $pendingExpenseRequests,
                'pendingDocumentRequests' => $pendingDocumentRequests,
                'pendingLoanRequests' => $pendingLoanRequests,
                'activeEmployees' => $active,
                'todayPresentUsers' => $todayPresentCount,
                'todayOnLeaveCount' => $todayOnLeaveCount,
                'todayAbsentUsers' => $todayAbsentCount,
                'myLeavesCount' => $myLeavesCount,
                'myExpensesCount' => $myExpensesCount,
                'mySOSCount' => $mySOSCount,
                'nextHoliday' => $nextHoliday,
                'recentNotices' => $recentNotices,
                'teamOutToday' => $teamOutToday,
                'teamBirthdays' => $teamBirthdays,
                'todayBirthdays' => $todayBirthdays,
                'upcomingBirthdays' => $upcomingBirthdays,
                'todayAnniversaries' => $todayAnniversaries,
                'upcomingAnniversaries' => $upcomingAnniversaries,
                'payrollTrend' => $payrollTrend,
                'latestNetSalary' => $latestNetSalary,
                'departments' => \App\Models\Department::where('status', \App\Enums\Status::ACTIVE)->get(),
                'roles' => \Spatie\Permission\Models\Role::all(),
                'designations' => \App\Models\Designation::where('status', \App\Enums\Status::ACTIVE)->get(),
                'managers' => \App\Models\User::whereHas('roles', function($q) {
                    $q->whereIn('name', ['admin', 'hr', 'manager', 'accounts']);
                })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->get()
            ]);
        }

        // 3. Admin / Default Dashboard (Full View)
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

        $todayHours = Attendance::whereDate('created_at', now())
            ->where('check_out_time', '!=', null)
            ->get()
            ->sum(function ($attendance) {
                return $attendance->check_in_time->diffInMinutes($attendance->check_out_time);
        });

        $onLeaveUsersCount = LeaveRequest::whereDate('from_date', now())
            ->where('status', LeaveRequestStatus::APPROVED)
            ->count();

        return view('tenant.users.dashboard.index', [
            'totalUser' => $totalUser,
            'activeEmployees' => $active,
            'active' => $active,
            'presentUsersCount' => $presentUsersCount,
            'pendingLeaveRequests' => $pendingLeaveRequests,
            'pendingExpenseRequests' => $pendingExpenseRequests,
            'pendingDocumentRequests' => DocumentRequest::where('status', 'pending')->count(),
            'pendingLoanRequests' => LoanRequest::where('status', 'pending')->count(),
            'thisWeekWorkingHours' => round($thisWeekWorkingHours, 2),
            'todayHours' => round($todayHours, 2),
            'tasks' => Task::where('status', 'new')->count(),
            'onGoingTasks' => Task::where('status', 'in_progress')->count(),
            'todayPresentUsers' => $presentUsersCount,
            'todayAbsentUsers' => $active - $presentUsersCount,
            'presentUsersCountLastWeek' => $presentUsersCountLastWeek,
            'absentUsersCountLastWeek' => $active - $presentUsersCountLastWeek,
            'onLeaveUsersCount' => $onLeaveUsersCount,
            'isSelfService' => false,
            'myLeavesCount' => $myLeavesCount,
            'myExpensesCount' => $myExpensesCount,
            'mySOSCount' => $mySOSCount,
            'nextHoliday' => $nextHoliday,
            'recentNotices' => $recentNotices,
            'teamOutToday' => $teamOutToday,
            'payrollTrend' => $payrollTrend,
            'latestNetSalary' => $latestNetSalary
        ]);
    }

    public function leaveIndex()
    {
        $user = auth()->user();
        $leaves = LeaveRequest::where('user_id', $user->id)
            ->with('leaveType')
            ->orderByRaw('CASE WHEN DATE(from_date) < DATE(created_at) THEN 0 ELSE 1 END')
            ->orderBy('id', 'desc')
            ->get();
        
        $gender = strtolower(trim($user->gender ?? ''));
        $maritalStatus = strtolower(trim($user->marital_status ?? ''));
        $isMarried = ($maritalStatus === 'married');
        
        $leaveTypes = LeaveType::where('status', \App\Enums\Status::ACTIVE)
            ->where(function ($q) use ($user) {
                $q->whereNull('site_id')
                  ->orWhere('site_id', $user->site_id);
            })
            ->get()
            ->filter(function($type) use ($gender, $isMarried) {
                $code = strtoupper($type->code);
                
                // Maternity - Only for Married Females
                if ($code === 'MAT' || $code === 'ML') {
                    return $isMarried && $gender === 'female';
                }
                
                // Paternity - Only for Married Males
                if ($code === 'PAT' || $code === 'PL_PAT') {
                    return $isMarried && $gender === 'male';
                }
                
                return true;
            })->values();

        $leaveBalances = $user->leaveBalances()->with('leaveType')->get();
        $settings = \App\Models\Settings::first();

        // Use the centralized LeaveHistoryService for a unified history across all pages
        $leaves = \App\Services\LeaveHistoryService::getUnifiedHistory($user);

        return view('tenant.users.leaves.index', compact('leaves', 'leaveTypes', 'settings', 'leaveBalances'));
    }

    public function leaveStore(Request $request)
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date'     => 'required|date',
            'to_date'       => 'required|date|after_or_equal:from_date',
            'user_notes'    => 'required|string|max:1000',
            'document'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'is_half_day'   => 'nullable|boolean',
            'half_day_session' => 'nullable|string|in:first_half,second_half',
        ]);

        $leaveType = LeaveType::find($validated['leave_type_id']);

        // HDL (Half Day Leave) always forces half-day regardless of form value
        $isHdl     = $leaveType && strtoupper($leaveType->code) === 'HDL';
        $isHalfDay = $isHdl ? true : $request->boolean('is_half_day');

        if ($isHalfDay) {
            $validated['to_date'] = $validated['from_date'];
        }

        // HDL must have a session; default to first_half if omitted
        if ($isHdl && empty($validated['half_day_session'])) {
            $validated['half_day_session'] = 'first_half';
        }
        $user = auth()->user();

        // 1. Gender Restriction Check
        $code = strtoupper($leaveType->code);
        if (in_array($code, ['MAT', 'ML']) && strtolower($user->gender ?? '') !== 'female') {
            return redirect()->back()->withErrors(['policy' => 'Maternity leave is only applicable for female employees.'])->withInput();
        }
        if (in_array($code, ['PAT', 'PL_PAT']) && strtolower($user->gender ?? '') !== 'male') {
            return redirect()->back()->withErrors(['policy' => 'Paternity leave is only applicable for male employees.'])->withInput();
        }

        // 2. Evidence/Proof Requirement Check
        if (($leaveType->is_proof_required || in_array($code, ['MAT', 'ML', 'PAT', 'PL_PAT'])) && !$request->hasFile('document')) {
            return redirect()->back()->withErrors(['document' => 'Proof/Evidence document is required for this leave type.'])->withInput();
        }

        // 3. Backdated Check (Limit: 30 Days)
        $fromDateObj = \Carbon\Carbon::parse($validated['from_date']);
        $todayObj = \Carbon\Carbon::today();
        if ($fromDateObj->lt($todayObj)) {
            $daysBack = $fromDateObj->diffInDays($todayObj);
            if ($daysBack > 30) {
                return redirect()->back()->withErrors(['from_date' => 'You cannot apply for leave more than 30 days in the past.'])->withInput();
            }
        }

        // Unit leave policy enforcement
        $error = LeavePolicyService::validate(
            auth()->user(),
            $validated['leave_type_id'],
            $validated['from_date'],
            $validated['to_date'],
            null,
            $isHalfDay
        );
        if ($error) {
            return redirect()->back()->withErrors(['policy' => $error])->withInput();
        }

        $leaveRequest = new LeaveRequest();
        $leaveRequest->user_id          = auth()->id();
        $leaveRequest->leave_type_id    = $validated['leave_type_id'];
        $leaveRequest->from_date        = $validated['from_date'];
        $leaveRequest->to_date          = $validated['to_date'];
        $leaveRequest->user_notes       = $validated['user_notes'];
        $leaveRequest->status           = LeaveRequestStatus::PENDING;
        $leaveRequest->is_half_day      = $isHalfDay;
        $leaveRequest->half_day_session = $isHalfDay ? ($validated['half_day_session'] ?? 'first_half') : null;

        // LWP Detection — check if balance is insufficient at submission time
        $impact = LeavePolicyService::getBalanceImpact($user, $validated['leave_type_id'], $validated['from_date'], $validated['to_date'], $isHalfDay);
        $workingDays = LeavePolicyService::calculateWorkingDays($user, $validated['leave_type_id'], $validated['from_date'], $validated['to_date'], $isHalfDay);
        $availableBalance = $impact['available'] ?? 0;
        if ($leaveType->is_paid && $availableBalance < $workingDays) {
            $lwpDays = max(0, $workingDays - $availableBalance);
            $leaveRequest->is_lwp   = true;
            $leaveRequest->lwp_days = $lwpDays;
        }

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->storeAs(AppConstants::BaseFolderLeaveRequestDocument, $fileName, 'public');
            $leaveRequest->document = $fileName;
        }

        $leaveRequest->save();

        NotificationHelper::notifyAdminHR(new NewLeaveRequest($leaveRequest));

        return redirect()->back()->with('success', 'Leave request submitted successfully.');
    }

    /**
     * Check if the user has an attendance record for the given date.
     * Used by the Half Day Leave (HDL) form to warn if the other half isn't covered.
     */
    public function leaveAttendanceCheck(Request $request)
    {
        $request->validate([
            'date'    => 'required|date',
            'session' => 'required|in:first_half,second_half',
        ]);

        $user    = auth()->user();
        $date    = $request->input('date');
        $session = $request->input('session');

        $attendance = \App\Models\Attendance::where('user_id', $user->id)
            ->whereDate('check_in_time', $date)
            ->first();

        if (!$attendance) {
            return response()->json([
                'present'  => false,
                'warning'  => 'No attendance record found for this date. If you were absent the entire day, please apply for a full-day leave instead.',
            ]);
        }

        $checkIn  = $attendance->check_in_time;
        $noon     = \Carbon\Carbon::parse($date)->setTime(13, 0); // 1 PM cutoff

        if ($session === 'second_half') {
            // Taking afternoon off — check if they came in the morning
            $presentMorning = $checkIn && $checkIn->lt($noon);
            return response()->json([
                'present' => $presentMorning,
                'warning' => $presentMorning ? null
                    : 'You don\'t appear to have a morning check-in on this date. Please verify before submitting.',
            ]);
        } else {
            // Taking morning off — they should have checked in this afternoon
            $presentAfternoon = $checkIn && $checkIn->gte($noon);
            return response()->json([
                'present' => $presentAfternoon,
                'warning' => $presentAfternoon ? null
                    : 'No afternoon check-in found. If you were absent all day, consider a full-day leave.',
            ]);
        }
    }

    public function leaveCheckAjax(Request $request)
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date'     => 'required|date',
            'to_date'       => 'required|date|after_or_equal:from_date',
            'is_half_day'   => 'nullable|boolean',
            'half_day_session' => 'nullable|string|in:first_half,second_half',
        ]);

        $user = auth()->user();
        $isHalfDay = $request->boolean('is_half_day');
        if ($isHalfDay) {
            $validated['to_date'] = $validated['from_date'];
        }
        
        $conflicts = LeavePolicyService::checkConflicts($user, $validated['from_date'], $validated['to_date']);
        $impact = LeavePolicyService::getBalanceImpact($user, $validated['leave_type_id'], $validated['from_date'], $validated['to_date'], $isHalfDay);

        return response()->json([
            'success'   => true,
            'conflicts' => $conflicts,
            'impact'    => $impact,
        ]);
    }

    public function expenseIndex()
    {
        $expenses = ExpenseRequest::where('user_id', auth()->id())->with('expenseType')->orderBy('id', 'desc')->get();
        $expenseTypes = ExpenseType::where('status', 1)->get();
        $settings = Settings::first();
        return view('tenant.users.expenses.index', compact('expenses', 'expenseTypes', 'settings'));
    }

    public function expenseStore(Request $request)
    {
        $validated = $request->validate([
            'expense_type_id' => 'required|exists:expense_types,id',
            'for_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'remarks' => 'required|string|max:1000',
            'file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        $expenseRequest = new ExpenseRequest();
        $expenseRequest->user_id = auth()->id();
        $expenseRequest->expense_type_id = $validated['expense_type_id'];
        $expenseRequest->for_date = $validated['for_date'];
        $expenseRequest->amount = $validated['amount'];
        $expenseRequest->remarks = $validated['remarks'];
        $expenseRequest->status = 'pending';

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            Storage::disk('public')->putFileAs(AppConstants::BaseFolderExpenseProofs, $file, $fileName);
            $expenseRequest->document_url = $fileName;
        }

        $expenseRequest->save();

        NotificationHelper::notifyHRAndAdminOnly(new NewExpenseRequest($expenseRequest));

        return redirect()->back()->with('success', 'Expense request submitted successfully.');
    }

    public function attendanceIndex(Request $request)
    {
        $user = auth()->user();

        // Filter Logic
        $filter = $request->input('filter', 'this_month');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        if ($request->has('month') && $request->has('year')) {
            $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $filter = 'custom_month'; // Internal flag
        } elseif ($filter === 'today') {
            $periodStart = now()->startOfDay();
            $periodEnd = now()->endOfDay();
        } elseif ($filter === 'this_week') {
            $periodStart = now()->startOfWeek();
            $periodEnd = now()->endOfWeek();
        } elseif ($filter === 'this_month') {
            $periodStart = now()->startOfMonth();
            $periodEnd = now()->endOfMonth();
        } elseif ($filter === 'last_month') {
            $periodStart = now()->subMonth()->startOfMonth();
            $periodEnd = now()->subMonth()->endOfMonth();
        } elseif ($filter === 'custom' && $startDate && $endDate) {
            $periodStart = Carbon::parse($startDate)->startOfDay();
            $periodEnd = Carbon::parse($endDate)->endOfDay();
        } else {
            $periodStart = now()->startOfMonth();
            $periodEnd = now()->endOfMonth();
        }

        // Auto-offset Sunday working against absent/unpaid leaves in the target month
        try {
            \App\Services\AttendanceAdjustmentService::autoOffsetSundayWorking($user, $periodStart->month, $periodStart->year);
        } catch (\Exception $e) {
            \Log::error("Failed to run auto-offset for user dashboard: " . $e->getMessage());
        }

        // Fetch DB Attendances
        $attendancesDb = Attendance::where('user_id', $user->id)
            ->where(function($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('check_in_time', [$periodStart, $periodEnd])
                  ->orWhereBetween('created_at', [$periodStart, $periodEnd]);
            })->get();
            
        // Fetch Approved Leaves
        $leavesDb = LeaveRequest::where('user_id', $user->id)
            ->where('status', LeaveRequestStatus::APPROVED)
            ->where(function($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('from_date', [$periodStart, $periodEnd])
                  ->orWhereBetween('to_date', [$periodStart, $periodEnd])
                  ->orWhere(function($q2) use ($periodStart, $periodEnd) {
                      $q2->where('from_date', '<=', $periodStart)
                         ->where('to_date', '>=', $periodEnd);
                  });
            })
            ->with('leaveType')
            ->get();

        // Fetch Holidays
        $holidaysDb = Holiday::where('status', 1)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where(function($q) use ($user) {
                $q->whereNull('site_id')
                  ->orWhere('site_id', $user->site_id);
            })
            ->get();

        $presentDays = 0;
        $lateDays = 0;
        $absentDays = 0;
        $totalHours = 0;
        $workCount = 0;

        $dailyLogs = [];

        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
            $dateStr = $date->toDateString();
            $isWorkingDay = \App\Services\LeavePolicyService::isWorkingDay($user, $date);

            $dayData = [
                'created_at' => $date->copy(),
                'status' => 'Missing',
                'dynamic_status' => 'missing',
                'check_in_time' => null,
                'check_out_time' => null,
                'admin_reason' => null,
                'holiday_name' => null
            ];

            // 1. Check Holiday
            $holiday = $holidaysDb->first(function($h) use ($dateStr) {
                return Carbon::parse($h->date)->toDateString() === $dateStr;
            });

            // 2. Check Attendance
            $attendance = $attendancesDb->first(function($a) use ($dateStr) {
                return ($a->check_in_time && $a->check_in_time->toDateString() === $dateStr) || 
                       (!$a->check_in_time && $a->created_at->toDateString() === $dateStr);
            });

            // 3. Check Leave
            $leave = $leavesDb->first(function($l) use ($dateStr) {
                return Carbon::parse($l->from_date)->toDateString() <= $dateStr && 
                       Carbon::parse($l->to_date)->toDateString() >= $dateStr;
            });

            if ($holiday) {
                $dayData['status'] = 'Holiday';
                $dayData['dynamic_status'] = 'holiday';
                $dayData['holiday_name'] = $holiday->name;
            } elseif ($attendance && strtolower($attendance->status) !== 'absent') {
                $dayData['check_in_time'] = $attendance->check_in_time;
                $dayData['check_out_time'] = $attendance->check_out_time;
                $dayData['admin_reason'] = $attendance->admin_reason;

                $s = strtolower($attendance->status ?: 'present');
                
                // Dynamic enforcement of 7:45 threshold rule (465 mins)
                if (empty($attendance->admin_reason) && $attendance->check_in_time && $attendance->check_out_time) {
                    $mins = $attendance->check_in_time->diffInMinutes($attendance->check_out_time);
                    if ($mins < 465 && $s === 'present') {
                        $s = 'half-day';
                    }
                }

                $dayData['dynamic_status'] = $s;

                if ($s === 'late' || $s === 'half-day') {
                    $dayData['status'] = ($s === 'late') ? 'Late' : 'Half Day';
                    $lateDays++;
                } elseif (in_array($s, ['work_from_home', 'wfh'])) {
                    $dayData['status'] = 'WFH';
                    $presentDays++;
                } else {
                    $dayData['status'] = 'Present';
                    $presentDays++;
                }

                if ($attendance->check_in_time && $attendance->check_out_time) {
                    $totalHours += $attendance->check_in_time->diffInMinutes($attendance->check_out_time) / 60;
                    $workCount++;
                }

            } elseif ($leave) {
                $dayData['status'] = 'On Leave';
                $dayData['dynamic_status'] = 'on_leave';
                $dayData['holiday_name'] = $leave->leaveType->name ?? 'Approved Leave';
            } elseif (!$isWorkingDay) {
                $dayData['status'] = 'Weekly Off';
                $dayData['dynamic_status'] = 'weekly_off';
            } elseif ($date->isPast() && !$date->isToday()) {
                $dayData['status'] = 'Absent';
                $dayData['dynamic_status'] = 'absent';
                $absentDays++;
            } elseif ($date->isToday()) {
                $dayData['status'] = 'Today';
                $dayData['dynamic_status'] = 'today';
                if ($attendance && strtolower($attendance->status) === 'absent') {
                    $dayData['status'] = 'Absent';
                    $dayData['dynamic_status'] = 'absent';
                    $absentDays++;
                }
            } else {
                $dayData['status'] = 'Scheduled';
                $dayData['dynamic_status'] = 'scheduled';
            }

            // We push to the top so the list shows newest dates first
            array_unshift($dailyLogs, (object) $dayData);
        }

        $avgHours = $workCount > 0 ? round($totalHours / $workCount, 1) : 0;
        $attendances = collect($dailyLogs);

        return view('tenant.users.attendance.index', compact(
            'attendances', 
            'presentDays', 
            'lateDays', 
            'absentDays', 
            'avgHours',
            'filter',
            'startDate',
            'endDate',
            'month',
            'year'
        ));
    }

    public function sosIndex()
    {
        $sosLogs = SOSLog::where('user_id', auth()->id())->orderBy('id', 'desc')->get();
        return view('tenant.users.sos.index', compact('sosLogs'));
    }

    public function visitIndex()
    {
        $visits = Visit::where('created_by_id', auth()->id())->orderBy('id', 'desc')->get();
        return view('tenant.users.visits.index', compact('visits'));
    }
    public function attendanceRegistryAjax(Request $request)
    {
        $user = auth()->user();
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);
        
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;
        
        // Auto-offset Sunday working against absent/unpaid leaves in the target month
        try {
            \App\Services\AttendanceAdjustmentService::autoOffsetSundayWorking($user, $month, $year);
        } catch (\Exception $e) {
            \Log::error("Failed to run auto-offset for user registry: " . $e->getMessage());
        }

        // Fetch Attendance for the month
        $attendances = Attendance::where('user_id', $user->id)
            ->whereMonth('check_in_time', $month)
            ->whereYear('check_in_time', $year)
            ->with(['updatedBy'])
            ->get();
            
        // Fetch Approved Leaves for the month
        $leaves = LeaveRequest::where('user_id', $user->id)
            ->where('status', LeaveRequestStatus::APPROVED)
            ->where(function($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('from_date', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('to_date', [$startOfMonth, $endOfMonth])
                  ->orWhere(function($q2) use ($startOfMonth, $endOfMonth) {
                      $q2->where('from_date', '<=', $startOfMonth)
                         ->where('to_date', '>=', $endOfMonth);
                  });
            })
            ->with('leaveType')
            ->get();
            
        // Fetch Holidays for the month
        $holidays = Holiday::where('status', 1)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->where(function($q) use ($user) {
                $q->whereNull('site_id')
                  ->orWhere('site_id', $user->site_id);
            })
            ->get();

        $calendarData = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateObj = Carbon::create($year, $month, $day);
            $dateStr = $dateObj->toDateString();
            
            $dayData = [
                'day' => $day,
                'date' => $dateStr,
                'status' => 'Missing',
                'icon' => 'bx-help-circle',
                'class' => 'bg-light text-muted',
                'in' => null,
                'out' => null,
                'duration' => null,
                'is_working_day' => LeavePolicyService::isWorkingDay($user, $dateObj),
                'holiday_name' => null
            ];
            
            // Check for Holiday
            $holiday = $holidays->first(function($h) use ($dateStr) {
                return Carbon::parse($h->date)->toDateString() === $dateStr;
            });
            if ($holiday) {
                $dayData['status'] = 'Holiday';
                $dayData['holiday_name'] = $holiday->name;
                $dayData['icon'] = 'bx-star';
                $dayData['class'] = 'bg-info bg-opacity-25 text-info border-info border-opacity-50';
                $calendarData[$day] = $dayData;
                continue;
            }
            
            // Check for Attendance
            $attendance = $attendances->first(function($a) use ($dateStr) {
                return $a->check_in_time->toDateString() === $dateStr;
            });
            
            if ($attendance) {
                $dayData['in'] = $attendance->check_in_time->format('h:i A');
                $dayData['out'] = $attendance->check_out_time ? $attendance->check_out_time->format('h:i A') : '--:--';
                
                if ($attendance->check_in_time && $attendance->check_out_time) {
                    $diff = $attendance->check_in_time->diff($attendance->check_out_time);
                    $dayData['duration'] = sprintf('%d:%02dh', $diff->h, $diff->i);
                }

                $s = strtolower($attendance->status ?: 'present');
                
                // Dynamic threshold check (465 mins)
                if (empty($attendance->admin_reason) && $attendance->check_in_time && $attendance->check_out_time) {
                    $mins = $attendance->check_in_time->diffInMinutes($attendance->check_out_time);
                    if ($mins < 465 && $s === 'present') {
                        $s = 'half-day';
                    }
                }

                switch($s) {
                    case 'present':
                        $dayData['status'] = 'Present';
                        $dayData['icon'] = 'bx-check-circle';
                        $dayData['class'] = 'bg-teal text-white border-0';
                        break;
                    case 'late':
                    case 'half-day':
                        $dayData['status'] = 'Late';
                        $dayData['icon'] = 'bx-time-five';
                        $dayData['class'] = 'bg-orange text-white border-0';
                        break;
                    case 'absent':
                        $dayData['status'] = 'Absent';
                        $dayData['icon'] = 'bx-x-circle';
                        $dayData['class'] = 'bg-red text-white border-0';
                        break;
                    case 'work_from_home':
                    case 'wfh':
                        $dayData['status'] = 'WFH';
                        $dayData['icon'] = 'bx-home';
                        $dayData['class'] = 'bg-indigo-vibrant text-white border-0';
                        break;
                }
            } else {
                // Check for Leaves
                $leave = $leaves->first(function($l) use ($dateStr) {
                    return Carbon::parse($l->from_date)->toDateString() <= $dateStr && 
                           Carbon::parse($l->to_date)->toDateString() >= $dateStr;
                });
                
                if ($leave) {
                    $dayData['status'] = 'Leave';
                    $dayData['icon'] = 'bx-calendar';
                    $dayData['class'] = 'bg-purple-vibrant text-white border-0';
                    $dayData['holiday_name'] = $leave->leaveType->name ?? 'Approved Leave';
                } elseif (!$dayData['is_working_day']) {
                    $dayData['status'] = 'Weekly Off';
                    $dayData['class'] = 'bg-secondary bg-opacity-10 text-muted';
                    $dayData['icon'] = 'bx-calendar-minus';
                } elseif ($dateObj->isFuture() && !$dateObj->isToday()) {
                    $dayData['status'] = 'Scheduled';
                    $dayData['icon'] = 'bx-calendar-event';
                    $dayData['class'] = 'bg-white border text-muted opacity-50';
                    $dayData['holiday_name'] = 'Upcoming';
                } elseif ($dateObj->isPast() && !$dateObj->isToday()) {
                    $dayData['status'] = 'Absent';
                    $dayData['icon'] = 'bx-x-circle';
                    $dayData['class'] = 'bg-red text-white border-0';
                    $dayData['holiday_name'] = 'No Log Found';
                } else {
                    // Today, no log yet
                    $dayData['status'] = 'Today';
                    $dayData['icon'] = 'bx-time';
                    $dayData['class'] = 'bg-white border-primary border-dashed text-primary';
                }
            }
            
            $calendarData[$day] = $dayData;
        }

        return response()->json([
            'success' => true,
            'daysInMonth' => $daysInMonth,
            'calendar' => $calendarData,
            'monthName' => $startOfMonth->format('F'),
            'year' => $year,
            'month' => $month
        ]);
    }
}
