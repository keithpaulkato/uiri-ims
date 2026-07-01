<?php

namespace App\Http\Middleware;

use App\Support\RoleHierarchy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleOrAbove
{
    /**
     * Handle an incoming request.
     *
     * Aborts with 403 unless the authenticated user's role is at or
     * above the given required role in the RoleHierarchy.
     */
    public function handle(Request $request, Closure $next, string $requiredRole): Response
    {
        $userRole = $request->user()?->role ?? '';

        if (! RoleHierarchy::isAtOrAbove($userRole, $requiredRole)) {
            abort(403);
        }

        return $next($request);
    }
}
