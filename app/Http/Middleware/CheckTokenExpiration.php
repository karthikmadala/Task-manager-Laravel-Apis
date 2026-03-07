<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckTokenExpiration
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && $token->expires_at && now()->greaterThanOrEqualTo($token->expires_at)) {
            return api_response(false, 'Token expired. Please login again.', null, [
                'token' => ['The access token is expired.'],
            ], 401);
        }

        return $next($request);
    }
}
