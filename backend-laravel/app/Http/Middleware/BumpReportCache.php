<?php

namespace App\Http\Middleware;

use App\Support\ReportCache;
use Closure;
use Illuminate\Http\Request;

/**
 * After any successful change through the API (POST, PUT, PATCH, DELETE), cached report
 * figures are marked out of date, so reports are fast but never show old numbers.
 */
class BumpReportCache
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && $response->getStatusCode() < 400) {
            ReportCache::bump();
        }

        return $response;
    }
}
