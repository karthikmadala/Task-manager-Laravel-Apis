<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    public function index()
    {
        info('DashboardController index');
        $stats = $this->dashboardService->getStats(request()->user());

        return api_response(true, 'Dashboard statistics fetched successfully.', $stats);
    }
}
