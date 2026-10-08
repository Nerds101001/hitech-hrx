<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sales Sources' Tally analytics group many rows by COALESCE()/NULLIF() expressions and
 * select the same expression back. That is well-defined, but some MySQL 8 builds reject it
 * under ONLY_FULL_GROUP_BY ("<col> isn't in GROUP BY") where others accept it. Rather than
 * rewrite every analytics query, drop just that one mode for the duration of a Sales Sources
 * request — these are read-only reporting pages. Every other strict mode stays in force, and
 * because PHP uses a fresh DB connection per request this never leaks to other pages.
 */
class RelaxSalesSourcesSqlMode
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            DB::statement("SET SESSION sql_mode = (SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''))");
        } catch (\Throwable $e) {
            // If the grant can't change sql_mode, fall through — the pages that need it will
            // still error loudly rather than this silently breaking the request.
        }

        return $next($request);
    }
}
