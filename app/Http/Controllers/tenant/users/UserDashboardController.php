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

        // Show Restricted View if status is Submitted (Review Required)
        if ($userStatus === UserAccountStatus::ONBOARDING_SUBMITTED->value) {
            return view('tenant.users.dashboard.review-restricted');
        }

        $isHR = $user->hasRole('hr');
        $isFieldEmployee = $user->hasRole('employee');
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
                'latestNetSalary' => $latestNetSalary
            ]);
        }

        // 2. Employee Dashboard
        if ($isFieldEmployee) {
            $settings = Settings::first();
            return view('tenant.users.dashboard.employee-index', compact(
                'myLeavesCount',
                'myExpensesCount',
                'myAttendanceCount',
                'mySOSCount',
                'nextHoliday',
                'recentNotices',
                'payrollTrend',
                'latestNetSalary',
                'settings'
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
            return view('tenant.users.dashboard.manager-index', [
                'pendingLeaveRequests' => $pendingLeaveRequests,
                'pendingExpenseRequests' => $pendingExpenseRequests,
                'activeEmployees' => $active, // Total active for now
                'todayPresentUsers' => $presentUsersCount,
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
        $leaves = LeaveRequest::where('user_id', $user->id)->with('leaveType')->orderBy('id', 'desc')->get();
        
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
                if ($code === 'MAT') {
                    return $isMarried && $gender === 'female';
                }
                
                // Paternity - Only for Married Males
                if ($code === 'PAT') {
                    return $isMarried && $gender === 'male';
                }
                
                return true;
            });

        $settings = Settings::first();
        return view('tenant.users.leaves.index', compact('leaves', 'leaveTypes', 'settings'));
    }

    public function leaveStore(Request $request)
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date'     => 'required|date|after_or_equal:today',
            'to_date'       => 'required|date|after_or_equal:from_date',
            'user_notes'    => 'required|string|max:1000',
            'document'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $leaveType = LeaveType::find($validated['leave_type_id']);
        $user = auth()->user();

        // 1. Gender Restriction Check
        if ($leaveType->code === 'MAT' && strtolower($user->gender ?? '') !== 'female') {
            return redirect()->back()->withErrors(['policy' => 'Maternity leave is only applicable for female employees.'])->withInput();
        }
        if ($leaveType->code === 'PAT' && strtolower($user->gender ?? '') !== 'male') {
            return redirect()->back()->withErrors(['policy' => 'Paternity leave is only applicable for male employees.'])->withInput();
        }

        // 2. Evidence/Proof Requirement Check
        if (($leaveType->is_proof_required || in_array($leaveType->code, ['MAT', 'PAT'])) && !$request->hasFile('document')) {
            return redirect()->back()->withErrors(['document' => 'Proof/Evidence document is required for this leave type.'])->withInput();
        }

        // Unit leave policy enforcement
        $error = LeavePolicyService::validate(
            auth()->user(),
            $validated['leave_type_id'],
            $validated['from_date'],
            $validated['to_date']
        );
        if ($error) {
            return redirect()->back()->withErrors(['policy' => $error])->withInput();
        }

        $leaveRequest = new LeaveRequest();
        $leaveRequest->user_id       = auth()->id();
        $leaveRequest->leave_type_id = $validated['leave_type_id'];
        $leaveRequest->from_date     = $validated['from_date'];
        $leaveRequest->to_date       = $validated['to_date'];
        $leaveRequest->user_notes    = $validated['user_notes'];
        $leaveRequest->status        = LeaveRequestStatus::PENDING;

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

        NotificationHelper::notifyAdminHR(new NewExpenseRequest($expenseRequest));

        return redirect()->back()->with('success', 'Expense request submitted successfully.');
    }

    public function attendanceIndex(Request $request)
    {
        $user = auth()->user();
        $query = Attendance::where('user_id', $user->id);

        // Filter Logic
        $filter = $request->input('filter', 'this_month');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        if ($request->has('month') && $request->has('year')) {
            $query->whereMonth('created_at', $month)->whereYear('created_at', $year);
            $filter = 'custom_month'; // Internal flag
        } elseif ($filter === 'today') {
            $query->whereDate('created_at', now());
        } elseif ($filter === 'this_week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($filter === 'this_month') {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        } elseif ($filter === 'last_month') {
            $query->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year);
        } elseif ($filter === 'custom' && $startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $attendances = $query->orderBy('id', 'desc')->get();
        
        $presentDays = 0;
        $lateDays = 0;
        $absentDays = 0;
        $totalHours = 0;
        $workCount = 0;

        foreach($attendances as $a) {
            $s = strtolower($a->status ?: 'present');
            
            // Dynamic enforcement of 7:45 threshold rule (465 mins)
            if (empty($a->admin_reason) && $a->check_in_time && $a->check_out_time) {
                $mins = $a->check_in_time->diffInMinutes($a->check_out_time);
                if ($mins < 465 && $s === 'present') {
                    $s = 'half-day';
                }
            }
            
            if ($s === 'absent') {
                $absentDays++;
            } elseif ($s === 'late' || $s === 'half-day') {
                $lateDays++;
            } elseif (in_array($s, ['on_leave', 'leave', 'work_from_home', 'wfh'])) {
                // Not counted towards present in cards
            } else {
                $presentDays++;
            }

            // For dynamic rendering in blade
            $a->dynamic_status = $s;

            if ($a->check_in_time && $a->check_out_time) {
                $totalHours += $a->check_in_time->diffInMinutes($a->check_out_time) / 60;
                $workCount++;
            }
        }
        
        $avgHours = $workCount > 0 ? round($totalHours / $workCount, 1) : 0;

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
