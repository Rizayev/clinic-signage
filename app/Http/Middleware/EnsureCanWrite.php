<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows safe (read) methods for any authenticated user, but restricts
 * mutating methods (POST/PUT/PATCH/DELETE) to the given writer roles.
 * super_admin always passes (handled by User::hasRole).
 */
class EnsureCanWrite
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            return response()->json(['message' => 'Недостаточно прав для изменения данных.'], 403);
        }

        return $next($request);
    }
}
