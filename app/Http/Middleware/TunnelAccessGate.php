<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * A passphrase wall for a developer's temporary Cloudflare (or any reverse-proxy) tunnel to a LOCAL instance.
 *
 * Why it exists: on a local instance the sign-in shortcuts (the DEV QUICK ACCESS panel and the 123456 OTP) are
 * the only way in -- there is no mail -- so they have to keep working through a tunnel, but a tunnel is public.
 * Rather than expose them to everyone who learns the URL, tunnel traffic must first present TUNNEL_ACCESS_PASSWORD
 * as the HTTP Basic password; only a request that has done so gets the shortcuts (see isLocalShortcutAllowed()).
 *
 * Inert unless TUNNEL_ACCESS_PASSWORD is set, which only a developer's own .env does: with it unset (every real
 * deployment) this middleware does nothing at all, and the shortcuts keep their original localhost-only rule.
 *
 * Guessing is throttled, so the passphrase can be short and memorable: MAX_FAILS_PER_IP wrong passwords from one
 * visitor lock that visitor out for 15 minutes, and MAX_FAILS_GLOBAL wrong passwords from anyone lock everybody out
 * for the rest of the hour. A locked-out visitor is refused even with the right password (otherwise the lock would
 * tell a guesser when they had it). The trade-off: someone hammering the address can keep the tunnel unusable for
 * as long as they keep at it -- localhost is never affected.
 *
 * "Via a tunnel" is decided by the request itself, not by the Host header alone, because a client of a tunnel
 * controls the Host it sends: a request counts as local only when its host is localhost AND it carries none of
 * the headers a proxy or Cloudflare adds. This runs before TrustProxies, so nothing a client sends about its
 * "real" host is believed here.
 */
class TunnelAccessGate
{
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]'];

    private const PROXY_HEADERS = ['CF-Ray', 'CF-Connecting-IP', 'CDN-Loop', 'X-Forwarded-For', 'X-Forwarded-Host', 'Forwarded'];

    private const MAX_FAILS_PER_IP = 5;
    private const IP_WINDOW_SECONDS = 900;
    private const MAX_FAILS_GLOBAL = 60;
    private const GLOBAL_WINDOW_SECONDS = 3600;
    private const GLOBAL_KEY = 'tunnel-gate:fails:global';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) env('TUNNEL_ACCESS_PASSWORD', '');
        if ($secret === '' || !self::viaTunnel($request)) {
            return $next($request);
        }

        $cache = Cache::store('file');
        $ipKey = self::ipKey($request);
        if ((int) $cache->get($ipKey, 0) >= self::MAX_FAILS_PER_IP || (int) $cache->get(self::GLOBAL_KEY, 0) >= self::MAX_FAILS_GLOBAL) {
            return response('Too many wrong attempts. Try again later.', 429, ['Retry-After' => (string) self::IP_WINDOW_SECONDS]);
        }

        $given = $request->getPassword();
        if (is_string($given) && hash_equals($secret, $given)) {
            $request->attributes->set('tunnel_gate_passed', true);
            // Only a request that has just proved it holds the passphrase gets its forwarding headers believed, so
            // the original https scheme (and visitor IP) reach the app and links/assets stop coming out http://.
            // X-Forwarded-Host is deliberately left out: a visitor must not be able to claim to be "localhost".
            // TrustProxies runs after this middleware, so setting its static config here is early enough.
            TrustProxies::at('*');
            TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
            return $next($request);
        }

        // No Authorization header at all is just the browser's first, credential-less visit (it is about to show its
        // password box) and costs nothing; only a password that was actually presented and was wrong counts.
        if ($given !== null) {
            foreach ([[$ipKey, self::IP_WINDOW_SECONDS], [self::GLOBAL_KEY, self::GLOBAL_WINDOW_SECONDS]] as [$key, $seconds]) {
                $cache->add($key, 0, $seconds);
                $cache->increment($key);
            }
        }

        return response('Authentication required.', 401, ['WWW-Authenticate' => 'Basic realm="HRX preview", charset="UTF-8"']);
    }

    /** Cloudflare puts the visitor's address in CF-Connecting-IP (replacing anything the visitor sent). */
    private static function ipKey(Request $request): string
    {
        $ip = (string) ($request->headers->get('CF-Connecting-IP') ?: $request->server->get('REMOTE_ADDR', ''));
        return 'tunnel-gate:fails:' . sha1($ip);
    }

    /** True for anything that did not come straight from this machine's own localhost. */
    public static function viaTunnel(Request $request): bool
    {
        if (!in_array($request->getHost(), self::LOCAL_HOSTS, true)) {
            return true;
        }
        foreach (self::PROXY_HEADERS as $header) {
            if ($request->headers->has($header)) {
                return true;
            }
        }
        return false;
    }

    /**
     * May the dev sign-in shortcuts be offered/accepted for this request? Plain localhost: yes (as before).
     * A tunnel request: only once it has passed the passphrase gate above.
     */
    public static function isLocalShortcutAllowed(Request $request): bool
    {
        if (!app()->environment('local')) {
            return false;
        }
        return !self::viaTunnel($request) || $request->attributes->get('tunnel_gate_passed') === true;
    }
}
