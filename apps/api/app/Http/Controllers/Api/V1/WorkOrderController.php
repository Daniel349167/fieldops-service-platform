<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Http\Requests\TransitionWorkOrderRequest;
use App\Http\Requests\UpdateWorkOrderRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkOrderController extends Controller
{
    public function __construct(private readonly WorkOrderService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', WorkOrder::class);
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(WorkOrderStatus::class)],
            'priority' => ['sometimes', Rule::enum(WorkOrderPriority::class)],
            'assigned_technician_id' => ['sometimes', 'integer'], 'q' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $query = WorkOrder::query()->with('assignedTechnician');
        if (! $request->user()->isAdmin()) {
            $query->where('assigned_technician_id', $request->user()->id);
        }

        $query->when($validated['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($validated['priority'] ?? null, fn ($q, $value) => $q->where('priority', $value))
            ->when($validated['assigned_technician_id'] ?? null, fn ($q, $value) => $q->where('assigned_technician_id', $value))
            ->when($validated['q'] ?? null, function ($q, $value): void {
                $q->where(fn ($inner) => $inner->where('title', 'like', "%{$value}%")
                    ->orWhere('customer_name', 'like', "%{$value}%")
                    ->orWhere('address_line', 'like', "%{$value}%"));
            });

        return WorkOrderResource::collection($query->latest('updated_at')->paginate($validated['per_page'] ?? 20));
    }

    public function store(StoreWorkOrderRequest $request)
    {
        $this->authorize('create', WorkOrder::class);
        $workOrder = $this->service->create($this->flatten($request->validated()), $request->user());

        return (new WorkOrderResource($workOrder))->response()->setStatusCode(201);
    }

    public function show(Request $request, WorkOrder $workOrder): WorkOrderResource
    {
        $this->authorize('view', $workOrder);

        return new WorkOrderResource($workOrder->load('assignedTechnician'));
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): WorkOrderResource
    {
        $this->authorize('update', $workOrder);
        $data = $request->validated();
        if (! $request->user()->isAdmin() && array_key_exists('assigned_technician_id', $data)) {
            throw new ApiException('Only administrators may reassign a work order.', 'forbidden', 403);
        }

        return new WorkOrderResource($this->service->update(
            $workOrder, $this->flatten($data), (int) $data['version'], $request->user()
        ));
    }

    public function transition(TransitionWorkOrderRequest $request, WorkOrder $workOrder): WorkOrderResource
    {
        $this->authorize('transition', $workOrder);
        $data = $request->validated();

        return new WorkOrderResource($this->service->transition(
            $workOrder, WorkOrderStatus::from($data['to_status']), (int) $data['version'], $request->user(), $data['note'] ?? null
        ));
    }

    public function destroy(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('delete', $workOrder);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $this->service->delete($workOrder, (int) $validated['version'], $request->user());

        return response()->json(['data' => ['id' => $workOrder->id, 'deleted' => true]]);
    }

    private function flatten(array $data): array
    {
        $flat = collect($data)->except(['customer', 'address'])->all();
        foreach (['name' => 'customer_name', 'phone' => 'customer_phone', 'email' => 'customer_email'] as $input => $column) {
            if (array_key_exists($input, $data['customer'] ?? [])) {
                $flat[$column] = $data['customer'][$input];
            }
        }
        foreach (['line' => 'address_line', 'district' => 'district', 'city' => 'city', 'latitude' => 'latitude', 'longitude' => 'longitude'] as $input => $column) {
            if (array_key_exists($input, $data['address'] ?? [])) {
                $flat[$column] = $data['address'][$input];
            }
        }

        return $flat;
    }
}
