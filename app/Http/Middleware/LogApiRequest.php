<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        try {
            ApiLog::create([
                'user_id'     => $request->user()?->id,
                'method'      => $request->method(),
                'path'        => $request->path(),
                'status_code' => $response->getStatusCode(),
                'ip'          => $request->ip(),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $response;
    }
}
