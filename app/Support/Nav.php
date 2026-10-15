<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * "Can this user actually open that link?"
 *
 * Dashboards and menus were hand-listing tiles whose routes the signed-in role
 * had no access to, so every click landed on a 403. Rather than keep two lists
 * in sync by hand, ask the router: match the URL, read the authorisation
 * middleware it really carries, and test this user against it.
 *
 * A URL with no matching route also returns false, so dead links disappear
 * instead of 404-ing.
 */
class Nav
{
    /** Per-request memo — a dashboard asks about the same URL several times. */
    private static array $cache = [];

    public static function allows(string $url, $user = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user) {
            return false;
        }

        $path = '/' . ltrim(parse_url($url, PHP_URL_PATH) ?? '/', '/');
        $key  = $user->id . '|' . $path;

        return self::$cache[$key] ??= self::evaluate($path, $user);
    }

    /**
     * Drop the memo.
     *
     * The cache is keyed on user and path, which is right for a single
     * request, but it also survives a change to the CONFIG that a decision
     * depended on -- so a test that flips telephony.restrict_to_emails and
     * re-asks would get the previous answer.
     */
    public static function flush(): void
    {
        self::$cache = [];
        self::$permissionCache = [];
        self::$grantedCache = [];
    }

    /** Per-request memo of the permission names each path's route accepts. */
    private static array $permissionCache = [];

    /**
     * The permission names the page at $url accepts - the dotted tokens of
     * its OWN gate, the innermost role_or_permission: / permission:
     * middleware. Role names and can: gates are left out; those aren't
     * something a checkbox grants.
     *
     * Only the innermost gate: an outer group gate lists every permission of
     * every page inside it, so reading it made "Manage 90-Day Goals" count as
     * opening Biometric Devices for a manager (the manager role passes the
     * page's own gate by name, the permission never did).
     */
    public static function permissionsFor(string $url): array
    {
        $path = '/' . ltrim(parse_url($url, PHP_URL_PATH) ?? '/', '/');

        return self::$permissionCache[$path] ??= (function () use ($path) {
            try {
                $route = RouteFacade::getRoutes()->match(Request::create($path, 'GET'));
            } catch (\Throwable $e) {
                return [];
            }
            $perms = [];
            foreach ($route->gatherMiddleware() as $m) {
                if (!is_string($m)) {
                    continue;
                }
                foreach (['role_or_permission:', 'permission:'] as $prefix) {
                    if (str_starts_with($m, $prefix)) {
                        $perms = []; // a later (inner) gate replaces an outer one
                        foreach (explode('|', substr($m, strlen($prefix))) as $token) {
                            if (str_contains($token, '.')) {
                                $perms[] = trim($token);
                            }
                        }
                    }
                }
            }
            return array_values(array_unique($perms));
        })();
    }

    /**
     * Sidebar "roles" lists are hand-kept, so a permission ticked on the
     * Roles & Permissions page never showed its link. A role-gated item is
     * also shown when the person holds a permission the item's own page
     * accepts AND that page would really open for them (allows() - so a
     * stricter outer gate still hides it). $urls is the item's url, or its
     * children's urls for a group header.
     */
    public static function grantedByPermission(array $urls, $user): bool
    {
        if (!$user) {
            return false;
        }
        $granted = self::grantedPermissionNames($user);
        foreach ($urls as $url) {
            if (!$url) {
                continue;
            }
            $perms = self::permissionsFor($url);
            if ($perms && array_intersect($perms, $granted) && self::allows($url, $user)
                && self::passesAdminGuard($url, $user)) {
                return true;
            }
        }
        return false;
    }

    /**
     * allows() only reads role/permission middleware; admin.guard sits on
     * top and turns away employee/office_employee (and anyone without an
     * admin-type role) unless they hold an ELEVATED permission. Mirrors
     * AdminRoleGuard::handle() so a granted permission never draws a link
     * that guard would bounce.
     */
    private static function passesAdminGuard(string $url, $user): bool
    {
        $path = '/' . ltrim(parse_url($url, PHP_URL_PATH) ?? '/', '/');
        try {
            $route = RouteFacade::getRoutes()->match(Request::create($path, 'GET'));
        } catch (\Throwable $e) {
            return false;
        }
        if (!in_array('admin.guard', $route->gatherMiddleware(), true)) {
            return true;
        }
        $guard = \App\Http\Middleware\AdminRoleGuard::class;
        $override = $user->getAllPermissions()->pluck('name')->intersect($guard::ELEVATED_PERMISSIONS)->isNotEmpty()
            || $guard::permissionOpens($route, $user);
        if ($user->hasRole(['employee', 'office_employee'])) {
            return $override;
        }
        return $user->hasAnyRole($guard::ADMIN_ROLES) || $override;
    }

    /** Per-request memo for grantedPermissionNames(). */
    private static array $grantedCache = [];

    /**
     * Permissions someone was actually granted on the Roles / Special
     * Permissions pages - everything they hold, minus the rows stored only
     * so the matrix shows what their role already has by name
     * (config/role_builtin_permissions.php). Those mirror route access the
     * sidebar deliberately doesn't advertise; counting them would add links
     * for every manager/accounts user nobody chose to grant.
     */
    private static function grantedPermissionNames($user): array
    {
        return self::$grantedCache[$user->id] ??= (function () use ($user) {
            $builtIn = [];
            foreach ($user->roles->pluck('name') as $role) {
                $builtIn = array_merge($builtIn, config('role_builtin_permissions.' . $role, []));
            }
            $direct = $user->getDirectPermissions()->pluck('name')->all();
            $all = $user->getAllPermissions()->pluck('name')->all();
            return array_values(array_diff($all, array_diff($builtIn, $direct)));
        })();
    }

    /** A menu JSON item's own url, or its children's when it's a group. */
    public static function menuUrls($item): array
    {
        $item = (object) $item;
        if (!empty($item->url)) {
            return [$item->url];
        }
        return array_values(array_filter(array_map(fn($s) => ((object) $s)->url ?? null, (array) ($item->submenu ?? []))));
    }

    /** Filter a list of ['url' => ...] rows down to what the user can open. */
    public static function filter(array $items, string $urlKey = 'url', $user = null): array
    {
        return array_values(array_filter(
            $items,
            fn($i) => empty($i[$urlKey]) || self::allows($i[$urlKey], $user)
        ));
    }

    private static function evaluate(string $path, $user): bool
    {
        try {
            $route = RouteFacade::getRoutes()->match(Request::create($path, 'GET'));
        } catch (\Throwable $e) {
            return false; // no such route — treat as a dead link
        }

        // The KPI Approvals link is role-gated to "manager", which dozens of
        // people hold. Whether the page has anything for THIS person is data
        // (are they a named approver?), not a role, so ask the service.
        if ($route->getName() === 'tenant.kpi.approvals'
            && !\App\Services\KpiEntryService::canOpenApprovals($user)) {
            return false;
        }

        // Same idea for Bulk KPI Entry: the route is open to any logged-in user
        // (data owners are usually neither admin nor HR), but only a KPI admin
        // or a named data owner has anything there - everyone else was shown a
        // link that opened a 403.
        if ($route->getName() === 'tenant.kpi.bulk-entry'
            && !\App\Models\KpiDataOwner::canOpenBulkEntry($user)) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $m) {
            if (!is_string($m)) {
                continue;
            }

            // A feature gated to named testers. Aliases are opaque strings
            // here, so Nav cannot infer this one the way it does role: and
            // permission: -- and without it the sidebar draws a link that only
            // 403s. The rule itself lives on the middleware, so there is one
            // definition rather than two that can drift.
            if ($m === 'telephony.restricted') {
                if (!\App\Http\Middleware\RestrictTelephonyAccess::permits($user)) {
                    return false;
                }
                continue;
            }

            // Tally-derived pages are limited to the emails in TALLY_VIEWER_EMAILS; same reasoning as above.
            if ($m === 'tally.viewer') {
                if (!\App\Http\Middleware\RestrictTallyAccess::permits($user)) {
                    return false;
                }
                continue;
            }

            // Leaver Handover is limited to the emails in config('handover.viewer_emails')
            // who are also Admin/HR; same reasoning as above.
            if ($m === 'handover.viewer') {
                if (!\App\Http\Middleware\RestrictHandoverAccess::permits($user)) {
                    return false;
                }
                continue;
            }

            if (str_starts_with($m, 'can:')) {
                $ability = explode(',', substr($m, 4))[0];
                if (!Gate::forUser($user)->allows($ability)) {
                    return false;
                }
                continue;
            }

            foreach (['role_or_permission:', 'role:', 'permission:'] as $prefix) {
                if (!str_starts_with($m, $prefix)) {
                    continue;
                }

                // Spatie treats "a|b" as "any of", and these middleware stack,
                // so every one of them has to pass.
                $passed = false;
                foreach (explode('|', substr($m, strlen($prefix))) as $token) {
                    $token = trim($token);
                    if ($token === '') {
                        continue;
                    }
                    if ($prefix === 'role:' ? $user->hasRole($token)
                                            : ($user->hasRole($token) || $user->can($token))) {
                        $passed = true;
                        break;
                    }
                }
                if (!$passed) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * "What would this person's sidebar actually look like?" - a faithful,
     * callable copy of verticalMenu.blade.php's own visibility rule (roles /
     * permission / department keys on the menu JSON, department a bypass for
     * admin/hr, then Nav::allows() per URL), built for the Roles & Permissions
     * "Preview as" screen rather than for rendering the real sidebar.
     *
     * Deliberately NOT wired back into the sidebar itself: that file is live,
     * high-traffic, and has broken twice this year from small edits (see its
     * own header comment) - safer to keep this as an independent read of the
     * same JSON than to risk the working page for a preview feature. The
     * shared, load-bearing part - which single URL a role/permission actually
     * unlocks - already lives in one place (allows() above); what's
     * duplicated here is only the JSON tree-walk, not the security decision.
     *
     * Returns every top-level entry and its submenu, tagged 'visible' rather
     * than filtered, so the caller can show what's hidden too, not just what
     * shows.
     */
    public static function previewMenuFor($user): array
    {
        $addonService = app(\App\Services\AddonService\IAddonService::class);
        $settings = \Illuminate\Support\Facades\Cache::get('app_settings')
            ?? \App\Models\Settings::first()
            ?? new \App\Models\Settings();

        $menuIndex = $user->hasRole('super_admin') ? 0 : 3;
        $files = [
            0 => 'resources/menu/verticalMenu.json',
            3 => 'resources/menu/tenantVerticalMenu.json',
        ];
        $json = @file_get_contents(base_path($files[$menuIndex] ?? $files[3]));
        $menu = $json ? (json_decode($json)->menu ?? []) : [];

        $isAdminUser = $user->hasRole(['Admin', 'admin', 'super_admin', 'ADMIN', 'HR', 'hr']);
        $hasAnyPermission = function ($permission) use ($user) {
            foreach ((array) $permission as $p) {
                if ($user->can($p)) return true;
            }
            return false;
        };

        $registryKeys = array_flip(collect(config('permissions_registry'))->flatMap(fn($p) => array_keys($p))->all());

        // Same gates the render loop applies at each level - a parent AND a
        // child can each carry their own addon/roles/permission/department.
        // Returns null when the item passes, or ['text' => ..., 'grantable'
        // => [...]] when it doesn't. 'grantable' is only ever populated for
        // a permission gate, and only with names that are real, manageable
        // registry keys - an addon/role/department gate has no per-role fix
        // a checkbox could ever represent (switching off "Reports" for
        // everyone isn't undone by ticking a box on one role's form), so
        // offering one there would be a button that lies about what it does.
        $gateReason = function ($item) use ($user, $isAdminUser, $hasAnyPermission, $addonService, $settings, $registryKeys) {
            if (isset($item->addon) && !$addonService->isAddonEnabled($item->addon)) {
                return ['text' => "The \"{$item->addon}\" add-on is switched off site-wide - not specific to this person or role.", 'grantable' => []];
            }
            if (isset($item->standardAddon)
                && !in_array($item->standardAddon, $settings->available_modules ?? [])) {
                return ['text' => "The \"{$item->standardAddon}\" module is switched off site-wide - not specific to this person or role.", 'grantable' => []];
            }
            if (isset($item->hideForRoles)
                && array_intersect(array_map('strtolower', $user->roles->pluck('name')->toArray()), array_map('strtolower', (array) $item->hideForRoles))) {
                return ['text' => 'Not shown to: ' . implode(', ', (array) $item->hideForRoles) . ' - this link is for another audience.', 'grantable' => []];
            }
            if (isset($item->roles)) {
                $ur = array_map('strtolower', $user->roles->pluck('name')->toArray());
                $rr = array_map('strtolower', (array) $item->roles);
                if (empty(array_intersect($ur, $rr))
                    && !(isset($item->rolesOrPermission) && $hasAnyPermission($item->rolesOrPermission))
                    && !self::grantedByPermission(self::menuUrls($item), $user)) {
                    // Any permission this item's page(s) accept will show it -
                    // same rule as the live sidebar.
                    $options = array_values(array_unique(array_filter(
                        array_merge((array) ($item->rolesOrPermission ?? []), ...array_map(fn($u) => self::permissionsFor($u), self::menuUrls($item) ?: [''])),
                        fn($p) => isset($registryKeys[$p]) && !$user->can($p)
                    )));
                    return ['text' => 'Menu limited to: ' . implode(', ', (array) $item->roles) . ' - this role isn\'t on that list' . ($options ? ' (or grant a permission its page accepts)' : '') . '.', 'grantable' => $options];
                }
            }
            if (isset($item->permission) && !$isAdminUser) {
                if (!$hasAnyPermission($item->permission)) {
                    $options = array_values(array_filter((array) $item->permission, fn($p) => isset($registryKeys[$p])));
                    return ['text' => 'Requires permission: ' . implode(' or ', (array) $item->permission) . ', which this role doesn\'t hold.', 'grantable' => $options];
                }
            }
            // departmentOrPermission: holding one of these lets a person through the
            // department gate, exactly as the live sidebar does.
            if (isset($item->department) && !$isAdminUser
                && !(isset($item->departmentOrPermission) && $hasAnyPermission($item->departmentOrPermission))) {
                // "department" may list several, pipe-separated (e.g. "Sales|Export") -- a person
                // in ANY of them is let through. A plain single value behaves exactly as before.
                $wantDepts = array_filter(array_map('trim', explode('|', (string) $item->department)));
                $inDept = $user->department && collect($wantDepts)->contains(fn ($d) => stripos($user->department->name, $d) !== false);
                if (!$inDept) {
                    return ['text' => "Limited to the \"{$item->department}\" department" . ($user->department ? " - this person is in \"{$user->department->name}\"." : ' - this person has no department set.'), 'grantable' => []];
                }
            }
            return null;
        };

        $tree = [];
        foreach ($menu as $item) {
            if (isset($item->menuHeader)) {
                continue; // section labels, nothing to preview
            }

            $parentGate = $gateReason($item);
            $entry = [
                'name' => $item->name ?? '(unnamed)',
                'icon' => $item->icon ?? null,
                'visible' => false,
                'reason' => null,
                'grantable' => [],
                'submenu' => null,
            ];

            if ($parentGate !== null) {
                $entry['reason'] = $parentGate['text'];
                $entry['grantable'] = $parentGate['grantable'];
                $tree[] = $entry;
                continue;
            }

            if (isset($item->submenu)) {
                $children = [];
                $anyChildVisible = false;
                // Group shown only through a granted permission: a child with
                // no roles of its own then needs one too (as the live sidebar).
                $viaPermission = isset($item->roles) && !$user->hasRole((array) $item->roles);
                foreach ($item->submenu as $sub) {
                    $childGate = $gateReason($sub);
                    if ($childGate === null && $viaPermission && !isset($sub->roles)
                        && !self::grantedByPermission([$sub->url ?? null], $user)) {
                        $childGate = ['text' => 'Its group is shown only through a granted permission, and this page accepts none this role holds.', 'grantable' => []];
                    }
                    $urlOk = $childGate === null && (!isset($sub->url) || self::allows(url($sub->url), $user));
                    $anyChildVisible = $anyChildVisible || $urlOk;
                    $children[] = [
                        'name' => $sub->name ?? '(unnamed)',
                        'url' => $sub->url ?? null,
                        'visible' => $urlOk,
                        'reason' => $urlOk ? null : ($childGate['text']
                            ?? 'The route itself refuses this person (role/permission check on the page, not the menu).'),
                        'grantable' => $urlOk ? [] : ($childGate['grantable'] ?? []),
                    ];
                }
                $entry['visible'] = $anyChildVisible;
                $entry['submenu'] = $children;
                $entry['reason'] = $anyChildVisible ? null : 'None of its items are reachable for this person.';
                $tree[] = $entry;
                continue;
            }

            $urlOk = !isset($item->url) || self::allows(url($item->url), $user);
            $entry['visible'] = $urlOk;
            $entry['reason'] = $urlOk ? null : 'The route itself refuses this person (role/permission check on the page, not the menu).';
            $tree[] = $entry;
        }

        return $tree;
    }
}
