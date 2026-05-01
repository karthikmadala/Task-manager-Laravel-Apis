<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => [
                'status' => 'ok',
                'connection' => config('queue.default'),
            ],
        ];

        $healthy = collect($checks)->every(
            static fn (array $check): bool => ($check['status'] ?? 'failed') === 'ok'
        );

        return api_response(
            $healthy,
            $healthy ? 'System healthy.' : 'System degraded.',
            [
                'timestamp' => now()->toIso8601String(),
                'environment' => config('app.env'),
                'checks' => $checks,
            ],
            null,
            $healthy ? 200 : 503
        );
    }

    public function admin(): JsonResponse
    {
        return api_response(true, 'Admin route accessible.');
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok'];
        } catch (\Throwable $exception) {
            report($exception);

            return ['status' => 'failed'];
        }
    }

    private function checkCache(): array
    {
        $key = 'health-check:' . now()->timestamp;

        try {
            Cache::put($key, 'ok', now()->addSeconds(10));

            return [
                'status' => Cache::get($key) === 'ok' ? 'ok' : 'failed',
                'store' => config('cache.default'),
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'status' => 'failed',
                'store' => config('cache.default'),
            ];
        } finally {
            Cache::forget($key);
        }
    }
}
