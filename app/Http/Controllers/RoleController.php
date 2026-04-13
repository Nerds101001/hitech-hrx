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
    
    // Group permissions by module/category
    $groupedPermissions = [
        'Dashboard & Stats' => $permissions->filter(fn($p) => str_contains(strtolower($p->name), 'dashboard')),
        'Employee Management' => $permissions->filter(fn($p) => str_contains($p->name, 'employees.') || str_contains(strtolower($p->name), 'employee')),
        'Attendance & Visits' => $permissions->filter(fn($p) => str_contains(strtolower($p->name), 'attendance') || str_contains(strtolower($p->name), 'visit')),
        'Leave & Holidays' => $permissions->filter(fn($p) => str_contains(strtolower($p->name), 'leave') || str_contains(strtolower($p->name), 'holiday')),
        'Payroll & Finance' => $permissions->filter(fn($p) => str_contains(strtolower($p->name), 'payroll') || str_contains(strtolower($p->name), 'expense') || str_contains(strtolower($p->name), 'loan') || str_contains(strtolower($p->name), 'adjustment')),
        'Recruitment' => $permissions->filter(fn($p) => str_contains(strtolower($p->name), 'job') || str_contains(strtolower($p->name), 'interview') || str_contains(strtolower($p->name), 'recruitment') || str_contains(strtolower($p->name), 'onboard')),
        'AI & Intelligent Vault' => $permissions->filter(fn($p) => str_contains(strtolower($p->name), 'library') || str_contains(strtolower($p->name), 'bot') || str_contains(strtolower($p->name), 'ai')),
        'Assets & Documents' => $permissions->filter(fn($p) => str_contains(strtolower($p->name), 'asset') || str_contains(strtolower($p->name), 'document')),
        'Organization' => $permissions->filter(fn($p) => str_contains(strtolower($p->name), 'department') || str_contains(strtolower($p->name), 'designation') || str_contains(strtolower($p->name), 'team') || str_contains(strtolower($p->name), 'unit') || str_contains(strtolower($p->name), 'hierarchy')),
        'System & Settings' => $permissions->filter(fn($p) => str_contains(strtolower($p->name), 'settings') || str_contains(strtolower($p->name), 'roles') || str_contains(strtolower($p->name), 'permission') || str_contains(strtolower($p->name), 'audit') || str_contains(strtolower($p->name), 'device') || str_contains(strtolower($p->name), 'geofence') || str_contains(strtolower($p->name), 'ipgroup') || str_contains(strtolower($p->name), 'qrcode') || str_contains(strtolower($p->name), 'sos'))
    ];

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
