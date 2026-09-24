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

    // Persona templates for one-click role setup (see config/role_presets.php)
    $rolePresets = config('role_presets', []);

    // "Does checking this box actually do anything?" - see PermissionWiringAudit.
    $wiredPermissions = \App\Support\PermissionWiringAudit::wiredMap();

    return view('roles.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'roles' => $roles,
      'permissions' => $permissions,
      'users' => $users,
      'groupedPermissions' => $groupedPermissions,
      'rolePresets' => $rolePresets,
      'wiredPermissions' => $wiredPermissions,
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

    // SECURITY (H-1): validate the requested permission set BEFORE any write, so
    // a non-admin cannot attach an admin-tier permission to a role (which they
    // could then have assigned to themselves).
    $requestedPermissions = $request->permissions ?? [];
    $existingPermissions = Permission::whereIn('name', $requestedPermissions)->pluck('name')->toArray();
    if ($guard = $this->guardPrivilegeEscalation($existingPermissions)) {
      return $guard;
    }

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

  /**
   * "What does this actually give someone?" - the other half of the wiring
   * badge. That says a permission does SOMETHING; this shows exactly what,
   * for a real person or for a role in the abstract, using the same
   * Nav::allows()-based check the sidebar itself is built on.
   *
   * A role has no menu of its own to read - only a real user does, since
   * roles/permission/department gates are evaluated against one specific
   * person. Previewing "Manager" in the abstract means asking what a person
   * who holds ONLY that role, and nothing else, would see - which needs a
   * real (if temporary) row, because Spatie's hasRole()/can() query real
   * pivot tables and cannot be faked on an unsaved model. Opened in a
   * transaction that is always rolled back before the response returns, so
   * nothing is left behind - the same pattern used to verify every fix in
   * this pass without touching real data.
   */
  public function previewAccessAjax(Request $request)
  {
    $request->validate([
      'user_id' => 'nullable|integer|exists:users,id',
      'role'    => 'nullable|string|exists:roles,name',
    ]);

    if (!$request->filled('user_id') && !$request->filled('role')) {
      return Error::response('Pick a person or a role to preview.');
    }

    if ($request->filled('user_id')) {
      $user = User::with('department', 'roles')->find($request->user_id);
      $label = $user->getFullName() . ' (' . implode(', ', $user->roles->pluck('name')->toArray() ?: ['no role']) . ')';
      $tree = \App\Support\Nav::previewMenuFor($user);
      return Success::response(['label' => $label, 'menu' => $tree]);
    }

    \Illuminate\Support\Facades\DB::beginTransaction();
    try {
      $role = $request->role;
      $temp = User::create([
        'first_name' => 'Preview', 'last_name' => ucfirst($role),
        'email' => 'preview-' . uniqid() . '@example.invalid',
        'code' => 'PREVIEW-' . uniqid(),
        'password' => bcrypt(bin2hex(random_bytes(8))),
        'status' => 'active',
      ]);
      $temp->assignRole($role);
      $temp->load('department', 'roles');

      $tree = \App\Support\Nav::previewMenuFor($temp);
      $label = 'Anyone with only the "' . $role . '" role';

      return Success::response(['label' => $label, 'menu' => $tree]);
    } finally {
      \Illuminate\Support\Facades\DB::rollBack();
    }
  }

  public function getUserPermissionsAjax($userId)
  {
    $user = User::with('permissions')->find($userId);
    if (!$user) {
        return Error::response('User not found');
    }
    return Success::response($user->permissions);
  }

  /**
   * SECURITY (H-1): a non-admin actor may not assign an admin-tier permission
   * (one that grants admin.guard passage) to anyone, nor edit their OWN
   * permission set. This preserves legitimate roles.manage delegation for
   * ordinary permissions while blocking self-escalation to admin. Admins are
   * unrestricted. Returns an Error response to abort, or null to proceed.
   */
  private function guardPrivilegeEscalation(array $requestedPermissionNames, $targetUserId = null): ?\Illuminate\Http\JsonResponse
  {
    $actor = auth()->user();
    if ($actor && $actor->hasRole('admin')) {
      return null;
    }
    if ($targetUserId !== null && $actor && (int) $actor->id === (int) $targetUserId) {
      return Error::response('You cannot modify your own permissions.');
    }
    $elevated = array_values(array_intersect($requestedPermissionNames, \App\Http\Middleware\AdminRoleGuard::ELEVATED_PERMISSIONS));
    if (!empty($elevated)) {
      return Error::response('You are not allowed to assign administrative permissions: ' . implode(', ', $elevated));
    }
    return null;
  }

  public function syncUserPermissionsAjax(Request $request)
  {
    $userId = $request->user_id;
    $user = User::find($userId);
    if (!$user) {
        return Error::response('User not found');
    }

    $requestedPermissions = $request->has('permissions') ? ($request->permissions ?? []) : [];
    $existingPermissions = Permission::whereIn('name', $requestedPermissions)->pluck('name')->toArray();

    // SECURITY (H-1): block self-escalation / assigning admin-tier permissions.
    if ($guard = $this->guardPrivilegeEscalation($existingPermissions, $userId)) {
        return $guard;
    }

    $user->syncPermissions($existingPermissions);

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
