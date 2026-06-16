<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API is stateless/JSON-only. Force the Accept header so framework
 * exceptions (auth, validation, not-found) always render as JSON instead
 * of attempting an HTML redirect to a non-existent `login` route.
 */
class ForceJsonApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
