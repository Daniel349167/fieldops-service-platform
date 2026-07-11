<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkOrderResource;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $base = WorkOrder::query();
        if (! $request->user()->isAdmin()) {
            $base->where('assigned_technician_id', $request->user()->id);
        }

        $byStatus = [];
        foreach (WorkOrderStatus::cases() as $status) {
            $byStatus[$status->value] = (clone $base)->where('status', $status->value)->count();
        }
        $openStatuses = [
            WorkOrderStatus::Pending->value, WorkOrderStatus::Assigned->value,
            WorkOrderStatus::EnRoute->value, WorkOrderStatus::InProgress->value,
        ];
        $recent = (clone $base)->with('assignedTechnician')->latest('updated_at')->limit(5)->get();

        return response()->json(['data' => [
            'summary' => [
                'total' => array_sum($byStatus),
                'open' => (clone $base)->whereIn('status', $openStatuses)->count(),
                'completed' => $byStatus[WorkOrderStatus::Completed->value],
                'cancelled' => $byStatus[WorkOrderStatus::Cancelled->value],
                'urgent_open' => (clone $base)->where('priority', 'urgent')->whereIn('status', $openStatuses)->count(),
                'due_today' => (clone $base)->whereDate('scheduled_at', today())->whereIn('status', $openStatuses)->count(),
            ],
            'by_status' => $byStatus,
            'recent_work_orders' => WorkOrderResource::collection($recent)->resolve($request),
        ]]);
    }
}
