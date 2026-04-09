<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\LeaveRequestStatus;
use App\Enums\Status;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Notifications\Leave\CancelLeaveRequest;
use App\Notifications\Leave\NewLeaveRequest;
use App\Services\LeavePolicyService;
use Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaveController extends Controller
{
  public function getLeaveTypes()
  {
    $user = auth()->user();
    $leaveTypes = LeaveType::where('status', Status::ACTIVE)
      ->where(function ($q) use ($user) {
          $q->whereNull('site_id')
            ->orWhere('site_id', $user->site_id);
      })->get();

    $response = $leaveTypes->map(function ($leaveType) {
      return [
        'id' => $leaveType->id,
        'name' => $leaveType->name,
        'isImgRequired' => $leaveType->is_proof_required,
      ];
    });

    return Success::response($response);
  }

  public function getLeaveRequests(Request $request)
  {
    $skip = $request->skip;
    $take = $request->take ?? 10;


    $leaveRequests = LeaveRequest::query()
      ->where('user_id', auth()->id())
      ->with('leaveType')
      ->orderBy('id', 'desc');

    if ($request->has('status')) {
      $leaveRequests->where('status', LeaveRequestStatus::from($request->status));
    }

    $totalCount = $leaveRequests->count();

    $leaveRequests = $leaveRequests->skip($skip)->take($take)->get();

    $leaveRequests = $leaveRequests->map(function ($leaveRequest) {
      return [
        'id' => $leaveRequest->id,
        'fromDate' => $leaveRequest->from_date->format(Constants::DateFormat),
        'toDate' => $leaveRequest->to_date->format(Constants::DateFormat),
        'leaveType' => $leaveRequest->leaveType->name,
        'comments' => $leaveRequest->notes,
        'status' => $leaveRequest->status,
        'createdOn' => $leaveRequest->created_at->format(Constants::DateTimeFormat),
        'approvedOn' => $leaveRequest->approved_at != null ? $leaveRequest->approved_at : '',
        'approvedBy' => $leaveRequest->approved_at != null ? 'Admin' : '',
      ];
    });

    $response = [
      'totalCount' => $totalCount,
      'values' => $leaveRequests
    ];

    return Success::response($response);
  }

  public function uploadLeaveDocument(Request $request)
  {
    $file = $request->file('file');

    if ($file == null) {
      return Error::response('File is required');
    }

    $lastLeaveRequest = LeaveRequest::where('user_id', auth()->user()->id)->orderBy('id', 'desc')->first();

    if ($lastLeaveRequest == null) {
      return Error::response('Leave request not found');
    }

    $fileName = time() . '_' . $file->getClientOriginalName();
    Storage::disk('public')->putFileAs(Constants::BaseFolderLeaveRequestDocument, $file, $fileName);

    $lastLeaveRequest->document = $fileName;

    $lastLeaveRequest->save();

    return Success::response('Document uploaded successfully');

  }

  public function cancelLeaveRequest(Request $request)
  {
    $req = $request->all();

    $leaveRequestId = reset($req);

    if ($leaveRequestId == null) {
      Error::response('Leave request Id is required');
    }

    $leaveRequest = LeaveRequest::find($leaveRequestId);

    if ($leaveRequest == null) {
      Error::response('Leave request not found');
    }

    if ($leaveRequest->status != LeaveRequestStatus::PENDING) {
      Error::response('Leave request cannot be cancelled');
    }

    $leaveRequest->status = LeaveRequestStatus::CANCELLED;
    $leaveRequest->save();

    NotificationHelper::notifyAdminHR(new CancelLeaveRequest($leaveRequest));

    return Success::response('Leave request cancelled successfully');
  }

  public function createLeaveRequest(Request $request)
  {

    $fromDate    = $request->fromDate;
    $toDate      = $request->toDate;
    $leaveTypeId = $request->leaveType;
    $remarks     = $request->comments;

    if ($fromDate > $toDate) {
      return Error::response('From date cannot be greater than to date');
    }

    if ($leaveTypeId == null) {
      return Error::response('Leave type is required');
    }

    $leaveType = LeaveType::find($leaveTypeId);

    if ($leaveType == null) {
      return Error::response('Leave type not found');
    }

    $finalFromDate = date('Y-m-d', strtotime($fromDate));
    $finalToDate   = date('Y-m-d', strtotime($toDate));

    $user = auth()->user();

    // Unit leave policy enforcement
    $policyError = LeavePolicyService::validate($user, $leaveTypeId, $finalFromDate, $finalToDate);
    if ($policyError) {
      return Error::response($policyError);
    }

    $leaveRequest = LeaveRequest::create([
      'from_date'     => $finalFromDate,
      'to_date'       => $finalToDate,
      'leave_type_id' => $leaveTypeId,
      'user_notes'    => $remarks,
      'user_id'       => $user->id,
    ]);

    NotificationHelper::notifyAdminHR(new NewLeaveRequest($leaveRequest));

    return Success::response('Leave request created successfully');
  }
}
