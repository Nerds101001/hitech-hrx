<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\ExpenseRule;
use App\Models\ExpenseType;
use App\Models\Location;
use App\Models\Settings;
use Illuminate\Http\Request;

class ExpenseTypeController extends Controller
{
  public function index()
  {
    $total = ExpenseType::count();
    $active = ExpenseType::where('status', 'active')->count();
    $inactive = ExpenseType::where('status', 'inactive')->count();

    return view('tenant.expenseTypes.index', [
      'stats' => [
        'total' => $total,
        'active' => $active,
        'inactive' => $inactive,
      ],
      'pageConfigs' => ['contentLayout' => 'wide']
    ]);
  }

  public function getExpenseTypesListAjax(Request $request)
  {
    try {
      $columns = [
        1 => 'id',
        2 => 'name',
        3 => 'notes',
        4 => 'code',
        5 => 'status',
      ];

      $search = [];

      $totalData = ExpenseType::count();

      $totalFiltered = $totalData;

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');

      if (empty($request->input('search.value'))) {
        $expenseTypes = ExpenseType::offset($start)
          ->limit($limit)
          ->orderBy($order, $dir)
          ->get();
      } else {
        $search = $request->input('search.value');
        $expenseTypes = ExpenseType::where('id', 'like', "%{$search}%")
          ->orWhere('name', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%")
          ->orWhere('notes', 'like', "%{$search}%")
          ->get();

        $totalFiltered = ExpenseType::where('id', 'like', "%{$search}%")
          ->orWhere('name', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%")
          ->orWhere('notes', 'like', "%{$search}%")
          ->count();
      }

      $data = [];
      if (!empty($expenseTypes)) {
        foreach ($expenseTypes as $expenseType) {
          $nestedData['id'] = $expenseType->id;
          $nestedData['name'] = $expenseType->name;
          $nestedData['code'] = $expenseType->code;
          $nestedData['notes'] = $expenseType->notes;
          $nestedData['status'] = $expenseType->status;
          $nestedData['action'] = '<div class="d-flex align-items-center justify-content-center gap-2">' .
            '<a href="javascript:;" class="text-hitech edit-record me-2" data-id="' . $expenseType->id . '" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateExpenseType" title="Edit"><i class="bx bx-edit fs-4"></i></a>' .
            '<a href="javascript:;" class="text-danger delete-record" data-id="' . $expenseType->id . '" title="Delete"><i class="bx bx-trash fs-4"></i></a>' .
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




  public function addOrUpdateExpenseTypeAjax(Request $request)
  {
    $expenseTypeId = $request->id;
    $request->validate([
      'name' => 'required',
      'code' => ['required', 'unique:expense_types,code,' . $expenseTypeId],
      'notes' => 'nullable',
      'isProofRequired' => 'nullable',

    ]);


    if ($expenseTypeId) {
      $expenseType = ExpenseType::find($expenseTypeId);
      $expenseType->name = $request->name;
      $expenseType->notes = $request->notes;
      $expenseType->code = $request->code;
      $expenseType->is_proof_required = $request->isProofRequired;
      $expenseType->save();

      return response()->json([
        'code' => 200,
        'message' => 'Updated',
      ]);
    } else {

      $expenseType = new ExpenseType();
      $expenseType->name = $request->name;
      $expenseType->notes = $request->notes;
      $expenseType->code = $request->code;
      $expenseType->is_proof_required = $request->isProofRequired;

      $expenseType->save();

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
      if (ExpenseType::where('code', $code)->where('id', '!=', $id)->exists()) {
        return response()->json([
          "valid" => false,
        ]);
      } else {
        return response()->json([
          "valid" => true,
        ]);
      }
    }
    if (ExpenseType::where('code', $code)->exists()) {
      return response()->json([
        "valid" => false,
      ]);
    }
    return response()->json([
      "valid" => true,
    ]);
  }

  public function getExpenseTypeAjax($id)
  {
    $expenseType = ExpenseType::findOrFail($id);

    if (!$expenseType) {
      return Error::response('Expense type not found');
    }
    $response = [
      'id' => $expenseType->id,
      'name' => $expenseType->name,
      'code' => $expenseType->code,
      'notes' => $expenseType->notes,
      'isProofRequired' => $expenseType->is_proof_required
    ];

    return response()->json($response);
  }

  public function deleteExpenseTypeAjax($id)
  {
    $expenseType = ExpenseType::findOrFail($id);
    if (!$expenseType) {
      return Error::response('Expense type not found');
    }

    $expenseType->delete();
    return Success::response('Expense type deleted successfully');
  }

  public function changeStatus($id)
  {
    $expenseType = ExpenseType::findOrFail($id);

    if (!$expenseType) {
      return response()->json([
        'code' => 404,
        'message' => 'Expense type not found',
      ]);
    }
    $expenseType->status = $expenseType->status == Status::ACTIVE ? Status::INACTIVE : Status::ACTIVE;
    $expenseType->save();
    return response()->json([
      'code' => 200,
      'message' => 'Expense type status changed successfully',
    ]);
  }

  public function view($id)
  {
    $expenseType = ExpenseType::findOrFail($id);

    if (!$expenseType) {
      return Error::response('Expense type not found');
    }

    $expenseRules = ExpenseRule::where('expense_type_id', $id)->get();

    $designations = Designation::where('status', Status::ACTIVE)->get();

    $locations = Location::where('status', Status::ACTIVE)->get();

    return view('tenant.expenseTypes.view', [
      'expenseType' => $expenseType,
      'expenseRules' => $expenseRules,
      'designations' => $designations,
      'locations' => $locations
    ]);
  }

  public function addOrUpdateRule(Request $request)
  {
    $request->validate([
      'expenseTypeId' => 'required',
      'designationId' => 'required',
      'locationId' => 'required',
      'amount' => 'required',
    ]);

    $expenseRuleId = $request->id;

    if ($expenseRuleId) {
      $expenseRule = ExpenseRule::find($expenseRuleId);
      $expenseRule->expense_type_id = $request->expenseTypeId;
      $expenseRule->designation_id = $request->designationId;
      $expenseRule->location_id = $request->locationId;
      $expenseRule->amount = $request->amount;
      $expenseRule->save();

      return redirect()->back()->with('success', 'Updated');
    } else {

      $isDuplicate = ExpenseRule::where('expense_type_id', $request->expenseTypeId)
        ->where('designation_id', $request->designationId)
        ->where('location_id', $request->locationId)
        ->exists();

      if ($isDuplicate) {
        return redirect()->back()->with('error', 'Rule already exists');
      }

      $expenseRule = new ExpenseRule();
      $expenseRule->expense_type_id = $request->expenseTypeId;
      $expenseRule->designation_id = $request->designationId;
      $expenseRule->location_id = $request->locationId;
      $expenseRule->amount = $request->amount;
      $expenseRule->save();

      return redirect()->back()->with('success', 'Added');
    }
  }

  public function deleteRule($id)
  {
    $expenseRule = ExpenseRule::findOrFail($id);
    if (!$expenseRule) {
      return Error::response('Rule not found');
    }

    $expenseRule->delete();
    return Success::response('Rule deleted successfully');
  }
}
