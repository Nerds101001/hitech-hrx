<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class DepartmentsController extends Controller
{
  public function index()
  {
    $users = User::all(['id', 'first_name', 'last_name']);
    return view('tenant.departments.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'users' => $users
    ]);
  }

  public function getListAjax()
  {
    $departments = Department::where('status', Status::ACTIVE)
      ->get(['id', 'name', 'code']);

    return Success::response($departments);
  }


  public function indexAjax(Request $request)
  {
    try {
      $columns = [
        1 => 'id',
        2 => 'name',
        3 => 'code',
        4 => 'parent_id',
        5 => 'notes',

      ];

      $search = [];
      $totalData = Department::count();
      $totalFiltered = $totalData;

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');

      if (empty($request->input('search.value'))) {
        $departments = Department::with(['parentDepartment:id,name', 'managers'])
          ->offset($start)
          ->limit($limit)
          ->orderBy($order, $dir)
          ->get();
      } else {
        $search = $request->input('search.value');
        $departments = Department::with(['parentDepartment:id,name', 'managers'])
          ->where('id', 'like', "%{$search}%")
          ->orWhere('name', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%")
          ->orWhere('notes', 'like', "%{$search}%")
          ->get();

        $totalFiltered = Department::where('id', 'like', "%{$search}%")
          ->orWhere('name', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%")
          ->orWhere('notes', 'like', "%{$search}%")
          ->count();
      }

      $data = [];
      if (!empty($departments)) {
        foreach ($departments as $department) {
          $nestedData['id'] = $department->id;
          $nestedData['name'] = $department->name;
          $nestedData['code'] = $department->code;
          $nestedData['parent_department'] = $department->parentDepartment ? $department->parentDepartment->name : 'No Parent';
          $nestedData['notes'] = $department->notes;
          $nestedData['status'] = $department->status;
          $allottedManagers = $department->managers;
          $designatedManagers = User::whereHas('designation', function($q) use ($department) {
              $q->where('department_id', $department->id)
                ->where('name', 'like', '%Manager%');
          })->get();

          $combined = $allottedManagers->merge($designatedManagers)->unique('id');
          
          $managerLabels = $combined->map(function($m) use ($allottedManagers) {
              $type = $allottedManagers->contains('id', $m->id) ? 'Allotted' : 'Designated';
              return $m->first_name . ' ' . $m->last_name . " ($type)";
          })->toArray();

          $nestedData['managers'] = !empty($managerLabels) ? implode(', ', $managerLabels) : 'No Managers';

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
    } catch (Exception $e) {
      return Error::response($e->getMessage());
    }

  }

  public function getParentDepartments()
  {
    $departments = Department::with('parentDepartment:id,name')->get(['id', 'name', 'parent_id']);
    return response()->json($departments);
  }

  public function addOrUpdateDepartmentAjax(Request $request)
  {
    $departmentId = $request->input('departmentId');
    Log::info('Department update/create request:', $request->all());

    $validatedData = $request->validate([
      'name' => 'required|string|max:255',
      'code' => [
        'required',
        'string',
        'max:10',
        Rule::unique('departments')->ignore($departmentId)
      ],
      'notes' => 'nullable|string|max:225',
      'parent_department' => 'nullable|exists:departments,id',
      'manager_ids' => 'nullable|array',
      'manager_ids.*' => 'exists:users,id'
    ]);

    try {
      if ($departmentId) {
        $department = Department::find($departmentId);
        if (!$department) {
          return Error::response('Department not found', 404);
        }
        $department->name = $validatedData['name'];
        $department->code = $validatedData['code'];
        $department->notes = $validatedData['notes'];
        $department->parent_id = $validatedData['parent_department'];
        $department->save();

        $managerIds = $request->input('manager_ids', []);
        Log::info('Syncing managers for department ' . $departmentId, ['ids' => $managerIds]);
        $department->managers()->sync($managerIds);

        return Success::response('Updated');
      } else {
        // Create a new department
        $department = new Department();
        $department->name = $request->name;
        $department->code = $request->code;
        $department->notes = $request->notes;
        $department->parent_id = $request->parent_department;
        $department->save();

        $managerIds = $request->input('manager_ids', []);
        Log::info('Syncing managers for new department ' . $department->id, ['ids' => $managerIds]);
        $department->managers()->sync($managerIds);

        return Success::response('Created');
      }
    } catch (Exception $e) {
      Log::error('Department save error: ' . $e->getMessage());
      return Error::response($e->getMessage());
    }
  }

  public function getDepartmentAjax($id)
  {
    try {

      $department = Department::find($id);

      if (!$department) {
        return Error::response('Department not found', 404);
      }

      $currentManagerIds = $department->managers->pluck('id')->toArray();
      $availableManagers = User::whereHas('designation', function($q) use ($id) {
          $q->where('department_id', $id);
      })->orWhereIn('id', $currentManagerIds)
      ->get(['id', 'first_name', 'last_name']);

      $response = [
        'id' => $department->id,
        'name' => $department->name,
        'code' => $department->code,
        'notes' => $department->notes,
        'parent_id' => $department->parent_id,
        'status' => $department->status,
        'manager_ids' => array_map('strval', $currentManagerIds),
        'available_managers' => $availableManagers->map(function($user) {
            return [
                'id' => (string)$user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name
            ];
        })
      ];

      return Success::response($response);
    } catch (Exception $e) {
      return Error::response($e->getMessage());
    }
  }

  public function getDepartmentUsersAjax($id = null)
  {
    try {
      if ($id) {
        $department = Department::findOrFail($id);
        // Get users via designations
        $users = User::whereHas('designation', function($q) use ($id) {
            $q->where('department_id', $id);
        })->get(['id', 'first_name', 'last_name']);
      } else {
        // Fallback or for new departments, maybe show all or empty? 
        // User said "show ONLY that department users". For a new one, maybe show all to pick from?
        $users = User::all(['id', 'first_name', 'last_name']);
      }

      return Success::response($users->map(function($user) {
          return [
              'id' => (string)$user->id,
              'first_name' => $user->first_name,
              'last_name' => $user->last_name
          ];
      }));
    } catch (Exception $e) {
      return Error::response($e->getMessage());
    }
  }

  public function deleteAjax($id)
  {
    $department = Department::findOrFail($id);

    $department->delete();

    return Success::response('Department deleted successfully');
  }

  public function changeStatus($id)
  {
    $departments = Department::findOrFail($id);

    if (!$departments) {
      return Error::response('Department not found', 404);
    }
    $departments->status = $departments->status == Status::ACTIVE ? Status::INACTIVE : Status::ACTIVE;
    $departments->save();
    return Success::response('Department status changed successfully');
  }


}
