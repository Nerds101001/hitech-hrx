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
  /**
   * Statuses that mean the person no longer uses the app: left (the
   * Employees page's RELIEVED/TERMINATED/RETIRED set) or switched off.
   * Kept out of every list and user count on the permission pages.
   */
  private const NOT_CURRENT_STATUSES = [
    'relieved', 'terminated', 'retired', 'inactive', 'suspended',
    'blocked', 'rejected', 'deleted', 'probation_failed',
  ];

  /** Query constraint: current employees only. */
  private static function currentOnly($query)
  {
    return $query->where(fn($q) => $q->whereNull('users.status')->orWhereNotIn('users.status', self::NOT_CURRENT_STATUSES));
  }

  /** Permission-registry module => the sidebar section its pages sit under. */
  private const MODULE_SECTION = [
    'Dashboard' => 'Dashboard',
    'Announcements' => 'Announcements',
    'Employees' => 'People',
    'Hierarchy & Probation' => 'People',
    'Attendance' => 'Time & Attendance',
    'Leaves' => 'Time & Attendance',
    'Configuration Masters' => 'Time & Attendance',
    'Travel & Expense' => 'Travel & Expense',
    'Payroll' => 'Payroll',
    'Loans & Advances' => 'Payroll',
    'Recruitment & Onboarding' => 'Recruitment & Onboarding',
    'Job Postings' => 'Recruitment & Onboarding',
    'Job Applications' => 'Recruitment & Onboarding',
    'Job Categories & Stages' => 'Recruitment & Onboarding',
    'Interview & Screening' => 'Recruitment & Onboarding',
    'Training' => 'Recruitment & Onboarding',
    'Sales Field Ops' => 'Sales Field Ops',
    'CCare Attendance' => 'Sales Field Ops',
    'Kingo Bingo' => 'Sales Field Ops',
    'Pricing Desk' => 'Sales Field Ops',
    'Telephony' => 'Telephony',
    'Intelligent Vault' => 'Intelligent Vault',
    'Asset Management' => 'Asset Management',
    'Monitoring' => 'Monitoring',
    'Performance' => 'Performance',
    'Reports' => 'Reports',
    'Settings & Administration' => 'System Settings',
  ];

  /**
   * One page: a role list on the left, the selected role's full permission
   * panel on the right - switching roles re-fetches the panel via AJAX
   * (panelAjax) instead of navigating away, so nothing is ever lost or
   * reloaded just to look at a different role.
   */
  public function index(Request $request)
  {
    $current = fn($q) => self::currentOnly($q);
    $roles = Role::withCount(['users' => $current])->with(['permissions', 'users' => $current])->orderBy('name')->get();
    $users = self::currentOnly(User::query())->get(); // special-permissions summary + preview access picker

    $selectedRole = $request->filled('role')
      ? $roles->firstWhere('id', (int) $request->query('role'))
      : null;
    $selectedRole = $selectedRole ?: $roles->first();

    return view('roles.index', array_merge([
      'pageConfigs' => ['contentLayout' => 'wide'],
      'roles' => $roles,
      'users' => $users,
      'selectedRole' => $selectedRole,
    ], $selectedRole ? $this->rolePanelData($selectedRole) : []));
  }

  /**
   * Returns just the rendered right-hand panel for one role - what the left
   * role list AJAX-swaps in when you click a different role, so the matrix
   * table is written and maintained in exactly one place (this Blade
   * partial), not duplicated between an initial server render and a
   * client-side JS template.
   */
  public function panelAjax($id)
  {
    $current = fn($q) => self::currentOnly($q);
    $role = Role::withCount(['users' => $current])->with(['permissions', 'users' => $current])->find($id);
    if (!$role) {
      return Error::response('Role not found');
    }

    return response()->view('roles._permission-panel', array_merge([
      'selectedRole' => $role,
      'roles' => Role::orderBy('name')->get(['id', 'name']), // for the "Copy From Role" picker
    ], $this->rolePanelData($role)));
  }

  /**
   * The permission names belonging to one role, for "Copy From Role" - the
   * new (unsaved) role's checkboxes get ticked to match, nothing is written
   * until the user hits Save on the role they're actually editing.
   */
  public function getRolePermissionsAjax($id)
  {
    $role = Role::with('permissions')->find($id);
    if (!$role) {
      return Error::response('Role not found');
    }
    return Success::response($role->permissions->pluck('name')->merge($this->lockedPermissionsFor($role))->unique()->values());
  }

  private function rolePanelData(Role $role): array
  {
    $groupedPermissions = config('permissions_registry');
    $lockedPermissionNames = $this->lockedPermissionsFor($role);

    return [
      'role' => $role,
      'rolePermissionNames' => array_values(array_unique(array_merge($role->permissions->pluck('name')->toArray(), $lockedPermissionNames))),
      'lockedPermissionNames' => $lockedPermissionNames,
      'isSuperRole' => $this->isSuperRole($role),
      'permissionMatrix' => $this->buildPermissionMatrix($groupedPermissions),
      'totalPermissionsCount' => array_sum(array_map('count', $groupedPermissions)),
      'wiredPermissions' => \App\Support\PermissionWiringAudit::wiredMap(),
      'rolePresets' => config('role_presets', []),
      'reachability' => $reach = $this->computeRoleReachability($role),
      // Page access per module row: which sidebar section it lives under
      // and whether that section opens for this role.
      'moduleSection' => self::MODULE_SECTION,
      'openSections' => collect($reach['tree'])->where('visible', true)->pluck('name')->flip()->all(),
    ];
  }

  /**
   * Roles Gate::before (AppServiceProvider) passes on every ability - there
   * is no permission it could be missing, so its matrix is all locked-on.
   */
  private function isSuperRole(Role $role): bool
  {
    return in_array($role->name, ['admin', 'Admin', 'super_admin'], true);
  }

  /**
   * Permissions this role holds by NAME in code, whatever its stored rows
   * say - shown ticked and locked, and never stripped on save, since
   * unticking them wouldn't take the access away. See
   * config/role_builtin_permissions.php for how that list is derived.
   */
  private function lockedPermissionsFor(Role $role): array
  {
    if ($this->isSuperRole($role)) {
      return collect(config('permissions_registry'))->flatMap(fn($perms) => array_keys($perms))->values()->all();
    }
    return config('role_builtin_permissions.' . $role->name, []);
  }

  /**
   * What this role can ACTUALLY reach, computed live from the real route
   * middleware and menu rules (App\Support\Nav) - the answer this page
   * leads with now, because it's automatic and can't drift from reality
   * the way a manually-ticked checkbox could. Same temp-user-in-a-rolled-
   * back-transaction technique as previewAccessAjax's role branch:
   * Spatie's hasRole()/can() need a real persisted row, and this is a
   * role viewed in the abstract, not a specific person.
   */
  private function computeRoleReachability(Role $role): array
  {
    \Illuminate\Support\Facades\DB::beginTransaction();
    try {
      $temp = User::create([
        'first_name' => 'Preview', 'last_name' => ucfirst($role->name),
        'email' => 'preview-' . uniqid() . '@example.invalid',
        'code' => 'PREVIEW-' . uniqid(),
        'password' => bcrypt(bin2hex(random_bytes(8))),
        'status' => 'active',
      ]);
      $temp->assignRole(\App\Support\RoleAssignment::rolesFor($role->name));
      $temp->load('department', 'roles');

      $tree = \App\Support\Nav::previewMenuFor($temp);
      $visibleCount = collect($tree)->where('visible', true)->count();

      return ['tree' => $tree, 'visibleCount' => $visibleCount, 'totalCount' => count($tree)];
    } finally {
      \Illuminate\Support\Facades\DB::rollBack();
    }
  }

  /**
   * Reshapes the flat permissions_registry.php into a CRUD-style matrix per
   * module: a permission slots into the View/Create/Edit/Delete column only
   * when its key is an EXACT "<module>.<verb>" match (e.g. "assets.delete"),
   * never a substring match - "employee.view_banking" must never collide
   * with the generic "View" column, since granting one is not granting the
   * other.
   *
   * A verb only becomes its own column when exactly ONE permission in that
   * module claims it. Some modules have several distinct "view"-type keys
   * (e.g. "Hierarchy & Probation" has both hierarchy.view and probation.view;
   * "Sales Field Ops" has five separate *.view permissions) - a single
   * checkbox can't represent more than one of those without silently
   * dropping the rest, so when a verb has more than one claimant, all of
   * them fall through to "Other Permissions" instead, which has no such
   * limit. Every key from the input ends up in exactly one place, by
   * construction - this can never lose a permission.
   */
  private function buildPermissionMatrix(array $groupedPermissions): array
  {
    $verbs = ['view', 'create', 'edit', 'delete'];
    $matrix = [];

    foreach ($groupedPermissions as $moduleName => $permsArray) {
      $claimants = array_fill_keys($verbs, []);
      foreach ($permsArray as $permName => $label) {
        foreach ($verbs as $verb) {
          if (\Illuminate\Support\Str::endsWith($permName, '.' . $verb)) {
            $claimants[$verb][] = $permName;
            break;
          }
        }
      }

      // Map permission name -> the verb it exclusively owns, for verbs with
      // exactly one claimant in this module.
      $soleOwner = [];
      foreach ($verbs as $verb) {
        if (count($claimants[$verb]) === 1) {
          $soleOwner[$claimants[$verb][0]] = $verb;
        }
      }

      $crud = array_fill_keys($verbs, null);
      $other = [];
      foreach ($permsArray as $permName => $label) {
        if (isset($soleOwner[$permName])) {
          $crud[$soleOwner[$permName]] = ['name' => $permName, 'label' => $label];
        } else {
          $other[$permName] = $label;
        }
      }

      $matrix[$moduleName] = [
        'slug' => \Illuminate\Support\Str::slug($moduleName),
        'crud' => $crud,
        'other' => $other,
      ];
    }

    return $matrix;
  }

  /**
   * "User Special Permissions" — the dedicated page. The employee list and
   * search live here; the reconciliation detail for whichever person is
   * selected is fetched on demand via specialPermissionsDataAjax, the same
   * master-detail pattern as the role editor's left rail.
   */
  public function specialPermissionsPage()
  {
    // Only people who can actually use access - see NOT_CURRENT_STATUSES.
    $employees = self::currentOnly(User::with('roles')->withCount('permissions'))
      ->orderBy('first_name')->get();
    $groupedPermissions = config('permissions_registry');

    return view('roles.special-permissions', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'employees' => $employees,
      'roles' => Role::all(),
      'users' => $employees,
      'groupedPermissions' => $groupedPermissions,
      'permissionMatrix' => $this->buildPermissionMatrix($groupedPermissions),
      'wiredPermissions' => \App\Support\PermissionWiringAudit::wiredMap(),
    ]);
  }

  /**
   * For one employee: exactly what their ROLE grants, what they hold as a
   * direct/"special" override, and the Final Access that actually results -
   * which under Spatie is simply role-granted OR direct-granted (a user's
   * `permissions` relation is direct-only; `getPermissionsViaRoles()` is
   * role-only; there is no "deny" concept in either, so special permissions
   * are additive-only here, same as the underlying library). Reuses the same
   * CRUD matrix the role editor is built on, so a module reads identically
   * in both places.
   */
  public function specialPermissionsDataAjax($userId)
  {
    $user = User::with(['roles', 'permissions', 'designation'])->find($userId);
    if (!$user) {
      return Error::response('Employee not found');
    }

    $matrix = $this->buildPermissionMatrix(config('permissions_registry'));
    $reconciled = $this->reconcilePermissionsForUser($user, $matrix);
    $role = $user->roles->first();

    // Who added each current special permission, and when (latest grant).
    $grantLog = \Illuminate\Support\Facades\DB::table('permission_grant_logs as l')
      ->leftJoin('users as a', 'a.id', '=', 'l.actor_id')
      ->where('l.user_id', $user->id)->where('l.action', 'granted')
      ->orderBy('l.id')
      ->get(['l.permission', 'l.created_at', 'a.first_name', 'a.last_name'])
      ->keyBy('permission')
      ->map(fn($r) => [
        'by' => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: 'Unknown',
        'at' => \Illuminate\Support\Carbon::parse($r->created_at)->format('d M Y'),
      ]);
    $lastChange = \Illuminate\Support\Facades\DB::table('permission_grant_logs as l')
      ->leftJoin('users as a', 'a.id', '=', 'l.actor_id')
      ->where('l.user_id', $user->id)->orderByDesc('l.id')
      ->first(['l.action', 'l.permission', 'l.created_at', 'a.first_name', 'a.last_name']);

    // Page access per module: which sidebar section a module's pages live
    // under, and whether it opens for this person. A module with 0 action
    // permissions can still open (Dashboard, My Tasks... are open to every
    // signed-in person), and a bare "No" there read as "can't open it".
    $reach = \App\Support\Nav::previewMenuFor($user);
    $sectionOpen = collect($reach)->mapWithKeys(fn($i) => [$i['name'] => (bool) $i['visible']]);
    foreach ($reconciled['modules'] as &$mod) {
      $section = self::MODULE_SECTION[$mod['name']] ?? null;
      $mod['section'] = $section;
      $mod['sectionOpen'] = $section !== null && ($sectionOpen[$section] ?? false);
    }
    unset($mod);
    $wired = \App\Support\PermissionWiringAudit::wiredMap();

    return Success::response([
      'unwired' => array_keys(array_filter($wired, fn($w) => !$w)),
      'user' => [
        'id' => $user->id,
        'name' => $user->getFullName(),
        'email' => $user->email,
        'phone' => $user->phone,
        'initials' => $user->getInitials(),
        'avatar' => $user->profile_picture ? $user->getProfilePicture() : null,
        'statusLabel' => $user->status ? ucfirst($user->status->value) : 'Unknown',
        'designation' => optional($user->designation)->name,
        'profileUrl' => route('employees.show', $user->id),
      ],
      'role' => $role ? [
        'name' => $role->display_name,
        'description' => $role->description,
        'url' => route('roles.index', ['role' => $role->id]),
        'multiCheckIn' => (bool) $role->is_multiple_check_in_enabled,
        'mobileApp' => (bool) $role->is_mobile_app_access_enabled,
        'webLogin' => (bool) $role->is_web_access_enabled,
        'gpsTracking' => (bool) $role->is_location_activity_tracking_enabled,
      ] : null,
      'specialCount' => $user->permissions->count(),
      'summary' => $reconciled['summary'],
      'modules' => $reconciled['modules'],
      'isSuper' => $user->roles->contains(fn($r) => $this->isSuperRole($r)),
      'isSelf' => (int) auth()->id() === (int) $user->id,
      'guardWarning' => $this->adminGuardWarning($user),
      'reach' => $reach,
      'grantLog' => $grantLog,
      'lastChange' => $lastChange ? [
        'text' => ucfirst($lastChange->action) . ' "' . $lastChange->permission . '"',
        'by' => trim(($lastChange->first_name ?? '') . ' ' . ($lastChange->last_name ?? '')) ?: 'Unknown',
        'at' => \Illuminate\Support\Carbon::parse($lastChange->created_at)->format('d M Y, h:i A'),
      ] : null,
    ]);
  }

  /**
   * admin.guard turns away employee/office_employee - and anyone without an
   * admin-type role - from the whole admin area unless they hold one of a
   * few ELEVATED permissions. A special permission for such a person saves
   * fine and then opens nothing, so say so on the page instead of letting
   * it look like it worked.
   */
  private function adminGuardWarning(User $user): ?string
  {
    $guard = \App\Http\Middleware\AdminRoleGuard::class;
    $hasElevated = $user->getAllPermissions()->pluck('name')->intersect($guard::ELEVATED_PERMISSIONS)->isNotEmpty();
    if ($hasElevated) {
      return null;
    }
    $roleLabel = $user->roles->pluck('name')->implode(', ') ?: 'no role';
    if ($user->hasRole(['employee', 'office_employee']) || !$user->hasAnyRole($guard::ADMIN_ROLES)) {
      return "This person's role ({$roleLabel}) keeps them out of the admin area, so most special permissions here won't open anything for them - only these let them past: "
        . implode(', ', $guard::ELEVATED_PERMISSIONS) . '. Their own self-service pages are unaffected.';
    }
    return null;
  }

  /**
   * Per module: every applicable permission tagged with whether it comes
   * from the role, a direct/special grant, both, or neither, plus a rollup
   * state (full / partial / view_only / none) for the role, special, and
   * final columns respectively - the exact shape the reconciliation table
   * renders one row per module from.
   */
  private function reconcilePermissionsForUser(User $user, array $matrix): array
  {
    // What the role gives: its stored rows PLUS what it has by name in code
    // (admin: everything via Gate::before) - the same locked set the role
    // editor shows, so the two pages never disagree.
    $roleNames = $user->getPermissionsViaRoles()->pluck('name')
      ->merge($user->roles->flatMap(fn($r) => $this->lockedPermissionsFor($r)))
      ->unique()->flip();
    $specialNames = $user->permissions->pluck('name')->flip();

    $cellFor = function ($permName, $label) use ($roleNames, $specialNames) {
      $role = isset($roleNames[$permName]);
      $special = isset($specialNames[$permName]);
      return [
        'name' => $permName,
        'label' => $label,
        'role' => $role,
        'special' => $special,
        'final' => $role || $special,
      ];
    };

    $rollup = function (array $cells, string $key): string {
      $total = count($cells);
      if ($total === 0) return 'none';
      $granted = count(array_filter($cells, fn($c) => $c[$key]));
      if ($granted === 0) return 'none';
      return $granted === $total ? 'full' : 'partial';
    };

    $modules = [];
    $summary = ['totalModules' => 0, 'fullAccess' => 0, 'viewOnly' => 0, 'partial' => 0, 'notGranted' => 0];

    foreach ($matrix as $moduleName => $m) {
      $columns = [];
      foreach ($m['crud'] as $verb => $entry) {
        $columns[$verb] = $entry ? $cellFor($entry['name'], $entry['label']) : null;
      }
      $other = [];
      foreach ($m['other'] as $permName => $label) {
        $other[] = $cellFor($permName, $label);
      }
      $allCells = array_merge(array_values(array_filter($columns)), $other);

      $finalState = $rollup($allCells, 'final');
      if ($finalState === 'partial' && ($columns['view']['final'] ?? false) && count(array_filter($allCells, fn($c) => $c['final'])) === 1) {
        $finalState = 'view_only';
      }

      $anyRole = count(array_filter($allCells, fn($c) => $c['role'])) > 0;
      $anySpecial = count(array_filter($allCells, fn($c) => $c['special'])) > 0;
      $source = $anyRole && $anySpecial ? 'both' : ($anyRole ? 'role' : ($anySpecial ? 'special' : 'none'));

      $summary['totalModules']++;
      match ($finalState) {
        'full' => $summary['fullAccess']++,
        'view_only' => $summary['viewOnly']++,
        'partial' => $summary['partial']++,
        default => $summary['notGranted']++,
      };

      $count = fn(string $key) => count(array_filter($allCells, fn($c) => $c[$key]));
      $modules[] = [
        'name' => $moduleName,
        'slug' => $m['slug'],
        'roleState' => $rollup($allCells, 'role'),
        'specialState' => $rollup($allCells, 'special'),
        'finalState' => $finalState,
        'source' => $source,
        'columns' => $columns,
        'other' => $other,
        // "3 / 5" reads unambiguously as action permissions - a bare
        // "Not Granted" was read as "can't open this at all".
        'total' => count($allCells),
        'roleCount' => $count('role'),
        'specialCount' => $count('special'),
        'finalCount' => $count('final'),
      ];
    }

    // Permission-level totals - the same unit as the table and the popup.
    $summary['totalPermissions'] = array_sum(array_column($modules, 'total'));
    $summary['fromRole'] = array_sum(array_column($modules, 'roleCount'));
    $summary['finalPermissions'] = array_sum(array_column($modules, 'finalCount'));
    $summary['specialOnly'] = $summary['finalPermissions'] - $summary['fromRole'];

    return ['modules' => $modules, 'summary' => $summary];
  }

  public function addOrUpdateAjax(Request $request)
  {

    if (env('APP_DEMO')) {
      return Error::response('This feature is disabled in demo mode');
    }

    // Validate the request
    $validator = validator($request->all(), [
      'name' => 'required|string|unique:roles,name' . ($request->id ? ',' . $request->id : ''),
      'description' => 'nullable|string|max:255',
      'preset' => 'nullable|string',
    ]);

    if ($validator->fails()) {
      return Error::response($validator->errors()->first());
    }

    // Prepare role data
    $roleData = [
      'name' => $request->name,
      'description' => $request->input('description') ?: null,
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
    $roleNow = $request->id ? Role::find($request->id) : null;
    if ($guard = $this->guardPrivilegeEscalation($existingPermissions, null, $roleNow ? $roleNow->permissions->pluck('name')->all() : [])) {
      return $guard;
    }

    // Check if updating or creating
    if ($request->id) {
      // Update Existing Role
      $role = Role::find($request->id);
      if (!$role) {
        return Error::response('Role not found');
      }
      // The name box is read-only for system roles, but that's only the
      // browser - access throughout the code is keyed on these exact names.
      if (in_array($role->name, Constants::BuiltInRoles) && $request->name !== $role->name) {
        return Error::response('System role names can\'t be changed.');
      }
      $role->update($roleData);

      // admin's boxes are all locked (Gate::before already grants it
      // everything), so the form sends none of them - syncing that would
      // wipe its stored rows for nothing. Leave them exactly as they are.
      if ($this->isSuperRole($role)) {
        return Success::response('Role updated successfully');
      }

      // Sync Permissions (Filter valid names only to avoid crash). Locked
      // boxes are disabled, so they never arrive in the request - merge
      // them back in rather than let the save strip them.
      $requestedPermissions = array_merge($request->permissions ?? [], $this->lockedPermissionsFor($role));
      $existingPermissions = Permission::whereIn('name', $requestedPermissions)->pluck('name')->toArray();

      // SAFETY: the permissions UI only ever renders a checkbox for keys in
      // config/permissions_registry.php. Several roles still carry older
      // permission names from before that registry existed (e.g. the
      // Job/Job Application/Interview Schedule family, still live-checked
      // via Auth::user()->can(...) in the recruitment controllers) that have
      // no checkbox anywhere in this form. Never let a save silently strip a
      // permission it never had the chance to show - only sync away a
      // registry-tracked one that was actually unticked.
      $registryKeys = collect(config('permissions_registry'))->flatMap(fn($perms) => array_keys($perms))->all();
      $outsideRegistry = $role->permissions->pluck('name')->diff($registryKeys)->values()->all();
      $role->syncPermissions(array_values(array_unique(array_merge($existingPermissions, $outsideRegistry))));

      return Success::response('Role updated successfully');
    } else {
      // Create New Role
      $role = Role::create($roleData);

      // The create step only ever offers a template picker, never raw
      // checkboxes - the permission list comes from our own config, not the
      // request, so there is nothing here for a client to tamper with. Fine
      // tuning happens next, on the dedicated permissions page.
      $presetKey = $request->input('preset');
      $presets = config('role_presets', []);
      $presetPermissions = ($presetKey && isset($presets[$presetKey])) ? ($presets[$presetKey]['permissions'] ?? []) : [];
      $existingPermissions = Permission::whereIn('name', $presetPermissions)->pluck('name')->toArray();
      if ($guard = $this->guardPrivilegeEscalation($existingPermissions)) {
        return $guard;
      }
      $role->syncPermissions($existingPermissions);

      return Success::response(['message' => 'Role created successfully', 'id' => $role->id]);
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
      $temp->assignRole(\App\Support\RoleAssignment::rolesFor($role));
      $temp->load('department', 'roles');

      $tree = \App\Support\Nav::previewMenuFor($temp);
      $label = 'Anyone with only the "' . $role . '" role';

      return Success::response(['label' => $label, 'menu' => $tree]);
    } finally {
      \Illuminate\Support\Facades\DB::rollBack();
    }
  }

  /**
   * SECURITY (H-1): a non-admin actor may not assign an admin-tier permission
   * (one that grants admin.guard passage) to anyone, nor edit their OWN
   * permission set. This preserves legitimate roles.manage delegation for
   * ordinary permissions while blocking self-escalation to admin. Admins are
   * unrestricted. Returns an Error response to abort, or null to proceed.
   */
  private function guardPrivilegeEscalation(array $requestedPermissionNames, $targetUserId = null, array $alreadyThere = []): ?\Illuminate\Http\JsonResponse
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
    // A ticked box opens its pages for every role (AdminRoleGuard::
    // permissionOpens), so someone who is neither admin nor HR - a manager,
    // or a "Manage Roles & Permissions" delegate - may only hand out boxes
    // they hold themselves. Otherwise they could give any role, their own
    // included, pages they cannot open. Boxes already there are not "handed
    // out" and are left alone.
    if ($actor && !$actor->hasRole(['hr', 'HR'])) {
      $held = $actor->getAllPermissions()->pluck('name')->all();
      $beyond = array_values(array_diff($requestedPermissionNames, $alreadyThere, $held));
      if (!empty($beyond)) {
        return Error::response('You can only give permissions you have yourself. Not allowed: ' . implode(', ', $beyond));
      }
    }
    return null;
  }

  /**
   * Grant or remove ONE special (direct) permission for one person - the
   * checkboxes in the page's Effective Permissions panel and its Grant
   * buttons. Same H-1 guard as the full save. Only registry permissions,
   * and never one the person's role already gives (nothing to add, and
   * removing it here wouldn't take the role's access away).
   */
  public function toggleUserPermissionAjax(Request $request)
  {
    $request->validate([
      'user_id' => 'required|integer|exists:users,id',
      'permission' => 'required|string',
      'grant' => 'required|boolean',
    ]);
    $user = User::with('permissions', 'roles')->find($request->user_id);
    $perm = $request->permission;

    $registryKeys = collect(config('permissions_registry'))->flatMap(fn($perms) => array_keys($perms))->all();
    if (!in_array($perm, $registryKeys, true) || !Permission::where('name', $perm)->exists()) {
      return Error::response('Unknown permission.');
    }
    if ($guard = $this->guardPrivilegeEscalation($request->boolean('grant') ? [$perm] : [], $user->id)) {
      return $guard;
    }
    $fromRole = $user->getPermissionsViaRoles()->pluck('name')
      ->merge($user->roles->flatMap(fn($r) => $this->lockedPermissionsFor($r)))->contains($perm);
    if ($fromRole) {
      return Error::response('Their role already gives this - it can\'t be added or removed per person.');
    }

    $has = $user->permissions->contains('name', $perm);
    if ($request->boolean('grant') && !$has) {
      $user->givePermissionTo($perm);
      $this->logGrantChanges($user->id, [$perm], []);
    } elseif (!$request->boolean('grant') && $has) {
      $user->revokePermissionTo($perm);
      $this->logGrantChanges($user->id, [], [$perm]);
    }

    return Success::response(($request->boolean('grant') ? 'Granted' : 'Removed') . ' "' . $perm . '" for ' . $user->getFullName() . '.');
  }

  /** Append who changed a person's special permissions; also drops their stale search cache. */
  private function logGrantChanges(int $userId, array $granted, array $revoked): void
  {
    $rows = [];
    foreach ([['granted', $granted], ['revoked', $revoked]] as [$action, $names]) {
      foreach ($names as $name) {
        $rows[] = ['user_id' => $userId, 'permission' => $name, 'action' => $action, 'actor_id' => auth()->id(), 'created_at' => now()];
      }
    }
    if ($rows) {
      \Illuminate\Support\Facades\DB::table('permission_grant_logs')->insert($rows);
      // Global search caches each person's page list for an hour.
      \Illuminate\Support\Facades\Cache::forget("global_search_data_{$userId}_hr");
      \Illuminate\Support\Facades\Cache::forget("global_search_data_{$userId}_other");
    }
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
