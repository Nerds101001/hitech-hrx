<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\ApiClasses\Success;
use App\ApiClasses\Error;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function index()
    {
        $now = now();
        $periodName = $now->format('F Y');

        // Optimized: Single query to get counts, sums, and user IDs
        $monthStats = Payslip::whereHas('payrollRecord', function($q) use ($periodName) {
            $q->where('period', $periodName);
        })
        ->selectRaw('count(*) as count, sum(net_salary) as total_payout, GROUP_CONCAT(user_id) as processed_user_ids')
        ->first();

        $processedThisMonth = $monthStats->count ?? 0;
        $totalPayout = $monthStats->total_payout ?? 0;
        $processedUserIds = explode(',', $monthStats->processed_user_ids ?? '');

        $pendingProcessing = User::where('status', 'active')
            ->whereNotIn('id', array_filter($processedUserIds))
            ->count();

        $departments = \App\Models\Department::all();
        $sites = \App\Models\Site::all();

        return view('tenant.payroll.index', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'pendingProcessing' => $pendingProcessing,
            'processedThisMonth' => $processedThisMonth,
            'totalPayout' => $totalPayout,
            'departments' => $departments,
            'sites' => $sites
        ]);
    }

    public function indexAjax(Request $request)
    {
        $query = Payslip::query()->with(['user.designation.department', 'user.site', 'payrollRecord']);

        // Apply Filters
        if ($request->filled('department_id')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }
        if ($request->filled('site_id')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('site_id', $request->site_id);
            });
        }
        if ($request->filled('month')) {
            $monthName = date('F', mktime(0, 0, 0, $request->month, 1));
            $year = now()->year;
            $periodSearch = $monthName . ' ' . $year;
            
            $query->whereHas('payrollRecord', function($q) use ($periodSearch) {
                $q->where('period', 'like', '%' . $periodSearch . '%');
            });
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('present_days', function ($item) {
                return '<span class="badge bg-label-success">' . $item->total_worked_days . '</span>';
            })
            ->addColumn('absent_days', function ($item) {
                return '<span class="badge bg-label-danger">' . $item->total_absent_days . '</span>';
            })
            ->addColumn('off_days', function ($item) {
                $offs = ($item->total_holidays ?? 0) + ($item->total_weekends ?? 0);
                return '<span class="badge bg-label-secondary">' . $offs . '</span>';
            })
            ->addColumn('allotted_basic', function ($item) {
                $userBaseSalary = $item->user->base_salary ?? 0;
                $user = $item->user;
                $policy = $user->salaryPolicy ?? \App\Models\SalaryPolicy::where('site_id', $user->site_id)->first() ?? \App\Models\SalaryPolicy::first();
                $fullBasic = $user->custom_basic !== null ? $user->custom_basic : ($policy ? ($userBaseSalary * ($policy->basic_percentage / 100)) : ($userBaseSalary * 0.50));
                return '₹' . number_format($fullBasic, 2);
            })
            ->addColumn('allotted_hra', function ($item) {
                $userBaseSalary = $item->user->base_salary ?? 0;
                $user = $item->user;
                $policy = $user->salaryPolicy ?? \App\Models\SalaryPolicy::where('site_id', $user->site_id)->first() ?? \App\Models\SalaryPolicy::first();
                $fullHra = $user->custom_hra !== null ? $user->custom_hra : ($policy ? ($userBaseSalary * ($policy->hra_percentage / 100)) : ($userBaseSalary * 0.25));
                return '₹' . number_format($fullHra, 2);
            })
            ->addColumn('allotted_other', function ($item) {
                $userBaseSalary = $item->user->base_salary ?? 0;
                $user = $item->user;
                $policy = $user->salaryPolicy ?? \App\Models\SalaryPolicy::where('site_id', $user->site_id)->first() ?? \App\Models\SalaryPolicy::first();
                
                $fullBasic = $user->custom_basic !== null ? $user->custom_basic : ($policy ? ($userBaseSalary * ($policy->basic_percentage / 100)) : ($userBaseSalary * 0.50));
                $fullHra = $user->custom_hra !== null ? $user->custom_hra : ($policy ? ($userBaseSalary * ($policy->hra_percentage / 100)) : ($userBaseSalary * 0.25));
                
                $fullCa = $user->custom_ca !== null ? $user->custom_ca : ($policy ? $policy->ca_fixed : 0.00);
                $fullMedical = $user->custom_medical !== null ? $user->custom_medical : ($policy ? $policy->medical_fixed : 0.00);
                $fullEdu = $user->custom_edu !== null ? $user->custom_edu : ($policy ? $policy->edu_fixed : 0.00);
                
                $fullSpecial = $user->custom_special_allowance !== null ? $user->custom_special_allowance : max(0.00, $userBaseSalary - ($fullBasic + $fullHra + $fullCa + $fullMedical + $fullEdu));
                
                $totalOther = $fullCa + $fullMedical + $fullEdu + $fullSpecial;
                return '₹' . number_format($totalOther, 2);
            })
            ->addColumn('payable_basic', function ($item) {
                $basic = $item->basic_salary ?? 0;
                return '₹' . number_format($basic, 2);
            })
            ->addColumn('payable_hra', function ($item) {
                $hra = $item->hra ?? 0;
                return '₹' . number_format($hra, 2);
            })
            ->addColumn('payable_other', function ($item) {
                $other = ($item->ca ?? 0) + ($item->medical ?? 0) + ($item->edu ?? 0) + ($item->special_allowance ?? 0);
                return '₹' . number_format($other, 2);
            })
            ->addColumn('net_payable', function ($item) {
                return '<span class="fw-bold text-primary">₹' . number_format($item->net_salary, 0) . '</span>';
            })
            ->addColumn('month', function ($item) {
                return $item->payrollRecord ? $item->payrollRecord->period : 'N/A';
            })
            ->editColumn('status', function ($item) {
                $status = $item->status ?? 'generated';
                $class = 'bg-label-info';
                if ($status === 'approved') $class = 'bg-label-primary';
                if ($status === 'paid') $class = 'bg-label-success';
                return '<span class="badge ' . $class . '">' . ucfirst($status) . '</span>';
            })
            ->addColumn('employee', function ($item) {
                $user = $item->user;
                $code = $item->user->code ?? $item->user->biometric_id ?? 'N/A';
                
                return '<div class="d-flex flex-column">' .
                       '<span class="fw-bold text-heading" style="font-size: 0.85rem;">' . $user->getFullName() . '</span>' .
                       '<small class="text-muted" style="font-size: 0.7rem;">Code: ' . $code . '</small>' .
                       '</div>';
            })
            ->addColumn('actions', function ($item) {
                $status  = $item->status ?? 'generated';
                $publish = '';
                if ($status === 'generated') {
                    $publish = '<button class="btn btn-sm btn-icon hitech-action-icon-sm" onclick="publishSingle(' . $item->id . ')" title="Publish to Employee" style="background:#f0fdf4;border-color:#bbf7d0;"><i class="bx bx-send" style="color:#16a34a;"></i></button>';
                }
                return '<div class="d-flex align-items-center gap-1">' .
                    '<button class="btn btn-sm btn-icon hitech-action-icon-sm" onclick="viewPayslip(' . $item->id . ')" title="View"><i class="bx bx-show text-primary"></i></button>' .
                    '<button class="btn btn-sm btn-icon hitech-action-icon-sm" onclick="downloadPayslip(' . $item->id . ')" title="Download"><i class="bx bx-download text-success"></i></button>' .
                    $publish .
                    '<button class="btn btn-sm btn-icon hitech-action-icon-sm" onclick="deletePayroll(' . $item->id . ')" title="Delete"><i class="bx bx-trash text-danger"></i></button>' .
                    '</div>';
            })
            ->rawColumns(['present_days', 'absent_days', 'off_days', 'status', 'employee', 'net_payable', 'actions'])
            ->make(true);
    }

    /**
     * Bulk generate payroll records.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        try {
            // Manual generation also starts as 'generated' (draft)
            $count = $this->payrollService->processBulk($request->month, $request->year, 'generated');
            
            if ($count > 0) {
                return redirect()->back()->with('success', "$count payroll records generated as draft.");
            } else {
                return redirect()->back()->with('info', "No new payroll records were generated. They might already exist for this period.");
            }
        } catch (\Exception $e) {
            Log::error('Payroll Generation Error: ' . $e->getMessage());
            return redirect()->back()->with('error', "Failed to generate payroll: " . $e->getMessage());
        }
    }

    /**
     * Bulk approve payroll records.
     */
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:payslips,id'
        ]);

        try {
            DB::transaction(function() use ($request) {
                $payslips = Payslip::whereIn('id', $request->ids)->get();
                foreach ($payslips as $payslip) {
                    $payslip->status = 'approved';
                    $payslip->save();

                    if ($payslip->payrollRecord) {
                        $payslip->payrollRecord->status = 'approved';
                        $payslip->payrollRecord->save();
                    }
                }
            });

            return Success::response(null, count($request->ids) . " payroll records approved and published.");
        } catch (\Exception $e) {
            return Error::response("Failed to approve records: " . $e->getMessage());
        }
    }

    /**
     * Publish (approve) a single payslip/payroll record.
     * Makes it visible to the employee.
     */
    public function publishSingle($id)
    {
        try {
            $payslip = Payslip::findOrFail($id);

            if (in_array($payslip->status, ['approved', 'paid'])) {
                return Error::response("This payslip is already published.", 400);
            }

            DB::transaction(function () use ($payslip) {
                $payslip->status = 'approved';
                $payslip->save();

                if ($payslip->payrollRecord) {
                    $payslip->payrollRecord->status = 'approved';
                    $payslip->payrollRecord->save();
                }
            });

            return Success::response(null, "Payslip published. The employee can now view it.");
        } catch (\Exception $e) {
            return Error::response("Failed to publish: " . $e->getMessage());
        }
    }

    /**
     * Delete a payroll record and its associated payslip.
     */
    public function destroyAjax($id)
    {
        try {
            $payslip = Payslip::findOrFail($id);
            $record = $payslip->payrollRecord;

            // Optional: check if already paid before allowing deletion
            if ($record && $record->status === 'paid') {
                return Error::response("Cannot delete a record that has already been paid.", 400);
            }

            if ($record) $record->delete();
            $payslip->delete();

            return Success::response(null, "Payroll record deleted successfully.");
        } catch (\Exception $e) {
            return Error::response("Failed to delete record: " . $e->getMessage());
        }
    }
}
