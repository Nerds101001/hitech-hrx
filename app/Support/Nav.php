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

        // Same three gates the render loop applies at each level - a parent
        // AND a child can each carry their own roles/permission/department.
        $passesGate = function ($item) use ($user, $isAdminUser, $hasAnyPermission, $addonService, $settings) {
            if (isset($item->addon) && !$addonService->isAddonEnabled($item->addon)) return false;
            if (isset($item->standardAddon)
                && !in_array($item->standardAddon, $settings->available_modules ?? [])) return false;
            if (isset($item->roles)) {
                $ur = array_map('strtolower', $user->roles->pluck('name')->toArray());
                $rr = array_map('strtolower', (array) $item->roles);
                if (empty(array_intersect($ur, $rr))) return false;
            }
            if (isset($item->permission) && !$isAdminUser) {
                if (!$hasAnyPermission($item->permission)) return false;
            }
            if (isset($item->department) && !$isAdminUser) {
                if (!$user->department || stripos($user->department->name, $item->department) === false) return false;
            }
            return true;
        };

        $tree = [];
        foreach ($menu as $item) {
            if (isset($item->menuHeader)) {
                continue; // section labels, nothing to preview
            }

            $parentGateOk = $passesGate($item);
            $entry = [
                'name' => $item->name ?? '(unnamed)',
                'icon' => $item->icon ?? null,
                'visible' => false,
                'reason' => null,
                'submenu' => null,
            ];

            if (!$parentGateOk) {
                $entry['reason'] = 'Restricted to a role, permission, or department this person does not have.';
                $tree[] = $entry;
                continue;
            }

            if (isset($item->submenu)) {
                $children = [];
                $anyChildVisible = false;
                foreach ($item->submenu as $sub) {
                    $childGateOk = $passesGate($sub);
                    $urlOk = !isset($sub->url) || self::allows(url($sub->url), $user);
                    $visible = $childGateOk && $urlOk;
                    $anyChildVisible = $anyChildVisible || $visible;
                    $children[] = [
                        'name' => $sub->name ?? '(unnamed)',
                        'url' => $sub->url ?? null,
                        'visible' => $visible,
                        'reason' => $visible ? null : (!$childGateOk
                            ? 'Restricted to a role, permission, or department this person does not have.'
                            : 'The route itself refuses this person (role/permission check on the page, not the menu).'),
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
