<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetHrLayoutMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
      // Check if a user is authenticated
      if (auth()->check()) {

        // Check if the user has the 'hr' role.
        // Adjust this condition based on how you manage roles (e.g., Spatie Permissions: $user->hasRole('hr'))
        // Assuming you have a `role` attribute or a `hasRole()` method on your User model:
        if (auth()->user()->hasRole('hr')) {

          // Define the pageConfigs for HR users
          $pageConfigs = ['myLayout' => 'vertical'];

          // Share this variable with all views rendered during this request cycle
          View::share('pageConfigs', $pageConfigs);
        }
        // Optional: You could define default pageConfigs for other roles here if needed
        // else {
        //     View::share('pageConfigs', ['myLayout' => 'vertical']); // Example default
        // }
      }

      // Continue processing the request
      return $next($request);
    }
}
