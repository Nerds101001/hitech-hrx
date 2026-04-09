<?php

namespace App\Http\Controllers\tenant;

use App\Enums\Status;
use App\Models\Settings;
use App\ApiClasses\Error;
use App\Models\LeaveType;
use App\ApiClasses\Success;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class LeaveTypeController extends Controller
{
  public function index()
  {
    $sites = \App\Models\Site::all();
    return view('tenant.leaveTypes.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'sites' => $sites
    ]);
  }

  public function getLeaveTypesAjax(Request $request)
  {
    try {
      $columns = [
        1 => 'id',
        2 => 'name',
        3 => 'notes',
        4 => 'code',
        5 => 'site_id',
        6 => 'status',
      ];

      $search = [];

      $totalData = LeaveType::count();

      $totalFiltered = $totalData;

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');

      if (empty($request->input('search.value'))) {
        $leaveTypes = LeaveType::offset($start)
          ->limit($limit)
          ->orderBy($order, $dir)
          ->with('site')
          ->get();
      } else {
        $search = $request->input('search.value');
        $leaveTypes = LeaveType::where('id', 'like', "%{$search}%")
          ->orWhere('name', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%")
          ->orWhere('notes', 'like', "%{$search}%")
          ->with('site')
          ->get();

        $totalFiltered = LeaveType::where('id', 'like', "%{$search}%")
          ->orWhere('name', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%")
          ->orWhere('notes', 'like', "%{$search}%")
          ->count();
      }

      $data = [];
      if (!empty($leaveTypes)) {
        foreach ($leaveTypes as $leaveType) {
          $nestedData['id'] = $leaveType->id;
          $nestedData['name'] = $leaveType->name;
          $nestedData['code'] = $leaveType->code;
          $nestedData['notes'] = $leaveType->notes;
          $nestedData['site_id'] = $leaveType->site_id;
          $nestedData['site_name'] = $leaveType->site ? $leaveType->site->name : 'All Units';
          $nestedData['is_short_leave'] = $leaveType->is_short_leave;
          $nestedData['is_paid'] = $leaveType->is_paid;
          $nestedData['status'] = $leaveType->status;
          $nestedData['action'] = '<div class="d-flex align-items-center justify-content-center gap-2">' .
            '<a href="javascript:;" class="text-hitech edit-record me-2" data-id="' . $leaveType->id . '" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateLeaveType" title="Edit"><i class="bx bx-edit fs-4"></i></a>' .
            '<a href="javascript:;" class="text-danger delete-record" data-id="' . $leaveType->id . '" title="Delete"><i class="bx bx-trash fs-4"></i></a>' .
            '</div>';
          $data[] = $nestedData;
        }
      }

      return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => intval($totalData),
        'recordsFiltered' => intval($totalFiltered),
        'code' => 200,
        'data' => $data
      ]);
    } catch (\Exception $e) {
      return Error::response($e->getMessage());
    }
  }

  public function addOrUpdateLeaveTypeAjax(Request $request)
  {
    $leaveTypeId = $request->id;
    $request->validate([
      'name' => 'required',
      'code' => [
        'required',
        'unique:leave_types,code,' . $leaveTypeId . ',id,tenant_id,' . auth()->user()->tenant_id
      ],
      'notes' => 'nullable',
      'isProofRequired' => 'nullable',
      'isShortLeave' => 'nullable',
      'isPaid' => 'nullable',
      'isCarryForward' => 'nullable',
      'isSplitEntitlement' => 'nullable',
      'isConsecutiveAllowed' => 'nullable',
      'isStrictRules' => 'nullable',
      'site_id' => 'nullable|exists:sites,id',
    ]);

    if ($leaveTypeId) {
      $leaveType = LeaveType::find($leaveTypeId);
      $leaveType->name = $request->name;
      $leaveType->notes = $request->notes;
      $leaveType->code = $request->code;
      $leaveType->is_proof_required = $request->isProofRequired;
      $leaveType->is_short_leave = $request->isShortLeave;
      $leaveType->is_paid = $request->isPaid;
      $leaveType->is_carry_forward = $request->isCarryForward;
      $leaveType->is_split_entitlement = $request->isSplitEntitlement;
      $leaveType->is_consecutive_allowed = $request->isConsecutiveAllowed;
      $leaveType->is_strict_rules = $request->isStrictRules;
      $leaveType->site_id = $request->site_id;
      $leaveType->save();

      return response()->json([
        'code' => 200,
        'message' => 'Updated',
      ]);
    } else {

      $leaveType = new LeaveType();
      $leaveType->name = $request->name;
      $leaveType->notes = $request->notes;
      $leaveType->code = $request->code;
      $leaveType->is_proof_required = $request->isProofRequired;
      $leaveType->is_short_leave = $request->isShortLeave;
      $leaveType->is_paid = $request->isPaid;
      $leaveType->is_carry_forward = $request->isCarryForward;
      $leaveType->is_split_entitlement = $request->isSplitEntitlement;
      $leaveType->is_consecutive_allowed = $request->isConsecutiveAllowed;
      $leaveType->is_strict_rules = $request->isStrictRules;
      $leaveType->site_id = $request->site_id;

      $leaveType->save();

      return response()->json([
        'code' => 200,
        'message' => 'Added',
      ]);
    }
  }

  public function checkCodeValidationAjax(Request $request)
  {
    $code = $request->code;


    if (!$code) {
      return response()->json(["valid" => false]);
    }

    if ($request->has('id')) {
      $id = $request->input('id');
      if (LeaveType::where('code', $code)->where('id', '!=', $id)->exists()) {
        return response()->json([
          "valid" => false,
        ]);
      } else {
        return response()->json([
          "valid" => true,
        ]);
      }
    }
    if (LeaveType::where('code', $code)->exists()) {
      return response()->json([
        "valid" => false,
      ]);
    }
    return response()->json([
      "valid" => true,
    ]);
  }

  public function getLeaveTypeAjax($id)
  {
    $leaveType = LeaveType::findOrFail($id);

    if (!$leaveType) {
      return Error::response('Leave type not found');
    }
    $response = [
      'id' => $leaveType->id,
      'name' => $leaveType->name,
      'code' => $leaveType->code,
      'notes' => $leaveType->notes,
      'isProofRequired' => $leaveType->is_proof_required,
      'isShortLeave' => $leaveType->is_short_leave,
      'isPaid' => $leaveType->is_paid,
      'isCarryForward' => $leaveType->is_carry_forward,
      'isSplitEntitlement' => $leaveType->is_split_entitlement,
      'isConsecutiveAllowed' => $leaveType->is_consecutive_allowed,
      'isStrictRules' => $leaveType->is_strict_rules,
      'site_id' => $leaveType->site_id
    ];

    return response()->json($response);
  }

  public function deleteLeaveTypeAjax($id)
  {
    $leaveType = LeaveType::findOrFail($id);
    if (!$leaveType) {
      return Error::response('Leave type not found');
    }

    $leaveType->delete();
    return Success::response('Leave type deleted successfully');
  }

  public function changeStatus($id)
  {
    $leaveType = LeaveType::findOrFail($id);

    if (!$leaveType) {
      return response()->json([
        'code' => 404,
        'message' => 'Leave type not found',
      ]);
    }
    $leaveType->status = $leaveType->status == Status::ACTIVE ? Status::INACTIVE : Status::ACTIVE;
    $leaveType->save();
    return response()->json([
      'code' => 200,
      'message' => 'Leave type status changed successfully',
    ]);
  }
}
