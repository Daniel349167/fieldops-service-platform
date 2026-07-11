<?php

namespace Tests\Feature;

use App\Enums\WorkOrderStatus;
use App\Models\Evidence;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_assign_unassign_and_optimistic_locking(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $created = $this->postJson('/api/v1/work-orders', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.version', 1);
        $orderId = $created->json('data.id');

        $this->assertDatabaseHas('work_order_events', [
            'work_order_id' => $orderId,
            'type' => 'created',
            'to_status' => 'pending',
        ]);

        $this->patchJson("/api/v1/work-orders/{$orderId}", [
            'version' => 1,
            'assigned_technician_id' => $technician->id,
        ])->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.assigned_technician.id', $technician->id);

        $this->patchJson("/api/v1/work-orders/{$orderId}", [
            'version' => 2,
            'assigned_technician_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.version', 3);

        $this->patchJson("/api/v1/work-orders/{$orderId}", [
            'version' => 2,
            'title' => 'Stale write',
        ])->assertConflict()
            ->assertJsonPath('code', 'version_conflict')
            ->assertJsonPath('errors.current', 3);

        $event = WorkOrder::findOrFail($orderId)->events()->where('type', 'assignment_changed')->latest()->firstOrFail();
        $this->assertArrayHasKey('previous_assigned_technician_id', $event->metadata);
        $this->assertArrayHasKey('assigned_technician_id', $event->metadata);
    }

    public function test_active_order_cannot_be_unassigned(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->inProgress($technician)->create();
        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $this->patchJson("/api/v1/work-orders/{$order->id}", [
            'version' => 1,
            'assigned_technician_id' => null,
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'active_order_requires_technician');

        $this->assertDatabaseHas('work_orders', [
            'id' => $order->id,
            'assigned_technician_id' => $technician->id,
            'status' => WorkOrderStatus::InProgress->value,
            'version' => 1,
        ]);
    }

    public function test_technician_advances_assigned_order_and_needs_evidence_to_complete(): void
    {
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create();
        Sanctum::actingAs($technician, $technician->tokenAbilities());

        $this->transition($order, 'en_route', 1)->assertOk()->assertJsonPath('data.version', 2);
        $this->transition($order, 'in_progress', 2)->assertOk()->assertJsonPath('data.version', 3);
        $this->transition($order, 'completed', 3)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'evidence_required');

        $this->postJson("/api/v1/work-orders/{$order->id}/evidences", $this->evidencePayload())
            ->assertCreated();
        $this->transition($order, 'completed', 3)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.version', 4);

        $this->postJson("/api/v1/work-orders/{$order->id}/evidences", $this->evidencePayload('after-close.jpg'))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'work_order_closed');
    }

    public function test_technician_cannot_cancel_or_roll_back_an_order(): void
    {
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create();
        Sanctum::actingAs($technician, $technician->tokenAbilities());

        $this->postJson("/api/v1/work-orders/{$order->id}/transition", [
            'to_status' => 'cancelled',
            'version' => 1,
            'note' => 'Customer unavailable.',
        ])->assertForbidden()
            ->assertJsonPath('code', 'forbidden_status_transition');
    }

    public function test_admin_cancellation_requires_a_note(): void
    {
        $admin = User::factory()->admin()->create();
        $order = WorkOrder::factory()->create();
        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $this->postJson("/api/v1/work-orders/{$order->id}/transition", [
            'to_status' => 'cancelled',
            'version' => 1,
        ])->assertUnprocessable()->assertJsonPath('code', 'cancellation_note_required');

        $this->postJson("/api/v1/work-orders/{$order->id}/transition", [
            'to_status' => 'cancelled',
            'version' => 1,
            'note' => 'Customer cancelled the visit.',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_deleting_order_also_soft_deletes_its_evidences(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create();
        $evidence = Evidence::factory()->create([
            'work_order_id' => $order->id,
            'uploaded_by' => $technician->id,
        ]);
        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $this->deleteJson("/api/v1/work-orders/{$order->id}", ['version' => 1])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('work_orders', ['id' => $order->id, 'version' => 2]);
        $this->assertSoftDeleted('evidences', ['id' => $evidence->id, 'version' => 2]);
        $this->assertDatabaseHas('work_order_events', ['work_order_id' => $order->id, 'type' => 'deleted']);
    }

    private function transition(WorkOrder $order, string $status, int $version)
    {
        return $this->postJson("/api/v1/work-orders/{$order->id}/transition", [
            'to_status' => $status,
            'version' => $version,
        ]);
    }

    private function payload(): array
    {
        return [
            'title' => 'Repair connectivity in warehouse',
            'description' => 'Diagnose and restore connectivity.',
            'customer' => ['name' => 'Acme SAC', 'phone' => '+51 999 000 111'],
            'address' => ['line' => 'Av. Industrial 450', 'district' => 'Ate', 'city' => 'Lima'],
            'priority' => 'urgent',
        ];
    }

    private function evidencePayload(string $fileName = 'network-rack.jpg'): array
    {
        return [
            'file_name' => $fileName,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 120000,
            'storage_path' => 'orders/evidence/'.$fileName,
            'checksum' => hash('sha256', $fileName),
            'captured_at' => now()->toIso8601String(),
            'metadata' => ['latitude' => -12.0464, 'longitude' => -77.0428],
        ];
    }
}
