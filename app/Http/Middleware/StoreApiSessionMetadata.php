<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StoreApiSessionMetadata
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = $request->user();

        if (! $user || ! Schema::hasTable('sessions')) {
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

        DB::table('sessions')->updateOrInsert(
            ['id' => $sessionId],
            [
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => base64_encode(json_encode([
                    'type' => 'api_session',
                    'token_id' => $tokenId,
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'route_name' => $request->route()?->getName(),
                    'updated_at' => now()->toISOString(),
                ])),
                'last_activity' => now()->timestamp,
            ]
        );

        return $response;
    }
}
