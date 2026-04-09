<?php

namespace App\Http\Middleware;

use App\ApiClasses\Error;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthenticate
{
  /**
   * Handle an incoming request.
   *
   * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
   */
  public function handle(Request $request, Closure $next): Response
  {
    if (!Auth::check() && $request->path() !== 'api/v1/auth/login' && $request->path() !== 'api/v1/auth/register' && $request->path() !== 'api/v1/auth/checkEmail') {
      return Error::response('Unauthorized', 401);
    }

    return $next($request);
  }
}
