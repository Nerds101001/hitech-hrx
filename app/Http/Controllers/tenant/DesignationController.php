<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Models\Designation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class DesignationController extends Controller
{

  public function getDesignationListAjax()
  {
    $designations = Designation::where('status', Status::ACTIVE)
      ->get(['id', 'name', 'code']);

    return Success::response($designations);
  }

  public function index()
  {
    return view('tenant.designation.index', [
      'pageConfigs' => ['contentLayout' => 'wide']
    ]);
  }

  public function indexAjax(Request $request)
  {
    try {
      // Build the Eloquent query, joining departments to retrieve the department name.
      $query = Designation::query()
        ->select('designations.*', 'departments.name as department_name')
        ->leftJoin('departments as departments', 'designations.department_id', '=', 'departments.id');

      // Using DataTables::eloquent ensures we work with the Eloquent builder.
      return DataTables::eloquent($query)
        // You can add an auto-index column if needed.
        ->addIndexColumn()
        // Optionally, re-map the name if needed (here we simply return the value).
        ->editColumn('name', function ($designation) {
          return $designation->name;
        })
        // Map the department name from the join.
        ->editColumn('department_name', function ($designation) {
          return $designation->department_name ?? 'N/A';
        })
        // Format the is_approver field to return 'Yes' or 'No'
        ->addColumn('is_approver_text', function ($designation) {
          return $designation->is_leave_approver ? 'Yes' : 'No';
        })
        // Build the action buttons column with your custom HTML.
        ->addColumn('action', function ($designation) {
          $editBtn = '<a href="javascript:;" class="text-hitech edit-record me-2" data-id="' . $designation->id . '" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateDesignation" title="Edit"><i class="bx bx-edit fs-4"></i></a>';
          $deleteBtn = '<a href="javascript:;" class="text-danger delete-record" data-id="' . $designation->id . '" title="Delete"><i class="bx bx-trash fs-4"></i></a>';
          return '<div class="d-flex align-items-center justify-content-center gap-2">' . $editBtn . $deleteBtn . '</div>';
        })

        ->filterColumn('department_name', function ($query, $keyword) {
          $query->whereHas('department', function ($query) use ($keyword) {
            $query->where('name', 'like', '%' . $keyword . '%');
          });
        })
        // Specify which columns contain raw HTML.
        ->rawColumns(['action', 'status'])
        ->make(true);

    } catch (Exception $e) {
      Log::error($e->getMessage());
      return Error::response('Something went wrong');
    }
  }


  public function addOrUpdateAjax(Request $request)
  {
    $designationId = $request->id;
    $request->validate([
      'name' => 'required',
      'code' => ['required', Rule::unique('designations')->ignore($designationId)],
      'department_id' => 'nullable|exists:departments,id',
      'notes' => 'nullable',
    ]);

    try {
      if ($designationId) {
        $designation = Designation::findOrFail($designationId);
        if (!$designation) {
          return Error::response('Designation not found', 404);
        }
        $designation->name = $request->name;
        $designation->code = $request->code;
        $designation->notes = $request->notes;
        $designation->department_id = $request->department_id;
        $designation->is_leave_approver =  $designation->is_expense_approver = $request->has('is_approver');
        $designation->save();
        return Success::response('Updated');
      } else {
        $designation = new Designation();
        $designation->name = $request->name;
        $designation->code = $request->code;
        $designation->notes = $request->notes;
        $designation->department_id = $request->department_id;
        $designation->is_leave_approver =  $designation->is_expense_approver = $request->has('is_approver');
        $designation->save();
        return Success::response('Added');
      }
    } catch (Exception $e) {
      return Error::response($e->getMessage());
    }
  }

  public function getByIdAjax($id)
  {

    if (!$designation = Designation::findOrFail($id)) {
      return Error::response('Designation not found', 404);
    }

    $response = [
      'id' => $designation->id,
      'name' => $designation->name,
      'code' => $designation->code,
      'notes' => $designation->notes,
      'department_id' => $designation->department_id,
      'status' => $designation->status,
      'is_approver' => $designation->is_leave_approver,
    ];

    return response()->json($response);
  }


  public function deleteAjax($id)
  {
    $designation = Designation::findOrFail($id);
    if (!$designation) {
      return Error::response('Designation not found');
    }
    $designation->delete();
    return Success::response('Designation deleted successfully');
  }

  public function changeStatus($id)
  {
    $designation = Designation::findOrFail($id);
    if (!$designation) {
      return Error::response('Designation not found', 404);
    }
    $designation->status = $designation->status == 'active' ? 'inactive' : 'active';
    $designation->save();
    return Success::response('Designation status changed successfully');
  }

  public function checkCodeValidationAjax(Request $request)
  {
    $code = $request->code;


    if (!$code) {
      return response()->json(["valid" => false]);
    }

    if ($request->has('id')) {
      $id = $request->input('id');
      if (Designation::where('code', $code)->where('id', '!=', $id)->exists()) {
        return response()->json([
          "valid" => false,
        ]);
      } else {
        return response()->json([
          "valid" => true,
        ]);
      }
    }
    if (Designation::where('code', $code)->exists()) {
      return response()->json([
        "valid" => false,
      ]);
    }
    return response()->json([
      "valid" => true,
    ]);
  }
}
