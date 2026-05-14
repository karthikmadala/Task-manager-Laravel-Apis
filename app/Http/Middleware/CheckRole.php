<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $roles): mixed
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            return api_response(false, 'Unauthenticated.', null, [
                'authorization' => ['Authentication required.'],
            ], 401);
        }

        // Super admin bypass
        if ($user->role_id && $user->role && $user->role->is_super_admin) {
            return $next($request);
        }

        // Legacy super_admin bypass
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Parse roles: comma-separated = ANY match
        $roleList = array_map('trim', explode(',', $roles));

        $hasRole = false;
        foreach ($roleList as $role) {
            if ($user->role_id && $user->role && $user->role->name === $role) {
                $hasRole = true;
                break;
            }
            // Legacy role field check
            if ($user->role === $role) {
                $hasRole = true;
                break;
            }
        }

        if (! $hasRole) {
            return api_response(false, 'Forbidden.', null, [
                'authorization' => ["Required role: {$roles}"],
            ], 403);
        }

        return $next($request);
    }
}