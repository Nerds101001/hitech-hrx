<?php

namespace App\Http\Controllers;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use Constants;
use Illuminate\Http\Request;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleController extends Controller
{
  public function index()
  {
    $roles = Role::with(['users', 'permissions'])->get();
    $permissions = Permission::all();
    $users = User::all(); // Fetch all users for the special permissions section
    
    // Read exact business logic groupings from our centralized registry
    $groupedPermissions = config('permissions_registry');

    return view('roles.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'roles' => $roles,
      'permissions' => $permissions,
      'users' => $users,
      'groupedPermissions' => $groupedPermissions
    ]);
  }

  public function addOrUpdateAjax(Request $request)
  {

    if (env('APP_DEMO')) {
      return Error::response('This feature is disabled in demo mode');
    }

    // Validate the request
    $validator = validator($request->all(), [
      'name' => 'required|string|unique:roles,name' . ($request->id ? ',' . $request->id : ''),
    ]);

    if ($validator->fails()) {
      return Error::response($validator->errors()->first());
    }

    // Prepare role data
    $roleData = [
      'name' => $request->name,
      'is_multiple_check_in_enabled' => $request->has('isMultiCheckInEnabled') && $request->isMultiCheckInEnabled == 'on',
      'is_mobile_app_access_enabled' => $request->has('mobileAppAccess') && $request->mobileAppAccess == 'on',
      'is_web_access_enabled' => $request->has('webAppAccess') && $request->webAppAccess == 'on',
      'is_location_activity_tracking_enabled' => $request->has('locationActivityTracking') && $request->locationActivityTracking == 'on',
    ];

    // Check if updating or creating
    if ($request->id) {
      // Update Existing Role
      $role = Role::find($request->id);
      if (!$role) {
        return Error::response('Role not found');
      }
      $role->update($roleData);
      
      // Sync Permissions (Filter valid names only to avoid crash)
      $requestedPermissions = $request->permissions ?? [];
      $existingPermissions = Permission::whereIn('name', $requestedPermissions)->pluck('name')->toArray();
      $role->syncPermissions($existingPermissions);
      
      return Success::response('Role updated successfully');
    } else {
      // Create New Role
      $role = Role::create($roleData);
      
      // Sync Permissions (Filter valid names only to avoid crash)
      $requestedPermissions = $request->permissions ?? [];
      $existingPermissions = Permission::whereIn('name', $requestedPermissions)->pluck('name')->toArray();
      $role->syncPermissions($existingPermissions);
      
      return Success::response('Role created successfully');
    }
  }

  public function getUserPermissionsAjax($userId)
  {
    $user = User::with('permissions')->find($userId);
    if (!$user) {
        return Error::response('User not found');
    }
    return Success::response('User permissions fetched', $user->permissions);
  }

  public function syncUserPermissionsAjax(Request $request)
  {
    $userId = $request->user_id;
    $user = User::find($userId);
    if (!$user) {
        return Error::response('User not found');
    }

    if ($request->has('permissions')) {
        $requestedPermissions = $request->permissions;
        $existingPermissions = Permission::whereIn('name', $requestedPermissions)->pluck('name')->toArray();
        $user->syncPermissions($existingPermissions);
    } else {
        $user->syncPermissions([]); // Revoke all direct permissions
    }

    return Success::response('User-specific permissions updated successfully');
  }

  public function deleteAjax($id)
  {

    if (env('APP_DEMO')) {
      return Error::response('This feature is disabled in demo mode');
    }

    $role = Role::find($id);

    if (!$role) {
      return Error::response('Role not found');
    }

    if ($role->users->count() > 0) {
      return Error::response('Role has users assigned to it');
    }

    if (in_array($role->name, Constants::BuiltInRoles)) {
      return Error::response('Built-in roles cannot be deleted');
    }

    $role->delete();

    return Success::response('Role deleted successfully');
  }
}
