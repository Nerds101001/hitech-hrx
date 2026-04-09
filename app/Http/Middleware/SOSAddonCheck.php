<?php

namespace App\Http\Middleware;

use App\Services\AddonService\IAddonService;
use App\Constants\ModuleConstants;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SOSAddonCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $addonService = app(IAddonService::class);
        if (!$addonService->isAddonEnabled(ModuleConstants::SOS, true)) {
            return redirect()->route('accessDenied')->with('error', 'You do not have permission to access this page');
        }

        return $next($request);
    }
}
