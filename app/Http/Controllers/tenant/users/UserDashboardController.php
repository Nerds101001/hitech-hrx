<?php

namespace App\Http\Controllers\tenant\users;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Support\Facades\Log;
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
use App\Mail\TeamLeaveAppliedMail;
use Illuminate\Support\Facades\Mail;
use App\Notifications\Expense\NewExpenseRequest;
use App\Services\LeavePolicyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Constants\Constants as AppConstants;

class UserDashboardController extends Controller
{
    /**
     * Point the session at another account WITHOUT rotating the session id.
     *
     * auth()->login() calls session()->migrate(true), which destroys the
     * current session file and issues a new id. Anything already in flight when
     * the switch happens — the Employee Directory's DataTable ajax, a
     * notification poller — still carries the old cookie: it finds no session,
     * redirects to the login page, and hands the browser back the dead id. The
     * next refresh then lands on the login screen, which is exactly the
     * "switching logs me out on refresh" report.
     *
     * Writing the guard's session key directly keeps one session throughout,
     * which is also how impersonation is normally done. Nothing is weakened by
     * skipping the rotation: session fixation is a risk when an unauthenticated
     * session becomes authenticated, and this session is already authenticated
     * — only the account it points at changes.
     */
    private function assumeIdentity(User $user): void
    {
        $guard = auth()->guard();
        session([$guard->getName() => $user->id]);
        $guard->setUser($user);
    }

    /**
     * Log in AS another account. Two ways in:
     *  - header hub: a vacant-position account whose access_delegate_id
     *    points at me (privileged accounts are always off limits);
     *  - Employee Directory: a super delegate (config/access.php) entering
     *    ANY active or still-onboarding account in one click, for
     *    support/debugging.
     * The original user id is kept in the session so Exit can restore it.
     */
    public function switchToDelegate($id)
    {
        $me      = auth()->user();
        $isSuper = $me->isSuperDelegate();

        if (session()->has('delegate_switch_original_id')) {
            return redirect()->back()->with('error', 'You are already in a delegate session — exit it first.');
        }

        if ((int) $id === (int) $me->id) {
            return redirect()->back()->with('error', 'That is already your own account.');
        }

        // A super delegate may also enter an account still in onboarding - to
        // see what a new joiner sees (onboarding form, training portal) and to
        // help them through it. Everyone else: active accounts only.
        $statuses = $isSuper
            ? [UserAccountStatus::ACTIVE, UserAccountStatus::ONBOARDING, UserAccountStatus::ONBOARDING_REQUESTED, UserAccountStatus::ONBOARDING_SUBMITTED]
            : [UserAccountStatus::ACTIVE];
        $query = User::where('id', $id)->whereIn('status', $statuses);

        // Super delegates skip the pointer requirement; everyone else may only
        // enter accounts explicitly delegated to them.
        if (!$isSuper) {
            $query->where('access_delegate_id', $me->id);
        }

        $target = $query->first();

        if (!$target) {
            return redirect()->back()->with('error', $isSuper
                ? 'That account was not found, or it is not active or in onboarding.'
                : 'This account is not delegated to you.');
        }

        // Hard security boundary: privileged accounts can never be entered via
        // delegation, even if a delegation row was somehow created for one —
        // blocks admin/HR privilege escalation through the switch path. A super
        // delegate is exempt: it is the top account already, so entering an
        // admin/HR account grants it nothing it does not already hold.
        if (!$isSuper && $target->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR'])) {
            \Illuminate\Support\Facades\Log::warning("Delegate switch BLOCKED: user {$me->id} attempted to enter privileged account {$target->id}");
            return redirect()->back()->with('error', 'Privileged accounts cannot be accessed by delegation.');
        }

        session([
            'delegate_switch_original_id' => $me->id,
            'delegate_switch_super'       => $isSuper,
        ]);
        $this->assumeIdentity($target);
        session()->save();

        \Illuminate\Support\Facades\Log::info(($isSuper ? 'SUPER delegate switch' : 'Delegate switch') . ": user {$me->id} ({$me->email}) logged in as account {$target->id} ({$target->email})");

        return redirect('/user/dashboard')->with('success', 'You are now logged in as ' . $target->getFullName() . ' (delegate session).');
    }

    /** Exit the delegate session and return to the real user's own account. */
    public function exitDelegate()
    {
        $originalId = session('delegate_switch_original_id');
        if (!$originalId) {
            return redirect()->back()->with('error', 'You are not in a delegate session.');
        }

        $wasSuper = (bool) session('delegate_switch_super');
        $current  = auth()->user();
        $original = User::find($originalId);

        // Integrity: the account being exited must actually be delegated to the
        // stored original user (or that user must still be a super delegate) —
        // otherwise kill the session entirely.
        $legitimate = $original && ($wasSuper
            ? $original->isSuperDelegate()
            : (int) $current->access_delegate_id === (int) $original->id);

        if (!$legitimate) {
            session()->forget(['delegate_switch_original_id', 'delegate_switch_super']);
            auth()->logout();
            return redirect('/login');
        }

        $this->assumeIdentity($original);
        session()->forget(['delegate_switch_original_id', 'delegate_switch_super']);
        session()->save();

        \Illuminate\Support\Facades\Log::info("Delegate switch: user {$original->id} exited delegated account {$current->id}");

        return redirect($wasSuper ? '/employees' : '/user/dashboard')->with('success', 'Back on your own account.');
    }

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

        // Accounts previously saw two DIFFERENT dashboards — the HR one at "/"
        // and a legacy page here whose charts were dead empty divs. Both now
        // resolve to the single Finance Command Center.
        if ($user->hasRole('accounts') && !$user->hasRole(['admin', 'hr']) && !$isFieldEmployee) {
            return app(\App\Http\Controllers\tenant\DashboardController::class)->index();
        }

        // Common Personal Stats
        $myLeavesCount = LeaveRequest::where('user_id', $user->id)->count();
        $myExpensesCount = ExpenseRequest::where('user_id', $user->id)->count();
        $myAttendanceCount = Attendance::where('user_id', $user->id)->count();
        $mySOSCount = SOSLog::where('user_id', $user->id)->count();

        // --- Revamp Data Points ---
        $user = auth()->user();
        // forUser() also scopes by department (the old inline where() only
        // scoped by site), and appliesToUser() applies gender scoping too -
        // both silently missing here before, same gap this whole pass fixes
        // on the "Upcoming Calendar" widgets.
        $nextHoliday = Holiday::forUser($user)
            ->where('date', '>=', now()->toDateString())
            ->where('status', 1)
            ->orderBy('date', 'asc')
            ->get()
            ->first(fn ($h) => $h->appliesToUser($user));

        // Shared with every other dashboard so the feed cannot drift.
        $recentNotices = \App\Support\DashboardAnnouncements::forUser($user);

        // Rail widgets: the calendar ahead, and which mandatory policies this
        // person still owes an acknowledgement for. The highlight used to say
        // "Code of Conduct · Pending" for everyone regardless of whether they
        // had signed anything.
        $upcomingHolidays = collect();
        $pendingPolicies = collect();
        try {
            $upcomingHolidays = \App\Models\Holiday::upcomingForUser($user);
        } catch (\Throwable $e) {
            $upcomingHolidays = collect();
        }
        try {
            $ackedIds = \App\Models\HRPolicyAcknowledgment::where('user_id', $user->id)
                ->pluck('hr_policy_id');
            $pendingPolicies = \App\Models\HRPolicy::where('is_active', true)
                ->where('is_mandatory', true)
                ->whereNotIn('id', $ackedIds)
                ->orderBy('title')->get();
        } catch (\Throwable $e) {
            $pendingPolicies = collect();
        }

        // Team Out Today (Approved leaves for today)
        $teamOutTodayQuery = LeaveRequest::whereDate('from_date', '<=', now())
            ->whereDate('to_date', '>=', now())
            ->where('status', LeaveRequestStatus::APPROVED)
            ->with('user');

        // Scoping for Manager. Was team_id, which leaked: this manager's
        // team_id is NULL and 251 other users also have NULL, so "her team"
        // matched almost the whole company and the card listed people who do
        // not report to her at all. Scope by direct reports, the same set the
        // rest of the manager dashboard uses.
        if ($isManager) {
            $teamOutTodayQuery->whereIn('user_id',
                User::whereIn('reporting_to_id', $user->managerIdentityIds())->notTerminated()->pluck('id'));
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
        // Outdoor duty is approved on the Leave page too, so it counts as pending leave.
        $pendingLeaveRequests = LeaveRequest::where('status', 'pending')->count()
            + \App\Models\OutdoorDuty::where('status', 'pending')->count();
        $pendingExpenseRequests = ExpenseRequest::where('status', 'pending')->count();

        // 1. HR/Admin Dashboard (First Priority)
        if (($isHR || $user->hasRole('admin')) && !$isFieldEmployee) {
            // The redesigned hr-index/admin-index need the full strategic
            // dataset that only DashboardController::index builds — delegate
            // to that single source of truth. No recursion: it only hands
            // back to this controller for employee-role holders, and those
            // are excluded from this branch above.
            return app(\App\Http\Controllers\tenant\DashboardController::class)->index();
        }

        // 2. Employee Dashboard — also catches users with NO role assigned yet
        // (accounts-dept bulk role assignment issue: anyone not explicitly admin/hr/manager/accounts gets employee view)
        $isPrivilegedRole = $user->hasRole(['admin', 'hr', 'accounts', 'manager']);
        // A manager identity with real reports — their own, or via a
        // vacant-position account delegated to them — outranks the employee
        // view, so dual-role users land on the redesigned Team Command Center.
        $hasTeamToManage = $isManager && User::whereIn('reporting_to_id', $user->managerIdentityIds())
            ->where('status', UserAccountStatus::ACTIVE)
            ->exists();
        if (!$hasTeamToManage && ($isFieldEmployee || (!$isPrivilegedRole && !$isManager))) {
            $settings = Settings::first();
            
            // Celebrations logic
            $todayMd = now()->format('md');
            
            // Birthdays
            $allBirthdays = User::notTerminated()->whereNotNull('dob')
                ->where('status', UserAccountStatus::ACTIVE)
                ->orderByRaw("CASE WHEN DATE_FORMAT(dob, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
                ->orderByRaw("DATE_FORMAT(dob, '%m%d') ASC")
                ->take(10)
                ->get();
            
            $todayBirthdays = $allBirthdays->filter(fn($u) => Carbon::parse($u->dob)->format('md') === $todayMd);
            $upcomingBirthdays = $allBirthdays->filter(fn($u) => Carbon::parse($u->dob)->format('md') !== $todayMd)->take(3);

            // Anniversaries
            $allAnniversaries = User::notTerminated()->whereNotNull('date_of_joining')
                ->where('status', UserAccountStatus::ACTIVE)
                ->orderByRaw("CASE WHEN DATE_FORMAT(date_of_joining, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
                ->orderByRaw("DATE_FORMAT(date_of_joining, '%m%d') ASC")
                ->take(10)
                ->get();

            $todayAnniversaries = $allAnniversaries->filter(fn($u) => Carbon::parse($u->date_of_joining)->format('md') === $todayMd);
            $upcomingAnniversaries = $allAnniversaries->filter(fn($u) => Carbon::parse($u->date_of_joining)->format('md') !== $todayMd)->take(3);

            // Everything the employee-index blade renders: department, manager,
            // shift, today's hours, leave balance, expenses, KRA, timeline,
            // upcoming leaves, documents and contacts. Previously none of these
            // were passed, so every widget on the page rendered its empty state
            // for every employee, permanently.
            $personal = $this->buildEmployeeDashboardData($user);

            return view('tenant.users.dashboard.employee-index', array_merge(compact(
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
                'upcomingAnniversaries',
                'upcomingHolidays',
                'pendingPolicies'
            ), $personal));
        }

        // Global Stats (Needed for Manager and HR)
        $totalUser = User::count();
        $active = User::where('status', UserAccountStatus::ACTIVE)->count();
        $presentUsersCount = Attendance::whereDate('created_at', now())->count();
        
        // Pending Requests (All for now, could be scoped to team for manager)
        // Outdoor duty is approved on the Leave page too, so it counts as pending leave.
        $pendingLeaveRequests = LeaveRequest::where('status', 'pending')->count()
            + \App\Models\OutdoorDuty::where('status', 'pending')->count();
        $pendingExpenseRequests = ExpenseRequest::where('status', 'pending')->count();

        // 2. Manager Dashboard
        if ($isManager) {
            // Get Subordinates (Direct Reports)
            $teamMembers = User::whereIn('reporting_to_id', $user->managerIdentityIds())
                ->where('status', UserAccountStatus::ACTIVE)
                ->get();
            
            $teamMemberIds = $teamMembers->pluck('id')->toArray();

            // Override global counts for the view to ensure "Team Only" view
            $active = count($teamMemberIds);
            $totalUser = $active;

            // Scoped Pending Requests
            $pendingLeaveRequests = LeaveRequest::whereIn('user_id', $teamMemberIds)
                ->where('status', 'pending')
                ->count()
                + \App\Models\OutdoorDuty::whereIn('user_id', $teamMemberIds)->where('status', 'pending')->count();
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
            $allBirthdays = User::notTerminated()->whereNotNull('dob')
                ->where('status', UserAccountStatus::ACTIVE)
                ->orderByRaw("CASE WHEN DATE_FORMAT(dob, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
                ->orderByRaw("DATE_FORMAT(dob, '%m%d') ASC")
                ->take(15) // Get broad set
                ->get();
            
            $todayBirthdays = $allBirthdays->filter(fn($u) => Carbon::parse($u->dob)->format('md') === $todayMd);
            $upcomingBirthdays = $allBirthdays->filter(fn($u) => Carbon::parse($u->dob)->format('md') !== $todayMd)->take(2);

            // Anniversaries
            $allAnniversaries = User::notTerminated()->whereNotNull('date_of_joining')
                ->where('status', UserAccountStatus::ACTIVE)
                ->orderByRaw("CASE WHEN DATE_FORMAT(date_of_joining, '%m%d') >= ? THEN 0 ELSE 1 END", [$todayMd])
                ->orderByRaw("DATE_FORMAT(date_of_joining, '%m%d') ASC")
                ->take(15)
                ->get();

            $todayAnniversaries = $allAnniversaries->filter(fn($u) => Carbon::parse($u->date_of_joining)->format('md') === $todayMd);
            $upcomingAnniversaries = $allAnniversaries->filter(fn($u) => Carbon::parse($u->date_of_joining)->format('md') !== $todayMd)->take(2);

            // Team Deployment: EVERY direct report with their status today
            // (Present / On Leave / Absent), not only those who are away.
            $presentTeamIds = $todayPresentUsersList->pluck('user_id')->unique();
            $onLeaveTeamIds = LeaveRequest::whereIn('user_id', $teamMemberIds)
                ->whereDate('from_date', '<=', now())
                ->whereDate('to_date', '>=', now())
                ->where('status', LeaveRequestStatus::APPROVED)
                ->pluck('user_id')->unique();
            $teamDeployment = $teamMembers->map(function ($m) use ($presentTeamIds, $onLeaveTeamIds) {
                $status = $presentTeamIds->contains($m->id) ? 'present'
                        : ($onLeaveTeamIds->contains($m->id) ? 'leave' : 'absent');
                return (object) ['user' => $m, 'deploy_status' => $status];
            });

            // This view carries a full sales-manager panel — pipeline value,
            // per-rep sales/forecast/pending, alerts. It was never passed here,
            // so every figure fell back to its `?? 0` default and the page read
            // zero, while the same view at "/" showed real numbers. The delegate
            // switch and the sidebar both land on THIS route, so this is the one
            // a manager actually sees. Shared builder, so the two cannot drift.
            $sm = \App\Support\SalesManagerSummary::build($teamMembers, $teamDeployment, (int) $pendingLeaveRequests);

            // Both of these were only ever passed by DashboardController, so on
            // this route the cards rendered their empty state — "No team
            // attendance in last 7 days" and "No upcoming team leaves" — no
            // matter what the data said.
            $teamAtt7 = [];
            try {
                $teamHeadcount = max(1, count($teamMemberIds));
                // One grouped query instead of 7 identical-shaped per-day queries.
                $teamAtt7RangeStart = now()->subDays(6)->startOfDay();
                $teamAtt7ByDate = Attendance::whereIn('user_id', $teamMemberIds)
                    ->where('check_in_time', '>=', $teamAtt7RangeStart)
                    ->selectRaw('DATE(check_in_time) as d, COUNT(DISTINCT user_id) as c')
                    ->groupBy('d')
                    ->pluck('c', 'd');
                for ($i = 6; $i >= 0; $i--) {
                    $d = now()->subDays($i);
                    $present = (int) ($teamAtt7ByDate[$d->toDateString()] ?? 0);
                    $teamAtt7[] = ['label' => $d->format('D'),
                                   'pct' => (int) round($present / $teamHeadcount * 100)];
                }
            } catch (\Throwable $e) {
                Log::warning('Team attendance tile: ' . $e->getMessage());
            }

            $teamUpcomingLeaves = LeaveRequest::whereIn('user_id', $teamMemberIds)
                ->where('status', LeaveRequestStatus::APPROVED)
                ->whereDate('from_date', '>', now())
                ->with(['user', 'leaveType'])->orderBy('from_date')->take(8)->get();

            $mgrRail = \App\Support\ManagerDashboard::rail([
                'teamSize' => $active, 'present' => $todayPresentCount ?? 0,
                'onLeave' => $todayOnLeaveCount ?? 0, 'absent' => $todayAbsentCount ?? 0,
                'pendingLeave' => $pendingLeaveRequests, 'pendingExpense' => $pendingExpenseRequests,
                'pendingDocument' => $pendingDocumentRequests, 'pendingLoan' => $pendingLoanRequests,
                'todayBirthdays' => $todayBirthdays ?? [], 'todayAnniversaries' => $todayAnniversaries ?? [],
                'sm' => $sm, 'user' => $user,
            ]);

            return view('tenant.users.dashboard.manager-index', array_merge($mgrRail, [
                'mgrVariant' => \App\Support\ManagerDashboard::variant($user),
                'mgrDept' => \App\Support\ManagerDashboard::departmentName($user),
                'sm' => $sm,
                'teamAtt7' => $teamAtt7,
                'teamUpcomingLeaves' => $teamUpcomingLeaves,
                'teamDeployment' => $teamDeployment,
                'teamPendingLeaveRequests' => LeaveRequest::whereIn('user_id', $teamMemberIds)
                    ->where('status', 'pending')
                    ->with(['user', 'leaveType'])
                    ->latest()
                    ->take(5)
                    ->get(),
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
                'departments' => \App\Models\Department::cachedActive(),
                'roles' => \Spatie\Permission\Models\Role::all(),
                'designations' => \App\Models\Designation::cachedActive(),
                'managers' => \App\Models\User::whereHas('roles', function($q) {
                    $q->whereIn('name', ['admin', 'hr', 'manager', 'accounts']);
                })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->get()
            ]));
        }

        // 3. Admin / Default Dashboard (Full View)
        $presentUsersCountLastWeek = Attendance::whereBetween('created_at', [now()->startOfWeek()->subWeek(), now()->endOfWeek()->subWeek()])
            ->where('check_out_time', '!=', null)
            ->get()
            ->sum(function ($attendance) {
                return \App\Services\AttendanceDayStatus::workedMinutes($attendance->check_in_time, $attendance->check_out_time);
        });

        $thisWeekWorkingHours = Attendance::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->where('check_out_time', '!=', null)
            ->get()
            ->sum(function ($attendance) {
                return \App\Services\AttendanceDayStatus::workedMinutes($attendance->check_in_time, $attendance->check_out_time);
        });

        $todayHours = Attendance::whereDate('created_at', now())
            ->where('check_out_time', '!=', null)
            ->get()
            ->sum(function ($attendance) {
                return \App\Services\AttendanceDayStatus::workedMinutes($attendance->check_in_time, $attendance->check_out_time);
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
            'start_time'    => 'nullable|string',
            'end_time'      => 'nullable|string',
            'duration_hours'=> 'nullable|numeric',
        ]);

        $leaveType = LeaveType::find($validated['leave_type_id']);

        // HDL (Half Day Leave) and WFH (functionally identical — see
        // EnsureWfhLeaveTypeCommand) always force half-day regardless of form value
        $isHdl     = $leaveType && strtoupper($leaveType->code) === 'HDL';
        $isWfh     = $leaveType && strtoupper($leaveType->code) === 'WFH';
        $isHalfDay = ($isHdl || $isWfh) ? true : $request->boolean('is_half_day');
        $isShort   = $leaveType && (bool)$leaveType->is_short_leave;

        if ($isHalfDay || $isShort) {
            $validated['to_date'] = $validated['from_date'];
        }

        // HDL/WFH must have a session; default to first_half if omitted
        if (($isHdl || $isWfh) && empty($validated['half_day_session'])) {
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

        // 3. Backdated Check (Limit: 1 week - see LeaveBackdate)
        if ($backdateError = \App\Support\LeaveBackdate::error($validated['from_date'], $validated['to_date'], auth()->id())) {
            return redirect()->back()->withErrors(['from_date' => $backdateError])->withInput();
        }

        // Unit leave policy enforcement
        $error = LeavePolicyService::validate(
            auth()->user(),
            $validated['leave_type_id'],
            $validated['from_date'],
            $validated['to_date'],
            $isShort ? ($request->input('duration_hours') ?? 2.0) : null,
            $isHalfDay,
            $isShort ? $request->input('start_time') : null,
            $isShort ? $request->input('end_time') : null,
            $isHalfDay ? ($validated['half_day_session'] ?? 'first_half') : null
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
        $leaveRequest->tenant_id        = $user->tenant_id;

        if ($isShort) {
            $leaveRequest->status           = LeaveRequestStatus::APPROVED;
            $leaveRequest->approved_by_id   = null;
            $leaveRequest->approved_at      = now();
            $leaveRequest->approval_notes   = 'System auto-approved first monthly short leave';
            $leaveRequest->is_short_leave   = true;
            $leaveRequest->duration_hours   = $request->input('duration_hours') ?? 2.0;
            $leaveRequest->start_time       = $request->input('start_time');
            $leaveRequest->end_time         = $request->input('end_time');
        } else {
            $leaveRequest->status           = LeaveRequestStatus::PENDING;
            $leaveRequest->is_half_day      = $isHalfDay;
            $leaveRequest->half_day_session = $isHalfDay ? ($validated['half_day_session'] ?? 'first_half') : null;
        }

        // LWP Detection — check if balance is insufficient at submission time (Only for regular leaves)
        if (!$isShort) {
            $impact = LeavePolicyService::getBalanceImpact($user, $validated['leave_type_id'], $validated['from_date'], $validated['to_date'], $isHalfDay);
            $workingDays = LeavePolicyService::calculateWorkingDays($user, $validated['leave_type_id'], $validated['from_date'], $validated['to_date'], $isHalfDay);
            $availableBalance = $impact['available'] ?? 0;
            if ($leaveType->is_paid && $availableBalance < $workingDays) {
                $lwpDays = max(0, $workingDays - $availableBalance);
                $leaveRequest->is_lwp   = true;
                $leaveRequest->lwp_days = $lwpDays;
            }
        }

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->storeAs(AppConstants::BaseFolderLeaveRequestDocument, $fileName, 'public');
            $leaveRequest->document = $fileName;
        }

        $leaveRequest->save();

        // Notify Admin/HR
        NotificationHelper::notifyAdminHR(new NewLeaveRequest($leaveRequest));

        return redirect()->back()->with('success', $isShort ? 'Short leave request submitted and auto-approved.' : 'Leave request submitted successfully.');
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
            })
            ->with(['leaveRequest.leaveType'])
            ->get();
            
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

        $presentDays = 0;
        $lateDays = 0;
        $absentDays = 0;
        $totalHours = 0;
        $workCount = 0;

        $dailyLogs = [];

        // Holidays, batched once per distinct month the range touches (a
        // custom/weekly range can span a month boundary) instead of queried
        // per day.
        $holidayMapsByMonth = [];
        $holidayMapsFor = function ($m, $y) use (&$holidayMapsByMonth) {
            $key = "$y-$m";
            if (!isset($holidayMapsByMonth[$key])) {
                $holidayMapsByMonth[$key] = \App\Services\AttendanceDayStatus::holidayMapsForMonth($m, $y);
            }
            return $holidayMapsByMonth[$key];
        };

        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
            $dateStr = $date->toDateString();

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
            $holiday = \App\Services\AttendanceDayStatus::holidayForUserOnDate(
                $holidayMapsFor($date->month, $date->year), $user, $user->site_id, $dateStr
            );

            // 2. Check Attendance (prioritize real punches over dummy 00:00 leave logs)
            $attendance = $attendancesDb->filter(function($a) use ($dateStr) {
                return ($a->check_in_time && $a->check_in_time->toDateString() === $dateStr) || 
                       (!$a->check_in_time && $a->created_at->toDateString() === $dateStr);
            })->sortByDesc(function($a) {
                $isDummy = $a->check_in_time && $a->check_in_time->format('H:i') === '00:00';
                return $isDummy ? 0 : 1;
            })->first();

            // 3. Check Leave
            $leave = $leavesDb->first(function($l) use ($dateStr) {
                return Carbon::parse($l->from_date)->toDateString() <= $dateStr && 
                       Carbon::parse($l->to_date)->toDateString() >= $dateStr;
            });

            $isSunday = $date->isSunday();
            // A dummy 00:00 "Absent" placeholder with no real punch is treated
            // as if there were no attendance row at all, letting the
            // no-record branch below (which prioritises an approved leave
            // over a coinciding holiday) run instead.
            $attendanceForResolve = ($attendance && (strtolower($attendance->status) !== 'absent' || ($attendance->check_in_time && $attendance->check_out_time)))
                ? $attendance : null;

            $r = \App\Services\AttendanceDayStatus::resolve($user, $date, $attendanceForResolve, $leave, $holiday);
            $isPaid = $r['isPaid'];

            if ($attendanceForResolve) {
                $dayData['check_in_time'] = $attendance->check_in_time;
                $dayData['check_out_time'] = $attendance->check_out_time;
                $dayData['admin_reason'] = $attendance->admin_reason;
            }

            $dayData['dynamic_status'] = $r['code'];

            switch ($r['code']) {
                case 'miss-punch':
                    $dayData['status'] = 'Miss Punch';
                    $dayData['holiday_name'] = 'HR/Admin remark required';
                    $absentDays++; // counted absent until HR corrects the punch with remarks
                    break;
                case 'absent':
                    $dayData['status'] = 'Absent';
                    $absentDays++;
                    break;
                case 'sp':
                    $dayData['status'] = 'Weekly Off Worked (SP)';
                    $presentDays++;
                    break;
                case 'hp':
                    $dayData['status'] = 'Holiday Worked (HP)';
                    $presentDays++;
                    break;
                case 'weekly_off':
                    $dayData['status'] = 'Weekly Off';
                    break;
                case 'holiday':
                    $dayData['status'] = 'Holiday';
                    $dayData['holiday_name'] = $r['holidayName'];
                    break;
                case 'late':
                    $dayData['status'] = 'Late';
                    $lateDays++;
                    $presentDays++;
                    break;
                case 'work_from_home':
                case 'wfh':
                    $dayData['status'] = 'WFH';
                    $presentDays += 1;
                    break;
                case 'half-day-paid':
                    $dayData['status'] = 'Half Day (HD-PL)';
                    $presentDays += 1;
                    break;
                case 'half-day':
                    $dayData['status'] = 'Half Day';
                    $presentDays += 0.5;
                    $absentDays += 0.5;
                    break;
                case 'leave':
                case 'paid_leave':
                case 'unpaid_leave':
                    $dayData['status'] = $isPaid ? 'Paid Leave' : 'Unpaid Leave';
                    if (!$attendanceForResolve) {
                        $dayData['holiday_name'] = $r['leaveRequest']?->leaveType?->name ?? 'Approved Leave';
                    }
                    if ($isPaid) {
                        $presentDays++;
                    } else {
                        $absentDays++;
                    }
                    break;
                case 'scheduled':
                    $dayData['status'] = 'Scheduled';
                    break;
                case 'today':
                    $dayData['status'] = 'Today';
                    // A dummy 00:00 "Absent" row already recorded for today
                    // overrides the placeholder "Today" card.
                    if ($attendance && strtolower($attendance->status) === 'absent') {
                        $dayData['status'] = 'Absent';
                        $dayData['dynamic_status'] = 'absent';
                        $absentDays++;
                    }
                    break;
                default:
                    $dayData['status'] = 'Present';
                    $presentDays++;
                    break;
            }

            if ($attendanceForResolve && $attendance->check_in_time && $attendance->check_out_time) {
                $totalHours += \App\Services\AttendanceDayStatus::workedMinutes($attendance->check_in_time, $attendance->check_out_time) / 60;
                $workCount++;
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
            ->with(['updatedBy', 'leaveRequest.leaveType'])
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
            
        // Holidays for the month, batched once (not queried per day).
        $holidayMaps = \App\Services\AttendanceDayStatus::holidayMapsForMonth($month, $year);

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
            
            $holiday = \App\Services\AttendanceDayStatus::holidayForUserOnDate($holidayMaps, $user, $user->site_id, $dateStr);

            // Check for Attendance
            $attendance = $attendances->first(function($a) use ($dateStr) {
                return $a->check_in_time->toDateString() === $dateStr;
            });

            // Approved leave covering this date (needed in both branches: an
            // approved short leave may not be linked on the attendance row)
            $dayLeave = $leaves->first(function($l) use ($dateStr) {
                return Carbon::parse($l->from_date)->toDateString() <= $dateStr &&
                       Carbon::parse($l->to_date)->toDateString() >= $dateStr;
            });

            $r = \App\Services\AttendanceDayStatus::resolve($user, $dateObj, $attendance, $dayLeave, $holiday);
            $leaveReq = $r['leaveRequest'];
            $isPaid = $r['isPaid'];

            if ($attendance) {
                $dayData['in'] = $attendance->check_in_time->format('h:i A');
                $dayData['out'] = $attendance->check_out_time ? $attendance->check_out_time->format('h:i A') : '--:--';

                if ($attendance->check_in_time && $attendance->check_out_time) {
                    $diff = $attendance->check_in_time->diff($attendance->check_out_time);
                    $dayData['duration'] = sprintf('%d:%02dh', $diff->h, $diff->i);
                }
            }

            switch ($r['code']) {
                case 'present':
                    $dayData['status'] = 'Present';
                    $dayData['icon'] = 'bx-check-circle';
                    $dayData['class'] = 'bg-teal text-white border-0';
                    break;
                case 'short-leave-present':
                    $dayData['status'] = 'Present (Short Leave)';
                    $dayData['icon'] = 'bx-check-circle';
                    $dayData['class'] = 'bg-short-leave text-white border-0';
                    break;
                case 'miss-punch':
                    $dayData['status'] = 'Miss Punch';
                    $dayData['icon'] = 'bx-error-circle';
                    $dayData['class'] = 'bg-miss-punch text-white border-0';
                    break;
                case 'sp':
                    $dayData['status'] = 'SP';
                    $dayData['icon'] = 'bx-check-circle';
                    $dayData['class'] = 'bg-success text-white border-0';
                    break;
                case 'hp':
                    $dayData['status'] = 'HP';
                    $dayData['icon'] = 'bx-check-circle';
                    $dayData['class'] = 'bg-success text-white border-0';
                    break;
                case 'weekly_off':
                    $dayData['status'] = 'Weekly Off';
                    $dayData['class'] = 'bg-secondary bg-opacity-10 text-muted';
                    $dayData['icon'] = 'bx-calendar-minus';
                    break;
                case 'holiday':
                    $dayData['status'] = 'Holiday';
                    $dayData['icon'] = 'bx-star';
                    $dayData['class'] = 'bg-info bg-opacity-25 text-info border-info border-opacity-50';
                    $dayData['holiday_name'] = $r['holidayName'];
                    break;
                case 'late':
                    $dayData['status'] = 'Late';
                    $dayData['icon'] = 'bx-time-five';
                    $dayData['class'] = 'bg-orange text-white border-0';
                    break;
                case 'half-day':
                    $leaveDetail = '';
                    if ($leaveReq) {
                        if ($leaveReq->is_short_leave) {
                            $leaveDetail = ' (Short Leave)';
                        } elseif ($leaveReq->is_half_day) {
                            $session = $leaveReq->half_day_session === 'second_half' ? '2nd Half' : '1st Half';
                            $leaveDetail = ' (Half Day - ' . $session . ')';
                        }
                    }
                    $dayData['status'] = 'Half Day' . $leaveDetail;
                    $dayData['icon'] = 'bx-time';
                    $dayData['class'] = 'bg-orange text-white border-0';
                    break;
                case 'half-day-paid':
                    $leaveDetail = '';
                    if ($leaveReq && $leaveReq->is_half_day) {
                        $session = $leaveReq->half_day_session === 'second_half' ? '2nd Half' : '1st Half';
                        $leaveDetail = ' (Half Day - ' . $session . ')';
                    }
                    $dayData['status'] = 'Present' . $leaveDetail . ' (Paid)';
                    $dayData['icon'] = 'bx-check-circle';
                    $dayData['class'] = 'bg-indigo-gradient text-white border-0';
                    break;
                case 'absent':
                    $dayData['status'] = 'Absent';
                    $dayData['icon'] = 'bx-x-circle';
                    $dayData['class'] = 'bg-red text-white border-0';
                    if (!$attendance) {
                        $dayData['holiday_name'] = 'No Log Found';
                    }
                    break;
                case 'work_from_home':
                case 'wfh':
                    $dayData['status'] = 'WFH';
                    $dayData['icon'] = 'bx-home';
                    $dayData['class'] = 'bg-indigo-vibrant text-white border-0';
                    break;
                case 'leave':
                case 'paid_leave':
                case 'unpaid_leave':
                    $leaveDetail = '';
                    if ($leaveReq) {
                        if ($leaveReq->is_short_leave) {
                            $leaveDetail = ' (Short Leave)';
                        } elseif ($leaveReq->is_half_day) {
                            $session = $leaveReq->half_day_session === 'second_half' ? '2nd Half' : '1st Half';
                            $leaveDetail = ' (Half Day - ' . $session . ')';
                        }
                    }
                    $dayData['status'] = ($isPaid ? 'Paid Leave' : 'Unpaid Leave') . $leaveDetail;
                    $dayData['icon'] = 'bx-calendar';
                    $dayData['class'] = ($isPaid ? 'bg-indigo-vibrant' : 'bg-purple-vibrant') . ' text-white border-0';
                    if (!$attendance) {
                        $dayData['holiday_name'] = $leaveReq?->leaveType?->name ?? 'Approved Leave';
                    }
                    break;
                case 'scheduled':
                    $dayData['status'] = 'Scheduled';
                    $dayData['icon'] = 'bx-calendar-event';
                    $dayData['class'] = 'bg-white border text-muted opacity-50';
                    $dayData['holiday_name'] = 'Upcoming';
                    break;
                case 'today':
                    $dayData['status'] = 'Today';
                    $dayData['icon'] = 'bx-time';
                    $dayData['class'] = 'bg-white border-primary border-dashed text-primary';
                    break;
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

    /**
     * Assemble every personal widget on the employee dashboard.
     *
     * The blade has always rendered these; the controller never supplied them,
     * so all of them silently fell back to their "—" / 0 / empty state. Each
     * block is individually try/catch-free but null-safe, and any genuinely
     * absent module (e.g. no KPI assignments) simply yields an empty value the
     * blade already knows how to render.
     */
    private function buildEmployeeDashboardData(User $user): array
    {
        $today = Carbon::today();

        // --- Identity: department, reporting manager, shift window ---------
        $deptName = optional($user->department)->name;
        $managerName = null;
        if ($user->reporting_to_id) {
            $managerName = optional(User::find($user->reporting_to_id))->getFullName();
        }

        $shiftWindow = null;
        if ($user->shift_id) {
            $shift = \App\Models\Shift::find($user->shift_id);
            if ($shift && $shift->start_time && $shift->end_time) {
                $shiftWindow = Carbon::parse($shift->start_time)->format('h:i A')
                    . ' - ' . Carbon::parse($shift->end_time)->format('h:i A');
            }
        }

        // --- Today's attendance + hours worked ----------------------------
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('check_in_time', $today)
            ->latest('check_in_time')
            ->first();

        $todayHours = 0.0;
        if ($todayAttendance && $todayAttendance->check_in_time) {
            $end = $todayAttendance->check_out_time
                ? Carbon::parse($todayAttendance->check_out_time)
                : now();
            $todayHours = max(0, Carbon::parse($todayAttendance->check_in_time)->floatDiffInHours($end));
            $todayHours = round(min($todayHours, 24), 2);
        }

        // --- This week's presence (Mon..today) ----------------------------
        // Resolved through the same AttendanceDayStatus resolver every
        // attendance page shares - this used to count any date with a
        // non-null check_in_time, including a dummy 00:00 leave-linked row
        // with no real punch at all.
        $weekStart = $today->copy()->startOfWeek();
        $weekHolidayMapsByMonth = [];
        $presentThisWeek = Attendance::where('user_id', $user->id)
            ->whereBetween('check_in_time', [$weekStart, $today->copy()->endOfDay()])
            ->whereNotNull('check_in_time')
            ->with('leaveRequest.leaveType')
            ->get()
            ->groupBy(fn($a) => $a->check_in_time->toDateString())
            ->filter(function ($dayRows) use ($user, &$weekHolidayMapsByMonth) {
                $best = $dayRows->sortByDesc(fn($a) => $a->check_in_time->format('H:i') !== '00:00' ? 1 : 0)->first();
                $d = $best->check_in_time;
                $monthKey = $d->year . '-' . $d->month;
                if (!isset($weekHolidayMapsByMonth[$monthKey])) {
                    $weekHolidayMapsByMonth[$monthKey] = \App\Services\AttendanceDayStatus::holidayMapsForMonth($d->month, $d->year);
                }
                $holiday = \App\Services\AttendanceDayStatus::holidayForUserOnDate($weekHolidayMapsByMonth[$monthKey], $user, $user->site_id, $d->toDateString());
                return \App\Services\AttendanceDayStatus::isPresentLikeRow($best, $holiday);
            })
            ->count();

        $weekWorkingDays = 0;
        for ($d = $weekStart->copy(); $d->lte($today); $d->addDay()) {
            if (LeavePolicyService::isWorkingDay($user, $d)) {
                $weekWorkingDays++;
            }
        }
        $weekWorkingDays = max(1, $weekWorkingDays);

        // --- Leave balance: the shared ALP pool + legacy PL fallback ------
        $leaveDaysAvailable = 0.0;
        try {
            $leaveDaysAvailable = LeavePolicyService::getPoolBalance($user);
        } catch (\Throwable $e) {
            $leaveDaysAvailable = (float) \App\Models\LeaveBalance::where('user_id', $user->id)
                ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(balance,0) - COALESCE(used,0)'));
        }

        // --- Expenses claimed this month (expense claims + travel claims) --
        $expensesThisMonth = (float) ExpenseRequest::where('user_id', $user->id)
            ->whereYear('created_at', $today->year)
            ->whereMonth('created_at', $today->month)
            ->sum('amount');

        // --- KRA / KPI progress for the current period --------------------
        $kraItems = [];
        // Performance = MARKS EARNED against marks possible for the period we
        // are in. It used to count "periods completed", so someone who ticked
        // every KPI and scored nothing showed 100%. It also read only the first
        // six KPIs by title, which on a 13-KPI scorecard hid Revenue — the
        // single biggest weight — from the card entirely.
        $kraOverall    = 0;
        $kpiEarned     = 0.0;
        $kpiPossible   = 0.0;
        $kpiDueSoon    = 0;
        $kpiPending    = 0;
        $kpiCompleted  = 0;
        $kpiWeekDue    = 0;
        $kpiWeekWaiting = 0;
        $kpiSentBack   = 0;
        try {
            // syncUser() backfills the current period's check for every active
            // assignment before returning them. Reading KpiCheck rows directly
            // (the old approach) silently dropped anything nobody had ever
            // opened /kpi/my or /kpi/manage for — no row existed yet, so it
            // never counted as due/pending no matter how close the deadline.
            $assignments = \App\Services\KpiPeriodService::syncUser($user->id);

            // What this person still owes on their own KPIs this week, and what
            // is sitting with HR — the dashboard card is the only place they
            // find out without opening the scorecard.
            [$wkStart] = \App\Services\KpiEntryService::weekOf();
            $manualIds = $assignments->filter(fn ($a) => \App\Services\KpiEntryService::isManual($a))->pluck('id');
            $weekRows = \App\Models\KpiEntry::where('user_id', $user->id)
                ->whereDate('period_start', $wkStart->toDateString())->get()->keyBy('kpi_assignment_id');
            $kpiWeekDue = $manualIds->reject(fn ($id) => isset($weekRows[$id]))->count();
            $kpiWeekWaiting = $weekRows->where('status', \App\Models\KpiEntry::SUBMITTED)->count();
            $kpiSentBack = \App\Models\KpiEntry::where('user_id', $user->id)
                ->where('status', \App\Models\KpiEntry::REJECTED)->count();

            if ($assignments->isNotEmpty()) {
                $today = \Carbon\Carbon::today();

                foreach ($assignments as $a) {
                    $check = $a->currentCheck;
                    $max   = (float) $a->max_marks;
                    $score = $check && $check->score !== null ? (float) $check->score : 0.0;

                    $kpiPossible += $max;
                    $kpiEarned   += $score;

                    if ($check) {
                        if (in_array($check->status, ['completed_on_time', 'completed_late'], true)) {
                            $kpiCompleted++;
                        } elseif ($check->status === 'pending') {
                            $kpiPending++;
                            // Still open and the deadline is within a week.
                            if ($check->due_date) {
                                $due = \Carbon\Carbon::parse($check->due_date);
                                if ($due->gte($today) && $due->diffInDays($today) <= 7) {
                                    $kpiDueSoon++;
                                }
                            }
                        }
                    }

                    $kraItems[] = [
                        'name' => $a->title,
                        'pct'  => $max > 0 ? (int) round(($score / $max) * 100) : 0,
                    ];
                }

                // syncUser() orders by category/frequency for the scoring
                // engine's own use; the dashboard list has always read
                // alphabetically, so restore that here rather than change
                // what every employee already sees.
                usort($kraItems, fn ($x, $y) => strcasecmp($x['name'], $y['name']));

                $kraOverall = $kpiPossible > 0
                    ? (int) round(($kpiEarned / $kpiPossible) * 100)
                    : 0;
            }
        } catch (\Throwable $e) {
            $kraItems = [];
        }

        // --- Upcoming approved/pending leaves ------------------------------
        $upcomingLeaves = LeaveRequest::with('leaveType')
            ->where('user_id', $user->id)
            ->whereDate('to_date', '>=', $today)
            ->whereIn('status', [LeaveRequestStatus::APPROVED, LeaveRequestStatus::PENDING])
            ->orderBy('from_date')
            ->limit(4)
            ->get()
            ->map(function ($l) {
                $from = Carbon::parse($l->from_date);
                $status = $l->status instanceof LeaveRequestStatus ? $l->status->value : (string) $l->status;
                return [
                    'day' => $from->format('d'),
                    'mon' => $from->format('M'),
                    'year' => $from->format('Y'),
                    'type' => optional($l->leaveType)->name ?? 'Leave',
                    'status' => $status,
                ];
            })->all();

        // --- Personal activity timeline (attendance + leave + expense) -----
        $myTimeline = [];
        $recentAtt = Attendance::where('user_id', $user->id)
            ->whereNotNull('check_in_time')
            ->latest('check_in_time')->limit(3)->get();
        foreach ($recentAtt as $a) {
            $in = Carbon::parse($a->check_in_time);
            $myTimeline[] = [
                'tone' => 'success', 'icon' => 'bx-log-in-circle',
                'title' => 'Checked in',
                'sub' => $in->isToday() ? 'Today' : $in->format('d M'),
                'time' => $in->format('h:i A'),
            ];
        }
        $recentLeave = LeaveRequest::with('leaveType')->where('user_id', $user->id)
            ->latest('created_at')->limit(2)->get();
        foreach ($recentLeave as $l) {
            $status = $l->status instanceof LeaveRequestStatus ? $l->status->value : (string) $l->status;
            $myTimeline[] = [
                'tone' => $status === 'approved' ? 'success' : ($status === 'pending' ? 'warning' : 'danger'),
                'icon' => 'bx-calendar-event',
                'title' => (optional($l->leaveType)->name ?? 'Leave') . ' · ' . ucfirst($status),
                'sub' => Carbon::parse($l->from_date)->format('d M'),
                'time' => '',
            ];
        }
        $myTimeline = collect($myTimeline)->take(5)->all();

        // --- My documents: which core documents are on file ----------------
        // Documents live as files under the onboarding folder, not columns —
        // the User model's getXxxUrl() helpers resolve them.
        $myDocs = [];
        try {
            $myDocs = [
                ['name' => 'Aadhaar Card',      'ok' => (bool) $user->getAadhaarUrl()],
                ['name' => 'PAN Card',          'ok' => (bool) $user->getPanUrl()],
                ['name' => 'Cancelled Cheque',  'ok' => (bool) $user->getChequeUrl()],
                ['name' => 'Matriculation',     'ok' => (bool) $user->getMatricUrl()],
                ['name' => 'Graduation',        'ok' => (bool) $user->getBachelorUrl()],
            ];
        } catch (\Throwable $e) {
            $myDocs = [];
        }

        // --- Important contacts: reporting manager + HR ---------------------
        // Shared across all 5 role dashboards — see HrNotificationService::importantContactsFor().
        $importantContacts = \App\Services\HrNotificationService::importantContactsFor($user);

        // --- Sales-only board (Kingo/Bingo + today's visits) ---------------
        $salesProfile = $this->buildEmployeeSalesProfile($user);

        return compact(
            'deptName', 'managerName', 'shiftWindow',
            'todayAttendance', 'todayHours', 'presentThisWeek', 'weekWorkingDays',
            'leaveDaysAvailable', 'expensesThisMonth',
            'kraItems', 'kraOverall', 'kpiEarned', 'kpiPossible', 'kpiDueSoon',
            'kpiWeekDue', 'kpiWeekWaiting', 'kpiSentBack',
            'kpiPending', 'kpiCompleted',
            'upcomingLeaves', 'myTimeline', 'myDocs', 'importantContacts',
            'salesProfile'
        );
    }

    /**
     * Kingo/Bingo tiers + today's visits for a salesperson. Returns null for
     * non-sales staff so the blade skips the whole section.
     */
    private function buildEmployeeSalesProfile(User $user): ?array
    {
        $dept = strtolower(optional($user->department)->name ?? '');
        // Sales, Pre Sales, Sales Department, New Biz, Customer Care,
        // Customer Care + New Biz and Export all work customer accounts.
        $isSalesDept = $dept !== '' && (
            str_contains($dept, 'sales') || str_contains($dept, 'new biz')
            || str_contains($dept, 'customer care') || str_contains($dept, 'export')
        );
        if (!$isSalesDept) {
            return null;
        }

        // A customer is held under whichever column matches the person's role:
        // salespeople sit on salesperson_id, Customer Care on ccare_id, New Biz
        // on new_biz_id. Matching only salesperson_id left every CCare and New
        // Biz employee looking at an empty card while their manager's dashboard
        // showed their real numbers.
        $roleCols = ['salesperson_id', 'ccare_id', 'new_biz_id'];
        if (Schema::hasColumn('sales_pipelines', 'new_biz_2_id')) {
            $roleCols[] = 'new_biz_2_id';
        }
        $heldByMe = function ($q) use ($roleCols, $user) {
            foreach ($roleCols as $i => $c) {
                $i === 0 ? $q->where($c, $user->id) : $q->orWhere($c, $user->id);
            }
        };
        // Sales figures also count a customer that moved New Biz -> CCare for
        // its New Biz person, for the months before the move only.
        $soldByMe = function ($q) use ($heldByMe, $user) {
            $q->where($heldByMe)->orWhere('former_new_biz_id', $user->id);
        };

        $todayVisits = collect();
        try {
            $todayVisits = \App\Models\SalesVisit::with('client')
                ->where(function ($q) use ($user) {
                    $q->where('salesperson_id', $user->id);
                    if (Schema::hasColumn('sales_visits', 'cc_user_id')) {
                        $q->orWhere('cc_user_id', $user->id);
                    }
                })
                ->whereDate('scheduled_at', Carbon::today())
                ->orderBy('scheduled_at')
                ->get();
        } catch (\Throwable $e) {
            $todayVisits = collect();
        }

        // Monthly achieved + targets. Targets live in salesperson_targets keyed
        // by 'YYYY-MM'; achieved comes from this month's pipeline sale amounts.
        // Both are stored in LAKHS, matching the Kingo Bingo scorecard.
        $achieved = 0.0;
        $kingoTiers = [];
        $orderCustomers = [];
        $monthly = [];
        $customerCount = 0;
        $fySold = 0.0; $fyForecast = 0.0; $fyPending = 0.0; $book = 0.0;
        $pctForecast = null; $pctPending = null; $openOrders = 0;
        $lastMonth = 0.0;
        $momPct = null;
        try {
            $monthKey = Carbon::today()->format('Y-m');

            // Achieved means realised sales, which live on the locked rows —
            // is_locked true is the Sales Report, false is the open pipeline.
            // Without this filter the card counted forecast as money earned.
            $pipelines = \App\Models\SalesPipeline::with(['months' => fn($q) => $q->where('month_year', $monthKey)])
                ->where($soldByMe)
                ->where('is_locked', true)
                ->get()
                ->filter(fn ($p) => $p->creditsMonth((int) $user->id, $monthKey));

            foreach ($pipelines as $p) {
                $achieved += (float) ($p->months->first()->sale_amount ?? 0);
            }

            $target = \App\Models\SalespersonTarget::where('salesperson_id', $user->id)
                ->where('month_year', $monthKey)
                ->first();

            if ($target) {
                foreach ([['Bingo', 'bingo_target'], ['Kingo', 'kingo_target'], ['Super Kingo', 'super_kingo_target']] as [$label, $col]) {
                    $t = (float) ($target->{$col} ?? 0);
                    if ($t <= 0) continue;
                    $kingoTiers[] = [
                        'name' => $label,
                        'target' => $t,
                        'pct' => ($achieved / $t) * 100,
                        'won' => $achieved >= $t,
                    ];
                }
            }

            $orderCustomers = $pipelines
                ->filter(fn($p) => (float) ($p->months->first()->sale_amount ?? 0) > 0)
                ->take(6)
                ->map(fn($p) => [
                    'name' => $p->party_name ?? 'Customer',
                    'product' => $p->product ?? '',
                    'stage' => $p->status_stage ?? '',
                    'sale' => (float) ($p->months->first()->sale_amount ?? 0),
                ])->values()->all();

            // Kingo/Bingo targets are only set for the Sales department, so for
            // Customer Care, New Biz and Export the tier board is permanently
            // empty. Give them their own six months of sales instead — same
            // figures, just measured against their own history rather than a
            // target nobody sets for them.
            $ids = \App\Models\SalesPipeline::where($soldByMe)->where('is_locked', true)->pluck('id');
            if ($ids->isNotEmpty()) {
                $keys = [];
                for ($i = 5; $i >= 0; $i--) {
                    $keys[] = Carbon::today()->subMonths($i)->format('Y-m');
                }
                $sumsQuery = \Illuminate\Support\Facades\DB::table('sales_pipeline_months')
                    ->whereIn('sales_pipeline_id', $ids)->whereIn('month_year', $keys);
                \App\Models\SalesPipeline::whereMonthCredited($sumsQuery, (int) $user->id);
                $sums = $sumsQuery
                    ->groupBy('month_year')
                    ->selectRaw('month_year, SUM(sale_amount) total')
                    ->pluck('total', 'month_year');
                foreach ($keys as $k) {
                    $monthly[] = [
                        'label' => Carbon::createFromFormat('Y-m', $k)->format('M'),
                        'value' => round((float) ($sums[$k] ?? 0), 2),
                    ];
                }
                // Customers held now - not ones that have moved on to CCare.
                $customerCount = \App\Models\SalesPipeline::where($heldByMe)->where('is_locked', true)->count();

                // Financial-year figures, using the same definitions as the
                // manager dashboard so an employee's card and their manager's
                // row agree: Apr-Mar to date, sale/forecast/pending straight
                // off the month rows, book = sold + still pending.
                $fyStartYear = now()->month >= 4 ? now()->year : now()->year - 1;
                $fyMonths = [];
                $cur = Carbon::create($fyStartYear, 4, 1);
                $end = now()->copy()->endOfMonth();
                while ($cur <= $end) { $fyMonths[] = $cur->format('Y-m'); $cur->addMonth(); }

                $fyQuery = \Illuminate\Support\Facades\DB::table('sales_pipeline_months')
                    ->whereIn('sales_pipeline_id', $ids)
                    ->whereIn('month_year', $fyMonths);
                \App\Models\SalesPipeline::whereMonthCredited($fyQuery, (int) $user->id);
                $fy = $fyQuery
                    ->selectRaw('SUM(sale_amount) sold, SUM(forecast_amount) fcst, SUM(pending_amount) pend')
                    ->first();
                $fySold     = (float) ($fy->sold ?? 0);
                $fyForecast = (float) ($fy->fcst ?? 0);
                $fyPending  = (float) ($fy->pend ?? 0);
                $book       = $fySold + $fyPending;
                $pctForecast = $fyForecast > 0 ? round($fySold / $fyForecast * 100, 1) : null;
                $pctPending  = $book > 0 ? round($fySold / $book * 100, 1) : null;

                // Customers still carrying a pending amount this FY.
                $openQuery = \Illuminate\Support\Facades\DB::table('sales_pipeline_months')
                    ->whereIn('sales_pipeline_id', $ids)
                    ->whereIn('month_year', $fyMonths)
                    ->where('pending_amount', '>', 0);
                \App\Models\SalesPipeline::whereMonthCredited($openQuery, (int) $user->id);
                $openOrders = $openQuery->distinct()->count('sales_pipeline_id');
                $prev = (float) ($sums[Carbon::today()->subMonth()->format('Y-m')] ?? 0);
                $lastMonth = $prev;
                $momPct = $prev > 0 ? round((($achieved - $prev) / $prev) * 100, 1) : null;
            }
        } catch (\Throwable $e) {
            // Sales tables absent/renamed — degrade to the "no targets" state.
        }

        return [
            'isSalesDept' => true,
            'todayVisits' => $todayVisits,
            'achieved' => $achieved,
            'hasTargets' => !empty($kingoTiers),
            'kingoTiers' => $kingoTiers,
            'orderCustomers' => $orderCustomers,
            'monthly' => $monthly,
            'customerCount' => $customerCount,
            'fySold' => $fySold, 'fyForecast' => $fyForecast, 'fyPending' => $fyPending,
            'book' => $book, 'pctForecast' => $pctForecast, 'pctPending' => $pctPending,
            'openOrders' => $openOrders,
            'lastMonth' => $lastMonth,
            'momPct' => $momPct,
        ];
    }
}
