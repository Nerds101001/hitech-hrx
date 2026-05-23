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
            $userStatus = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;
            
            $onboardingStatuses = [
                UserAccountStatus::ONBOARDING->value,
                UserAccountStatus::ONBOARDING_REQUESTED->value,
                UserAccountStatus::ONBOARDING_SUBMITTED->value
            ];
            
            if (in_array($userStatus, $onboardingStatuses)) {
                $currentRouteName = $request->route() ? $request->route()->getName() : null;
                $currentPath = '/' . ltrim($request->getPathInfo(), '/');
                
                // 1. Always allow logout, changing language, and public recruitment routes
                $alwaysAllowedPaths = [
                    '/auth/logout',
                    '/logout',
                ];
                $alwaysAllowedRoutes = [
                    'auth.logout',
                    'career',
                    'job.requirement',
                    'job.apply',
                    'job.apply.data',
                    'terms-and-conditions',
                ];
                
                if (in_array($currentPath, $alwaysAllowedPaths) || 
                    in_array($currentRouteName, $alwaysAllowedRoutes) ||
                    str_starts_with($currentPath, '/lang/') ||
                    str_starts_with($currentPath, '/career') ||
                    str_starts_with($currentPath, '/public-job') ||
                    str_starts_with($currentPath, '/terms_and_condition')) {
                    return $next($request);
                }
                
                // 2. Handle ONBOARDING / ONBOARDING_REQUESTED status
                if ($userStatus === UserAccountStatus::ONBOARDING->value || $userStatus === UserAccountStatus::ONBOARDING_REQUESTED->value) {
                    $allowedOnboardingRoutes = [
                        'onboarding.form',
                        'onboarding.store',
                        'onboarding.status',
                        'onboarding.autoSave',
                        'onboarding.uploadFile',
                    ];
                    
                    if (in_array($currentRouteName, $allowedOnboardingRoutes) || 
                        str_starts_with($currentPath, '/onboarding')) {
                        return $next($request);
                    }
                    
                    return redirect()->route('onboarding.form');
                }
                
                // 3. Handle ONBOARDING_SUBMITTED status
                if ($userStatus === UserAccountStatus::ONBOARDING_SUBMITTED->value) {
                    // If training is required, allow access to training routes
                    if ($user->is_training_required) {
                        if (str_starts_with($currentPath, '/training') || 
                            ($currentRouteName && str_starts_with($currentRouteName, 'training.'))) {
                            return $next($request);
                        }
                    }
                    
                    // Allow access to dashboard and onboarding status
                    $allowedSubmittedRoutes = [
                        'user.dashboard.index',
                        'onboarding.status',
                    ];
                    
                    if (in_array($currentRouteName, $allowedSubmittedRoutes) || 
                        $currentPath === '/user/dashboard' || 
                        $currentPath === '/onboarding/status') {
                        return $next($request);
                    }
                    
                    // If training is required, redirect to training, otherwise to dashboard
                    if ($user->is_training_required) {
                        return redirect()->route('training.portal');
                    }
                    
                    return redirect()->route('user.dashboard.index');
                }
            }
        }

        return $next($request);
    }
}
