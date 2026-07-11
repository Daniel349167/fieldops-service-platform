<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EvidenceController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TechnicianController;
use App\Http\Controllers\Api\V1\WorkOrderController;
use App\Http\Controllers\Api\V1\WorkOrderTimelineController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('technicians', TechnicianController::class)->middleware('abilities:users:read');

        Route::middleware('abilities:work-orders:read')->group(function (): void {
            Route::get('dashboard', DashboardController::class);
            Route::get('work-orders', [WorkOrderController::class, 'index']);
            Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show'])->withTrashed();
            Route::get('work-orders/{workOrder}/evidences', [EvidenceController::class, 'index']);
            Route::get('work-orders/{workOrder}/timeline', WorkOrderTimelineController::class);
        });

        Route::middleware('abilities:work-orders:write')->group(function (): void {
            Route::post('work-orders', [WorkOrderController::class, 'store'])->middleware('idempotent');
            Route::patch('work-orders/{workOrder}', [WorkOrderController::class, 'update']);
            Route::delete('work-orders/{workOrder}', [WorkOrderController::class, 'destroy']);
        });

        Route::middleware('ability:work-orders:write,work-orders:execute')->group(function (): void {
            Route::post('work-orders/{workOrder}/transition', [WorkOrderController::class, 'transition'])->middleware('idempotent');
            Route::post('work-orders/{workOrder}/evidences', [EvidenceController::class, 'store'])->middleware('idempotent');
            Route::delete('work-orders/{workOrder}/evidences/{evidence}', [EvidenceController::class, 'destroy']);
        });

        Route::get('sync', SyncController::class)->middleware('abilities:sync:read');
    });
});
