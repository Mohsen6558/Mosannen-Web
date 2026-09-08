<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The whole application is Persian and lives in a single clinic timezone.
 * Pinning both here keeps date formatting deterministic across queue workers,
 * scheduled jobs and web requests.
 */
class SetLocaleAndTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(config('app.locale', 'fa'));
        date_default_timezone_set(config('app.timezone', 'Asia/Tehran'));

        return $next($request);
    }
}
