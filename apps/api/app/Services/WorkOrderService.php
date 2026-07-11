<?php

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WorkOrderService
{
    public function create(array $attributes, User $actor): WorkOrder
    {
        return DB::transaction(function () use ($attributes, $actor): WorkOrder {
            $attributes['status'] = empty($attributes['assigned_technician_id'])
                ? WorkOrderStatus::Pending->value
                : WorkOrderStatus::Assigned->value;
            $attributes['version'] = 1;

            $workOrder = WorkOrder::query()->create($attributes);
            $workOrder->events()->create([
                'actor_id' => $actor->id,
                'type' => 'created',
                'to_status' => $workOrder->status->value,
                'metadata' => ['version' => 1],
            ]);

            return $workOrder->load('assignedTechnician');
        });
    }

    public function update(WorkOrder $workOrder, array $attributes, int $expectedVersion, User $actor): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $attributes, $expectedVersion, $actor): WorkOrder {
            $locked = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            $this->assertVersion($locked, $expectedVersion);

            $fromStatus = $locked->status;
            $previousAssignee = $locked->assigned_technician_id;
            $locked->fill(Arr::except($attributes, ['version']));
            $assigneeChanged = $previousAssignee !== $locked->assigned_technician_id;

            if ($assigneeChanged) {
                if ($locked->assigned_technician_id === null) {
                    if (in_array($locked->status, [WorkOrderStatus::EnRoute, WorkOrderStatus::InProgress], true)) {
                        throw new ApiException(
                            'An active work order cannot be left without a technician.',
                            'active_order_requires_technician',
                            422,
                        );
                    }

                    if ($locked->status === WorkOrderStatus::Assigned) {
                        $locked->status = WorkOrderStatus::Pending;
                    }
                } elseif ($locked->status === WorkOrderStatus::Pending) {
                    $locked->status = WorkOrderStatus::Assigned;
                }
            }

            $locked->version++;
            $locked->save();

            $locked->events()->create([
                'actor_id' => $actor->id,
                'type' => $assigneeChanged ? 'assignment_changed' : 'updated',
                'from_status' => $fromStatus->value,
                'to_status' => $locked->status->value,
                'metadata' => [
                    'version' => $locked->version,
                    ...($assigneeChanged ? [
                        'previous_assigned_technician_id' => $previousAssignee,
                        'assigned_technician_id' => $locked->assigned_technician_id,
                    ] : []),
                ],
            ]);

            return $locked->load('assignedTechnician');
        });
    }

    public function transition(WorkOrder $workOrder, WorkOrderStatus $target, int $expectedVersion, User $actor, ?string $note): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $target, $expectedVersion, $actor, $note): WorkOrder {
            $locked = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            $this->assertVersion($locked, $expectedVersion);
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw new ApiException(
                    "Transition from {$from->value} to {$target->value} is not allowed.",
                    'invalid_status_transition',
                    422,
                    ['allowed' => array_map(fn (WorkOrderStatus $status) => $status->value, $from->allowedTransitions())],
                );
            }

            if (! $actor->isAdmin() && ! $this->technicianCanTransition($from, $target)) {
                throw new ApiException(
                    'Technicians may only advance their assigned work through execution states.',
                    'forbidden_status_transition',
                    403,
                );
            }

            if ($target === WorkOrderStatus::Assigned && $locked->assigned_technician_id === null) {
                throw new ApiException('Assign a technician before moving the order to assigned.', 'technician_required', 422);
            }

            if ($target === WorkOrderStatus::Completed && ! $locked->evidences()->exists()) {
                throw new ApiException(
                    'At least one evidence is required before completing a work order.',
                    'evidence_required',
                    422,
                );
            }

            if ($target === WorkOrderStatus::Cancelled && blank($note)) {
                throw new ApiException(
                    'A cancellation note is required.',
                    'cancellation_note_required',
                    422,
                );
            }

            $locked->status = $target;
            $locked->version++;
            $locked->save();
            $locked->events()->create([
                'actor_id' => $actor->id,
                'type' => 'status_changed',
                'from_status' => $from->value,
                'to_status' => $target->value,
                'note' => $note,
                'metadata' => ['version' => $locked->version],
            ]);

            return $locked->load('assignedTechnician');
        });
    }

    public function delete(WorkOrder $workOrder, int $expectedVersion, User $actor): void
    {
        DB::transaction(function () use ($workOrder, $expectedVersion, $actor): void {
            $locked = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            $this->assertVersion($locked, $expectedVersion);
            $locked->version++;
            $locked->save();
            $locked->events()->create([
                'actor_id' => $actor->id,
                'type' => 'deleted',
                'from_status' => $locked->status->value,
                'metadata' => ['version' => $locked->version],
            ]);

            $locked->evidences()->get()->each(function ($evidence): void {
                $evidence->version++;
                $evidence->save();
                $evidence->delete();
            });
            $locked->delete();
        });
    }

    private function assertVersion(WorkOrder $workOrder, int $expected): void
    {
        if ($workOrder->version !== $expected) {
            throw new ApiException(
                'The work order was modified by another client. Refresh and retry.',
                'version_conflict',
                409,
                ['expected' => $expected, 'current' => $workOrder->version],
            );
        }
    }

    private function technicianCanTransition(WorkOrderStatus $from, WorkOrderStatus $target): bool
    {
        return match ($from) {
            WorkOrderStatus::Assigned => $target === WorkOrderStatus::EnRoute,
            WorkOrderStatus::EnRoute => $target === WorkOrderStatus::InProgress,
            WorkOrderStatus::InProgress => $target === WorkOrderStatus::Completed,
            default => false,
        };
    }
}
