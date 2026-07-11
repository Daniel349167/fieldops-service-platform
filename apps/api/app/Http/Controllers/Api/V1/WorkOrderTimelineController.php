<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkOrderTimelineController extends Controller
{
    public function __invoke(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);
        $events = $workOrder->events()->with('actor')->get()->map(fn ($event) => [
            'id' => $event->id,
            'type' => $event->type,
            'from_status' => $event->from_status,
            'to_status' => $event->to_status,
            'note' => $event->note,
            'metadata' => $event->metadata ?? (object) [],
            'actor' => $event->actor ? ['id' => $event->actor->id, 'name' => $event->actor->name] : null,
            'created_at' => $event->created_at->toIso8601String(),
        ]);

        return response()->json(['data' => $events]);
    }
}
