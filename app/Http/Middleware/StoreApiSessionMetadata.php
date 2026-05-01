<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StoreApiSessionMetadata
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = $request->user();

        if (! $user) {
            return $response;
        }

        $token = $user->currentAccessToken();
        $tokenId = (string) ($token?->id ?? 'no-token');
        $sessionId = hash('sha256', implode('|', [
            'api',
            (string) $user->id,
            $tokenId,
            (string) $request->ip(),
            (string) $request->userAgent(),
        ]));

        Cache::put(
            "api-session-metadata:{$sessionId}",
            [
                'user_id' => $user->id,
                'token_id' => $tokenId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'updated_at' => now()->toIso8601String(),
            ],
            now()->addMinutes((int) config('session.lifetime', 120))
        );

        return $response;
    }
}
