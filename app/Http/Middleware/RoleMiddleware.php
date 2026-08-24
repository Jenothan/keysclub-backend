<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthorized');
        }

        // Roles are passed as comma separated if defined in route like 'role:Admin,Super Admin'
        // But Laravel parses them as multiple arguments to the handle method.
        // If they are passed as a single string 'Admin,Super Admin', we split them.
        $allowedRoles = [];
        foreach ($roles as $roleGroup) {
            $allowedRoles = array_merge($allowedRoles, explode(',', $roleGroup));
        }
        $allowedRoles = array_map('trim', $allowedRoles);

        if (!in_array($user->role, $allowedRoles)) {
            abort(403, 'Forbidden. You do not have the required role.');
        }

        return $next($request);
    }
}
