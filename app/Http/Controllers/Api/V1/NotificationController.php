<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(Request $request)
    {
        $paginator = $this->notificationService->list($request->user(), (int) $request->integer('per_page', 15));

        return api_response(true, 'Notifications fetched successfully.', [
            'items' => NotificationResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function markAsRead(string $id)
    {
        $this->notificationService->markAsRead(request()->user(), $id);

        return api_response(true, 'Notification marked as read.');
    }
}
