<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkOrderStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvidenceRequest;
use App\Http\Resources\EvidenceResource;
use App\Models\Evidence;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvidenceController extends Controller
{
    public function index(Request $request, WorkOrder $workOrder)
    {
        $this->authorize('view', $workOrder);

        return EvidenceResource::collection($workOrder->evidences()->latest()->paginate(20));
    }

    public function store(StoreEvidenceRequest $request, WorkOrder $workOrder)
    {
        $this->authorize('addEvidence', $workOrder);
        if (in_array($workOrder->status, [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)) {
            throw new ApiException('Evidence cannot be added to a closed work order.', 'work_order_closed', 422);
        }

        $evidence = DB::transaction(function () use ($request, $workOrder): Evidence {
            $evidence = $workOrder->evidences()->create([
                ...$request->validated(),
                'uploaded_by' => $request->user()->id,
                'version' => 1,
            ]);
            $workOrder->events()->create([
                'actor_id' => $request->user()->id,
                'type' => 'evidence_added',
                'metadata' => ['evidence_id' => $evidence->id, 'file_name' => $evidence->file_name],
            ]);

            return $evidence;
        });

        return (new EvidenceResource($evidence))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, WorkOrder $workOrder, Evidence $evidence): JsonResponse
    {
        if ($evidence->work_order_id !== $workOrder->id) {
            abort(404);
        }
        $this->authorize('deleteEvidence', [$workOrder, $evidence]);
        if (in_array($workOrder->status, [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)) {
            throw new ApiException('Evidence on a closed work order is immutable.', 'closed_order_evidence_locked', 422);
        }
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        DB::transaction(function () use ($evidence, $validated, $request, $workOrder): void {
            $locked = Evidence::query()->lockForUpdate()->findOrFail($evidence->id);
            if ($locked->version !== (int) $validated['version']) {
                throw new ApiException('The evidence was modified by another client.', 'version_conflict', 409, [
                    'expected' => (int) $validated['version'], 'current' => $locked->version,
                ]);
            }
            $locked->version++;
            $locked->save();
            $locked->delete();
            $workOrder->events()->create([
                'actor_id' => $request->user()->id,
                'type' => 'evidence_deleted',
                'metadata' => ['evidence_id' => $locked->id, 'version' => $locked->version],
            ]);
        });

        return response()->json(['data' => ['id' => $evidence->id, 'deleted' => true, 'version' => $evidence->version + 1]]);
    }
}
