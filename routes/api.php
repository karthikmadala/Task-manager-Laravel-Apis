<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'check.token.expiry', 'store.api.session'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);
            Route::patch('change-password', [ProfileController::class, 'changePassword']);
        });
    });

    Route::middleware(['auth:sanctum', 'check.token.expiry', 'store.api.session'])->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::prefix('tasks')->group(function (): void {
            Route::get('/', [TaskController::class, 'index']);
            Route::post('/', [TaskController::class, 'store']);
            Route::get('{task}', [TaskController::class, 'show']);
            Route::put('{task}', [TaskController::class, 'update']);
            Route::delete('{task}', [TaskController::class, 'destroy']);
            Route::patch('{task}/complete', [TaskController::class, 'markCompleted']);
        });

        Route::prefix('notifications')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index']);
            Route::patch('{id}/read', [NotificationController::class, 'markAsRead']);
        });

        Route::middleware('role:admin')->group(function (): void {
            Route::get('admin/health', fn () => api_response(true, 'Admin route accessible.'));
        });
    });
});
