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

        foreach ($route->gatherMiddleware() as $m) {
            if (!is_string($m)) {
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
}
