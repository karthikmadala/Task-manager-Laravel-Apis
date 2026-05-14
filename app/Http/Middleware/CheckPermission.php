<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permissions): mixed
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            return api_response(false, 'Unauthenticated.', null, [
                'authorization' => ['Authentication required.'],
            ], 401);
        }

        // Super admin bypass
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Legacy super_admin bypass
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Parse permissions: comma-separated = ANY match, pipe-separated = ALL required
        $isAnyCheck = str_contains($permissions, ',');
        $permList = $isAnyCheck
            ? array_map('trim', explode(',', $permissions))
            : array_map('trim', explode('|', $permissions));

        $hasPermission = $isAnyCheck
            ? $user->hasAnyPermission($permList)
            : $this->hasAllPermissions($user, $permList);

        if (! $hasPermission) {
            $this->logUnauthorizedAttempt($request, $user, $permissions);

            return api_response(false, 'Forbidden.', null, [
                'authorization' => ["Required permission: {$permissions}"],
            ], 403);
        }

        return $next($request);
    }

    private function hasAllPermissions($user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $user->hasPermissionTo($permission)) {
                return false;
            }
        }
        return true;
    }

    private function logUnauthorizedAttempt(Request $request, $user, string $requiredPermission): void
    {
        try {
            ApiLog::create([
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => 403,
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_body' => null,
                'response_body' => null,
                'duration_ms' => 0,
            ]);
        } catch (\Throwable) {
            // Fail silently - do not block the request
        }
    }
}