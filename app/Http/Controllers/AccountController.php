<?php

namespace App\Http\Controllers;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\CommonStatus;
use App\Enums\Status;
use App\Enums\UserAccountStatus;
use App\Models\Asset;
use App\Models\DocumentType;
use App\Models\LeaveType;
use App\Models\SuperAdmin\DomainRequest;
use App\Models\SuperAdmin\Order;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Role;

class AccountController extends Controller
{
  public function resetPasswordRedirect()
  {
    return redirect()->route('password.request');
  }


  public function customerIndex()
  {
    $users = User::where('is_customer', true)->get();

    $userCount = $users->count();

    $verified = User::where('is_customer', true)
      ->whereNotNull('email_verified_at')
      ->get()
      ->count();

    $notVerified = User::where('is_customer', true)
      ->whereNull('email_verified_at')->get()->count();

    $usersUnique = $users->unique(['email']);
    $userDuplicates = $users->diff($usersUnique)->count();

    return view('account.customerIndex', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'totalUser' => $userCount,
      'verified' => $verified,
      'notVerified' => $notVerified,
      'userDuplicates' => $userDuplicates,
    ]);
  }

  public function customerIndexAjax(Request $request)
  {
    try {
      $columns = [
        1 => 'id',
        2 => 'first_name',
        3 => 'email',
        4 => 'email_verified_at',
        5 => 'status',
      ];

      $search = [];

      $totalData = User::where('is_customer', true)->count();

      $totalFiltered = $totalData;

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');


      $baseQuery = User::where('is_customer', true)->with(['activeSubscription.plan']);

      if (empty($request->input('search.value'))) {
        $users = $baseQuery->offset($start)
          ->limit($limit)
          ->orderBy($order, $dir)
          ->get();
      } else {
        $search = $request->input('search.value');

        $users = $baseQuery->where(function($q) use ($search) {
            $q->where('id', 'LIKE', "%{$search}%")
              ->orWhere('first_name', 'LIKE', "%{$search}%")
              ->orWhere('last_name', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%");
          })
          ->offset($start)
          ->limit($limit)
          ->orderBy($order, $dir)
          ->get();

        $totalFiltered = $baseQuery->where(function($q) use ($search) {
            $q->where('id', 'LIKE', "%{$search}%")
              ->orWhere('first_name', 'LIKE', "%{$search}%")
              ->orWhere('last_name', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%");
          })
          ->count();
      }

      $data = [];

      if (!empty($users)) {
        // providing a dummy id instead of database ids
        $ids = $start;

        foreach ($users as $user) {
          $subscription = $user->activeSubscription();
          $nestedData['id'] = $user->id;
          $nestedData['fake_id'] = ++$ids;
          $nestedData['name'] = $user->getFullName();
          $nestedData['email'] = $user->email;
          $nestedData['email_verified_at'] = $user->email_verified_at;
          $nestedData['status'] = $user->status;
          $nestedData['subscription'] = $subscription ? [
            'plan' => $subscription->plan->name,
            'start_date' => $subscription->start_date,
            'end_date' => $subscription->end_date,
            'included_users' => $subscription->plan->included_users,
            'additional_users' => $subscription->additional_users,
          ] : null;

          $data[] = $nestedData;
        }
      }

      return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => intval($totalData),
        'recordsFiltered' => intval($totalFiltered),
        'code' => 200,
        'data' => $data,
      ]);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return Error::response($e->getMessage());
    }
  }

  public function index()
  {
    $users = User::where('is_customer', false)->get();

    $userCount = $users->count();

    $verified = User::where('is_customer', false)
      ->whereNotNull('email_verified_at')
      ->get()
      ->count();

    $notVerified = User::where('is_customer', false)
      ->whereNull('email_verified_at')->get()->count();

    $usersUnique = $users->unique(['email']);
    $userDuplicates = $users->diff($usersUnique)->count();

    return view('account.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'totalUser' => $userCount,
      'verified' => $verified,
      'notVerified' => $notVerified,
      'userDuplicates' => $userDuplicates,
    ]);
  }

  public function viewUser($id)
  {
    $user = User::findOrFail($id);

    $orders = Order::with('plan')
      ->where('user_id', $id)
      ->get();

    $domains = DomainRequest::where('user_id', $id)->get();

    return view('account.user-details', [
      'user' => $user,
      'orders' => $orders,
      'domains' => $domains,
    ]);
  }

  public function activeInactiveUserAjax($id)
  {
    if (env('APP_DEMO')) {
      return Error::response('This feature is disabled in the demo.');
    }

    $user = User::find($id);

    if ($user->status == UserAccountStatus::ACTIVE) {
      $user->status = UserAccountStatus::INACTIVE;
    } else {
      $user->status = UserAccountStatus::ACTIVE;
    }

    $user->save();

    return Success::response('User status changed successfully');
  }

  public function suspendUserAjax($id)
  {
    $user = User::find($id);

    $user->status = UserAccountStatus::RETIRED;

    $user->save();

    return Success::response('User suspended successfully');
  }

  public function deleteUserAjax($id)
  {

    if (env('APP_DEMO')) {
      return Error::response('This feature is disabled in the demo.');
    }

    $user = User::find($id);

    $user->delete();

    return Success::response('User deleted successfully');
  }

  public function myProfile()
  {
    $id = auth()->user()->id;

    $user = User::where('id', $id)
      ->with('userDevice')
      ->with('team')
      ->with('userAvailableLeaves')
      ->with('shift')
      ->with('designation')
      ->with('salesTargets')
      ->with('bankAccount')
      ->with('tasks')
      ->with('payrollAdjustments')
      ->with('documentRequests')
      ->first();

    $documentTypes = DocumentType::where('status', CommonStatus::ACTIVE)->get();

    $leaveTypes = LeaveType::where('status', Status::ACTIVE)
      ->select('id', 'name', 'code')
      ->get();

    $availableAssets = Asset::where('status', 'available')
      ->select('id', 'name', 'asset_code', 'serial_number')
      ->get();

    $status = $user->status;
    $role = $user->getRoleNames()->first() ?? 'Employee';

    // Variables needed by edit modals included in employee-self-profile
    $leavePolicyProfiles = \App\Models\LeavePolicyProfile::all();
    $roles = \Spatie\Permission\Models\Role::all();
    $departments = \App\Models\Department::where('status', \App\Enums\Status::ACTIVE)->get();
    $designations = \App\Models\Designation::where('status', \App\Enums\Status::ACTIVE)->get();
    $allUsers = \App\Models\User::where('status', \App\Enums\UserAccountStatus::ACTIVE)
        ->where('id', '!=', $id)
        ->select('id', 'first_name', 'last_name')
        ->get();

    return view('account.employee-self-profile', compact(
        'user', 'status', 'documentTypes', 'leaveTypes',
        'availableAssets', 'role', 'leavePolicyProfiles',
        'roles', 'departments', 'designations', 'allUsers'
    ));
  }

  public function userListAjax(Request $request)
  {
    try {
      $columns = [
        1 => 'id',
        2 => 'first_name',
        3 => 'email',
        4 => 'email_verified_at',
        5 => 'status',
      ];

      $search = [];

      $totalData = User::where('is_customer', false)->count();

      $totalFiltered = $totalData;

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');


      $baseQuery = User::where('is_customer', false)->with(['roles']);

      if (empty($request->input('search.value'))) {
        $users = $baseQuery->offset($start)
          ->limit($limit)
          ->orderBy($order, $dir)
          ->get();
      } else {
        $search = $request->input('search.value');

        $users = $baseQuery->where(function($q) use ($search) {
            $q->where('id', 'LIKE', "%{$search}%")
              ->orWhere('first_name', 'LIKE', "%{$search}%")
              ->orWhere('last_name', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%");
          })
          ->offset($start)
          ->limit($limit)
          ->orderBy($order, $dir)
          ->get();

        $totalFiltered = $baseQuery->where(function($q) use ($search) {
            $q->where('id', 'LIKE', "%{$search}%")
              ->orWhere('first_name', 'LIKE', "%{$search}%")
              ->orWhere('last_name', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%");
          })
          ->count();
      }

      $data = [];

      if (!empty($users)) {
        // providing a dummy id instead of database ids
        $ids = $start;

        foreach ($users as $user) {
          $nestedData['id'] = $user->id;
          $nestedData['fake_id'] = ++$ids;
          $nestedData['name'] = $user->getFullName();
          $nestedData['email'] = $user->email;
          $nestedData['email_verified_at'] = $user->email_verified_at;
          $nestedData['status'] = $user->status;

          $data[] = $nestedData;
        }
      }

      return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => intval($totalData),
        'recordsFiltered' => intval($totalFiltered),
        'code' => 200,
        'data' => $data,
      ]);
    } catch (Exception $e) {
      return Error::response($e->getMessage());
    }
  }


  public function addOrUpdateUserAjax(Request $request)
  {
    $request->validate([
      'firstName' => 'required',
      'lastName' => 'required',
      'email' => 'required|email|unique:users,email,' . $request->userId,
      'phone' => 'required'
    ]);

    try {
      $userId = $request->userId;

      if ($userId) {
        $user = User::find($userId);

        $user->first_name = $request->firstName;
        $user->last_name = $request->lastName;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->is_customer = false;

        $user->save();

        $user->assignRole('super_admin');

        return response()->json([
          'message' => 'updated',
          'code' => 200,
        ]);

      } else {
        $user = new User();

        $user->first_name = $request->firstName;
        $user->last_name = $request->lastName;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->password = bcrypt('123456');

        $user->save();

        $user->assignRole($request->role);

        return response()->json([
          'message' => 'added',
          'code' => 200,
        ]);
      }
    } catch (Exception $e) {
      return response()->json([
        'message' => $e->getMessage(),
        'code' => 500,
      ]);
    }
  }

  public function editUserAjax($id)
  {

    try {
      $user = User::find($id);

      $response = [
        'id' => $user->id,
        'firstName' => $user->first_name,
        'lastName' => $user->last_name,
        'email' => $user->email,
        'phone' => $user->phone,
        'role' => $user->roles()->exists() ? $user->roles()->first()->name : '',
        'status' => $user->status,
        'gender' => $user->gender
      ];

      return response()->json($response);
    } catch (Exception $e) {
      return response()->json([
        'message' => $e->getMessage(),
        'code' => 500,
      ]);
    }
  }

  public function updateUserAjax(Request $request, $id)
  {

    if (env('APP_DEMO')) {
      return Error::response('This feature is disabled in the demo.');
    }

    $request->validate([
      'first_name' => 'required',
      'last_name' => 'required',
      'email' => 'required|email',
      'role' => 'required',
    ]);

    $user = User::find($id);
    $user->first_name = $request->first_name;
    $user->last_name = $request->last_name;
    $user->email = $request->email;
    $user->status = $request->status;
    $user->save();

    $user->syncRoles([$request->role]);

    return response()->json([
      'message' => 'User updated successfully',
      'code' => 200,
    ]);
  }

  public function changeUserStatusAjax($id)
  {
    $user = User::find($id);

    if ($user->status == 'active') {
      $user->status = 'inactive';
    } else {
      $user->status = 'active';
    }

    $user->save();

    return response()->json([
      'message' => 'User status changed successfully',
      'code' => 200,
    ]);
  }

  public function getRolesAjax()
  {
    $roles = Role::get();

    return Success::response($roles);
  }

  public function getUsersByRoleAjax($role)
  {
    if (!$role) {
      return Error::response('Role is required');
    }

    if ($role == 'all') {
      $users = User::get();
      return Success::response($users);
    }

    $role = Role::find($role);

    $users = $role->users;

    return Success::response($users);
  }

  public function getUsersAjax()
  {
    $users = User::where('id', '!=', auth()->user()->id)->get();

    return Success::response($users);
  }

  public function changePassword(Request $request)
  {

    if (env('APP_DEMO')) {
      return Error::response('This feature is disabled in the demo.');
    }

    $request->validate([
      'oldPassword' => 'required|min:6',
      'newPassword' => 'required|min:6',
    ]);

    if (!(Hash::check($request->oldPassword, auth()->user()->password))) {
      return redirect()->back()->with('error', 'Your current password does not matches with the password you provided. Please try again.');
    }

    $user = User::find(auth()->user()->id);
    $user->password = bcrypt($request->newPassword);
    $user->save();

    return redirect()->back()->with('success', 'Password changed successfully');
  }
}
