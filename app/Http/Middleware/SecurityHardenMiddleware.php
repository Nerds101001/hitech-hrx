<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SecurityHardenMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 2. Inject Security Headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // SECURITY (M-14): Content-Security-Policy.
        // A full script-src policy needs nonces on the app's many inline <script>
        // blocks plus an inventory of external script origins actually used
        // (Razorpay checkout, PayPal, Firebase, Google Maps/Fonts, Vite), so the
        // strict policy is shipped Report-Only for now — it never blocks, it only
        // reports violations so the real allow-list can be built before enforcing.
        // The directives ENFORCED below are the ones that never break normal app
        // content: no plugins/objects, no <base> hijacking, no external framing.
        $response->headers->set(
            'Content-Security-Policy',
            "object-src 'none'; base-uri 'self'; frame-ancestors 'self'"
        );
        $response->headers->set(
            'Content-Security-Policy-Report-Only',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
            "font-src 'self' data: https://fonts.gstatic.com; " .
            "img-src 'self' data: blob: https:; " .
            "connect-src 'self' https: wss:; " .
            "object-src 'none'; base-uri 'self'; frame-ancestors 'self'"
        );

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
