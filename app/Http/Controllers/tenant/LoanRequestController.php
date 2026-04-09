<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Models\LoanRequest;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoanRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $totalLoanAmount = LoanRequest::where('status', 'Approved')->sum('approved_amount');
        $pendingLoans = LoanRequest::where('status', 'Pending')->count();
        $approvedLoans = LoanRequest::where('status', 'Approved')->count();
        $rejectedLoans = LoanRequest::where('status', 'Rejected')->count();

        return view('tenant.loanRequests.index', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'totalLoanAmount' => $totalLoanAmount,
            'pendingLoans' => $pendingLoans,
            'approvedLoans' => $approvedLoans,
            'rejectedLoans' => $rejectedLoans
        ]);
    }

    /**
     * Get list of loan requests via Ajax for DataTables.
     */
    public function getListAjax(Request $request)
    {
        try {
            $columns = [
                0 => 'id',
                1 => 'user_id',
                2 => 'amount',
                3 => 'approved_amount',
                4 => 'status',
                5 => 'created_at',
            ];

            $query = LoanRequest::with(['user']);

            $totalData = $query->count();
            $totalFiltered = $totalData;

            $limit = $request->input('length', 10);
            $start = $request->input('start', 0);
            $order = $columns[$request->input('order.0.column')] ?? 'id';
            $dir = $request->input('order.0.dir', 'desc');

            if (!empty($request->input('search.value'))) {
                $search = $request->input('search.value');
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('first_name', 'like', "%{$search}%")
                             ->orWhere('last_name', 'like', "%{$search}%");
                      })
                      ->orWhere('amount', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%");
                });
                $totalFiltered = $query->count();
            }

            $loans = $query->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();

            $data = [];
            foreach ($loans as $loan) {
                $data[] = [
                    'id' => $loan->id,
                    'user' => $loan->user ? $loan->user->full_name : 'N/A',
                    'amount' => $loan->amount,
                    'approved_amount' => $loan->approved_amount ?? '-',
                    'status' => $loan->status,
                    'remarks' => $loan->remarks,
                    'created_at' => $loan->created_at->format('d M Y, h:i A'),
                    'action' => '',
                ];
            }

            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => intval($totalData),
                'recordsFiltered' => intval($totalFiltered),
                'data' => $data
            ]);
        } catch (Exception $e) {
            Log::error("LoanRequestController@getListAjax: " . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Update the status of a loan request.
     */
    public function actionAjax(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'status' => 'required|in:Approved,Rejected,Pending',
            'approved_amount' => 'nullable|numeric',
            'admin_remarks' => 'nullable|string'
        ]);

        try {
            $loan = LoanRequest::findOrFail($request->id);
            $loan->status = $request->status;
            $loan->approved_amount = $request->status == 'Approved' ? ($request->approved_amount ?? $loan->amount) : null;
            $loan->admin_remarks = $request->admin_remarks;
            $loan->action_taken_by_id = Auth::id();
            $loan->action_taken_at = now();
            $loan->save();

            return Success::response('Loan request updated successfully');
        } catch (Exception $e) {
            Log::error("LoanRequestController@actionAjax: " . $e->getMessage());
            return Error::response('Something went wrong');
        }
    }

    /**
     * Get details of a single request.
     */
    public function getByIdAjax($id)
    {
        try {
            $loan = LoanRequest::with(['user'])->findOrFail($id);
            return Success::response($loan);
        } catch (Exception $e) {
            return Error::response('Loan request not found');
        }
    }
}
