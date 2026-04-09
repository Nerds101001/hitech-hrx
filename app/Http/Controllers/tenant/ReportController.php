<?php

namespace App\Http\Controllers\tenant;

use App\Exports\AttendanceExport;
use App\Exports\ExpenseReport;
use App\Exports\LeaveReport;
use App\Exports\VisitExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Modules\ProductOrder\App\Exports\ProductOrderReport;

class ReportController extends Controller
{
  public function index()
  {
    return view('tenant.reports.index', [
      'pageConfigs' => ['contentLayout' => 'wide']
    ]);
  }

  public function getAttendanceReport(Request $request)
  {
    $period = $request->period;

    if (!$period) {
      return redirect()->back()->with('error', 'Please select a period');
    }

    $month = date('m', strtotime($period));

    $year = date('Y', strtotime($period));

    return Excel::download(new AttendanceExport($month, $year), time() . '_attendance_report.xlsx');
  }

  public function getVisitReport(Request $request)
  {
    try {
      $period = $request->period;

      if (!$period) {
        return redirect()->back()->with('error', 'Please select a period');
      }

      $month = date('m', strtotime($period));
      $year = date('Y', strtotime($period));

      return Excel::download(new VisitExport($month, $year), time() . '_visit_report.xlsx');
    } catch (\Exception $e) {
      \Log::error('Visit report generation failed: ' . $e->getMessage());
      return redirect()->back()->with('error', 'Failed to generate visit report. Please try again.');
    }
  }

  public function getLeaveReport(Request $request)
  {
    $period = $request->period;

    if (!$period) {
      return redirect()->back()->with('error', 'Please select a period');
    }

    $month = date('m', strtotime($period));

    $year = date('Y', strtotime($period));

    return Excel::download(new LeaveReport($month, $year), time() . '_leave_report.xlsx');
  }

  public function getExpenseReport(Request $request)
  {
    $period = $request->period;

    if (!$period) {
      return redirect()->back()->with('error', 'Please select a period');
    }

    $month = date('m', strtotime($period));

    $year = date('Y', strtotime($period));

    return Excel::download(new ExpenseReport($month, $year), time() . '_expense_report.xlsx');
  }

  public function getProductOrderReport(Request $request)
  {
    $period = $request->period;

    if (!$period) {
      return redirect()->back()->with('error', 'Please select a period');
    }

    $month = date('m', strtotime($period));
    $year = date('Y', strtotime($period));

    return Excel::download(new ProductOrderReport($month, $year), time() . '_product_order_report.xlsx');
  }
}
