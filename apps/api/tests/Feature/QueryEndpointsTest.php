<?php

namespace Tests\Feature;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QueryEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_scoped_and_reports_operational_totals(): void
    {
        $technician = User::factory()->technician()->create();
        $other = User::factory()->technician()->create();
        WorkOrder::factory()->assigned($technician)->create([
            'scheduled_at' => today()->setTime(10, 0),
            'priority' => WorkOrderPriority::High,
        ]);
        WorkOrder::factory()->assigned($technician)->create([
            'status' => WorkOrderStatus::Completed,
            'scheduled_at' => today()->subDay(),
        ]);
        WorkOrder::factory()->assigned($other)->create(['priority' => WorkOrderPriority::Urgent]);
        Sanctum::actingAs($technician, $technician->tokenAbilities());

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.open', 1)
            ->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.summary.urgent_open', 0)
            ->assertJsonPath('data.summary.due_today', 1)
            ->assertJsonCount(2, 'data.recent_work_orders');
    }

    public function test_order_list_validates_and_combines_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $expected = WorkOrder::factory()->create([
            'title' => 'Repair Acme router',
            'customer_name' => 'Acme Logistics',
            'priority' => WorkOrderPriority::High,
            'status' => WorkOrderStatus::Pending,
        ]);
        WorkOrder::factory()->create([
            'customer_name' => 'Another company',
            'priority' => WorkOrderPriority::Low,
            'status' => WorkOrderStatus::Completed,
        ]);
        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $this->getJson('/api/v1/work-orders?'.http_build_query([
            'status' => 'pending',
            'priority' => 'high',
            'q' => 'Acme',
            'per_page' => 10,
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expected->id)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/work-orders?status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_timeline_exposes_actor_and_transition_audit_data(): void
    {
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create();
        $order->events()->create([
            'actor_id' => $technician->id,
            'type' => 'status_changed',
            'from_status' => 'assigned',
            'to_status' => 'en_route',
            'note' => 'Leaving the workshop.',
            'metadata' => ['version' => 2],
        ]);
        Sanctum::actingAs($technician, $technician->tokenAbilities());

        $this->getJson("/api/v1/work-orders/{$order->id}/timeline")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'status_changed')
            ->assertJsonPath('data.0.actor.id', $technician->id)
            ->assertJsonPath('data.0.metadata.version', 2);
    }
}
