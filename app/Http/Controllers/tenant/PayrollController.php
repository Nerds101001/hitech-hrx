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

        return view('tenant.payroll.index', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'pendingProcessing' => $pendingProcessing,
            'processedThisMonth' => $processedThisMonth,
            'totalPayout' => $totalPayout
        ]);
    }

    public function indexAjax(Request $request)
    {
        $query = Payslip::query()->with('user', 'payrollRecord');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('employee', function ($item) {
                return $item->user->getFullName();
            })
            ->addColumn('month', function ($item) {
                return $item->payrollRecord ? $item->payrollRecord->period : 'N/A';
            })
            ->addColumn('status', function ($item) {
                $status = $item->status;
                $class = 'bg-label-info'; // Draft/Generated
                if ($status === 'approved') $class = 'bg-label-primary';
                if ($status === 'paid') $class = 'bg-label-success';
                
                return '<span class="badge ' . $class . '">' . ucfirst($status) . '</span>';
            })
            ->addColumn('actions', function ($item) {
                return '<div class="d-flex align-items-center gap-2">' .
                    '<button class="btn btn-sm btn-icon hitech-action-icon" onclick="viewPayslip(' . $item->id . ')" title="View"><i class="bx bx-show"></i></button>' .
                    '<button class="btn btn-sm btn-icon hitech-action-icon" onclick="downloadPayslip(' . $item->id . ')" title="Download"><i class="bx bx-download"></i></button>' .
                    '<button class="btn btn-sm btn-icon hitech-action-icon text-danger" onclick="deletePayroll(' . $item->id . ')" title="Delete"><i class="bx bx-trash"></i></button>' .
                    '</div>';
            })
            ->rawColumns(['status', 'actions'])
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
