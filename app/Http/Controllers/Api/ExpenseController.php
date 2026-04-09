<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\ExpenseRequest;
use App\Models\ExpenseType;
use App\Notifications\Expense\CancelExpenseRequest;
use App\Notifications\Expense\NewExpenseRequest;
use Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
  public function getExpenseTypes()
  {
    $expenseTypes = ExpenseType::where('status', 1)->get();

    $response = $expenseTypes->map(function ($expenseType) {
      return [
        'id' => $expenseType->id,
        'name' => $expenseType->name,
        'isImgRequired' => $expenseType->is_proof_required,
      ];
    });

    return Success::response($response);
  }

  public function getExpenseRequests(Request $request)
  {
    $skip = $request->skip;
    $take = $request->take ?? 10;


    $expenseRequests = ExpenseRequest::query()
      ->where('user_id', auth()->id())
      ->with('expenseType')
      ->orderBy('id', 'desc');

    if ($request->has('status')) {
      $expenseRequests->where('status', $request->status);
    }

    if ($request->has('date') && !empty($request->date)) {
      $expenseRequests->whereDate('for_date', $request->date);
    }

    $totalCount = $expenseRequests->count();

    $expenseRequests = $expenseRequests->skip($skip)->take($take)->get();

    $expenseRequests = $expenseRequests->map(function ($expenseRequest) {
      return [
        'id' => $expenseRequest->id,
        'date' => $expenseRequest->for_date->format(Constants::DateTimeFormat),
        'type' => $expenseRequest->expenseType->name,
        'actualAmount' => doubleval($expenseRequest->amount),
        'approvedAmount' => $expenseRequest->approved_amount != null ? floatval($expenseRequest->approved_amount) : null,
        'comments' => $expenseRequest->remarks,
        'status' => $expenseRequest->status,
        'createdAt' => $expenseRequest->created_at->format(Constants::DateTimeFormat),
        'approvedAt' => $expenseRequest->approved_at != null ? $expenseRequest->approved_at : '',
        'approvedBy' => $expenseRequest->approved_by_id != null ? 'Admin' : '',
      ];
    });

    $response = [
      'totalCount' => $totalCount,
      'values' => $expenseRequests
    ];

    return Success::response($response);
  }

  public function cancel(Request $request)
  {
    $expenseRequest = ExpenseRequest::find($request->id);

    if ($expenseRequest == null) {
      return Error::response('Expense Request not found');
    }

    if ($expenseRequest->status != 'pending') {
      return Error::response('Expense Request cannot be cancelled');
    }

    $expenseRequest->status = 'cancelled';
    $expenseRequest->save();

    NotificationHelper::notifyAdminHR(new CancelExpenseRequest($expenseRequest));

    return Success::response('Expense Request cancelled successfully');

  }

  public function uploadExpenseDocument(Request $request)
  {

    $file = $request->file('file');

    if ($file == null) {
      Error::response('File is required');
    }

    $lastExpenseRequest = ExpenseRequest::where('user_id', auth()->user()->id)->orderBy('id', 'desc')->first();

    if ($lastExpenseRequest == null) {
      Error::response('No expense request found');
    }

    $fileName = time() . '_' . $file->getClientOriginalName();
    Storage::disk('public')->putFileAs(Constants::BaseFolderExpenseProofs, $file, $fileName);

    $lastExpenseRequest->document_url = $fileName;
    $lastExpenseRequest->save();

    return Success::response('Document uploaded successfully');
  }


  public function createExpenseRequest(Request $request)
  {
    $date = $request->date;
    $amount = $request->amount;
    $expenseTypeId = $request->typeId;
    $remarks = $request->comments;

    if ($date == null) {
      return Error::response('Date is required');
    }

    if ($amount == null) {
      return Error::response('Amount is required');
    }

    if ($amount <= 0) {
      return Error::response('Amount should be greater than 0');
    }

    if ($expenseTypeId == null) {
      return Error::response('Expense Type is required');
    }

    if ($remarks == null) {
      return Error::response('Remarks is required');
    }

    $finalForDate = strtotime($date);

    $expenseRequest = new ExpenseRequest();
    $expenseRequest->user_id = auth()->user()->id;
    $expenseRequest->for_date = date('Y-m-d', $finalForDate);
    $expenseRequest->amount = $amount;
    $expenseRequest->expense_type_id = $expenseTypeId;
    $expenseRequest->remarks = $remarks;
    $expenseRequest->status = 'pending';

    $expenseRequest->save();

    NotificationHelper::notifyAdminHR(new NewExpenseRequest($expenseRequest));

    return Success::response('Expense Request Created Successfully');
  }
}
