<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ApiLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * GET /api/v1/admin/users
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return api_response(true, 'Users retrieved', [
            'users' => UserResource::collection($users),
            'pagination' => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $query = ApiLog::query();

        if ($statusCode = $request->query('status_code')) {
            $query->where('status_code', (int) $statusCode);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        return api_response(true, 'Logs retrieved', [
            'logs' => $logs->map(fn (ApiLog $log) => [
                'id'          => $log->id,
                'method'      => $log->method,
                'path'        => $log->path,
                'status_code' => $log->status_code,
                'duration_ms' => $log->duration_ms,
                'ip_address'  => $log->ip,
                'created_at'  => $log->created_at?->toISOString(),
            ]),
        ]);
    }
}
