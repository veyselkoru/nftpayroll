<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (empty($roles)) {
            return $next($request);
        }

        if (! in_array($user->normalizedRole(), $roles, true)) {
            return response()->json(['message' => 'Bu alana erişim yetkiniz yok.'], 403);
        }

        return $next($request);
    }
}
