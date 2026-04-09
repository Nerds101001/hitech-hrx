<?php

namespace App\Http\Middleware;

use App\Enums\UserAccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnboardingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user) {
            // Onboarding Gate Disabled as per USER request to "unlock the whole program"
            return $next($request);
            
            /* $userStatus = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;
            ... */
        }

        return $next($request);
    }
}
