<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\ExpenseRequest;
use App\Models\ExpenseType;
use App\Models\User;
use App\Notifications\Expense\ExpenseRequestApproval;
use Constants;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ExpenseController extends Controller
{
  public function index()
  {
    $employees = User::all();
    $expenseTypes = ExpenseType::all();
    
    $pendingRequests = ExpenseRequest::where('status', 'pending')->count();
    $approvedToday = ExpenseRequest::where('status', 'approved')
        ->whereDate('updated_at', today())
        ->count();
    $totalThisMonthAmount = ExpenseRequest::where('status', 'approved')
        ->whereMonth('for_date', now()->month)
        ->whereYear('for_date', now()->year)
        ->sum('approved_amount');
    $pendingAmount = ExpenseRequest::where('status', 'pending')->sum('amount');

    return view('tenant.expenses.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'employees' => $employees,
      'expenseTypes' => $expenseTypes,
      'pendingRequests' => $pendingRequests,
      'approvedToday' => $approvedToday,
      'totalThisMonthAmount' => $totalThisMonthAmount,
      'pendingAmount' => $pendingAmount
    ]);
  }

  public function indexAjax(Request $request)
  {
    try {
      $query = ExpenseRequest::query()
        ->with(['user', 'expenseType', 'approvedBy', 'updatedBy'])
        ->select('expense_requests.*');

      // Filters
      if ($request->has('employeeFilter') && !empty($request->input('employeeFilter'))) {
        $query->where('user_id', $request->input('employeeFilter'));
      }
      if ($request->has('dateFilter') && !empty($request->input('dateFilter'))) {
        $query->whereDate('for_date', $request->input('dateFilter'));
      }
      if ($request->has('expenseTypeFilter') && !empty($request->input('expenseTypeFilter'))) {
        $query->where('expense_type_id', $request->input('expenseTypeFilter'));
      }
      if ($request->has('statusFilter') && !empty($request->input('statusFilter'))) {
        $query->where('status', $request->input('statusFilter'));
      }

      // Search
      $search = $request->input('searchTerm') ?? $request->input('search.value');
      if (!empty($search)) {
        $query->where(function ($q) use ($search) {
          $q->whereHas('user', function ($qu) use ($search) {
            $qu->where('first_name', 'LIKE', "%{$search}%")
               ->orWhere('last_name', 'LIKE', "%{$search}%")
               ->orWhere('code', 'LIKE', "%{$search}%");
          })
          ->orWhereHas('expenseType', function ($qu) use ($search) {
            $qu->where('name', 'LIKE', "%{$search}%");
          })
          ->orWhere('expense_requests.id', 'LIKE', "%{$search}%")
          ->orWhere('expense_requests.amount', 'LIKE', "%{$search}%");
        });
      }

      return \Yajra\DataTables\Facades\DataTables::of($query)
        ->addColumn('user_name', function($expenseRequest) {
            return $expenseRequest->user ? $expenseRequest->user->getFullName() : 'N/A';
        })
        ->addColumn('user_code', function($expenseRequest) {
            return $expenseRequest->user->code ?? 'N/A';
        })
        ->addColumn('user_profile_image', function($expenseRequest) {
            return $expenseRequest->user && $expenseRequest->user->profile_picture ? asset('storage/'. \Constants::BaseFolderEmployeeProfileWithSlash . $expenseRequest->user->profile_picture) : null;
        })
        ->addColumn('user_initial', function($expenseRequest) {
            return $expenseRequest->user ? $expenseRequest->user->getInitials() : '??';
        })
        ->addColumn('expense_type_name', function($expenseRequest) {
            return $expenseRequest->expenseType->name ?? 'N/A';
        })
        ->addColumn('document_url_formatted', function($expenseRequest) {
            return $expenseRequest->document_url ? asset('storage/'. \Constants::BaseFolderExpenseProofs . $expenseRequest->document_url) : null;
        })
        ->addColumn('approved_by_name', function($expenseRequest) {
            if ($expenseRequest->approvedBy) return $expenseRequest->approvedBy->getFullName();
            
            if ($expenseRequest->status === 'approved') {
                if ($expenseRequest->updatedBy) return $expenseRequest->updatedBy->getFullName();
                if ($expenseRequest->approved_by_id) {
                    $admin = \App\Models\User::find($expenseRequest->approved_by_id);
                    if ($admin) return $admin->getFullName();
                }
                return 'Demo HR'; 
            }
            return 'N/A';
        })
        ->addColumn('approved_at_formatted', function($expenseRequest) {
            $date = $expenseRequest->approved_at ?? ($expenseRequest->status === 'approved' ? $expenseRequest->updated_at : null);
            return $date ? $date->format('d/m H:i') : 'N/A';
        })
        ->make(true);
    } catch (Exception $e) {
      Log::error('Expense indexAjax Error: ' . $e->getMessage());
      return response()->json(['error' => 'Something went wrong.'], 500);
    }
  }

  public function actionAjax(Request $request)
  {

    $validated = $request->validate([
      'id' => 'required|exists:expense_requests,id',
      'status' => 'required|in:approved,rejected',
      'approvedAmount' => 'nullable|numeric',
      'adminRemarks' => 'nullable|string',
    ]);

    try {
      $expenseRequest = ExpenseRequest::findOrFail($validated['id']);
      $expenseRequest->status = $validated['status'];
      
      // Fallback to original amount if not specified
      $approvedAmount = $validated['approvedAmount'] ?? null;
      if (($validated['status'] == 'approved') && ($approvedAmount === null || $approvedAmount < 0)) {
          $approvedAmount = $expenseRequest->amount;
      }
      $expenseRequest->approved_amount = $approvedAmount;
      $expenseRequest->admin_remarks = $validated['adminRemarks'];
      
      if ($validated['status'] == 'approved') {
        $expenseRequest->approved_by_id = auth()->id();
        $expenseRequest->approved_at = now();
      } elseif ($validated['status'] == 'rejected') {
        $expenseRequest->rejected_by_id = auth()->id();
        $expenseRequest->rejected_at = now();
      }
      
      $expenseRequest->save();

      Notification::send($expenseRequest->user, new ExpenseRequestApproval($expenseRequest, $validated['status']));

      return back()->with('success', 'Expense request ' . $validated['status'] . ' successfully.');
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return back()->with('error', 'Something went wrong. Please try again.');
    }
  }

  public function getByIdAjax($id)
  {
    $expenseRequest = ExpenseRequest::findOrFail($id);

    if (!$expenseRequest) {
      return Error::response('Expense request not found.');
    }

    $response = [
      'id' => $expenseRequest->id,
      'userName' => $expenseRequest->user->getFullName(),
      'userCode' => $expenseRequest->user->code,
      'userInitials' => $expenseRequest->user->getInitials(),
      'user_profile_image' => $expenseRequest->user->profile_picture != null ? asset('storage/'.Constants::BaseFolderEmployeeProfileWithSlash . $expenseRequest->user->profile_picture) : null,
      'expenseType' => $expenseRequest->expenseType->name,
      'forDate' => $expenseRequest->for_date->format(Constants::DateFormat),
      'amount' => $expenseRequest->amount,
      'approvedAmount' => $expenseRequest->approved_amount ?? $expenseRequest->amount,
      'document' => $expenseRequest->document_url != null ? asset('storage/'.Constants::BaseFolderExpenseProofs . $expenseRequest->document_url) : null,
      'status' => $expenseRequest->status,
      'createdAt' => $expenseRequest->created_at->format(Constants::DateTimeFormat),
      'userNotes' => $expenseRequest->remarks
    ];

    return Success::response($response);
  }


}
