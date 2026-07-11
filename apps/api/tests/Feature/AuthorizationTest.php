<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_only_reads_orders_assigned_to_them(): void
    {
        $technician = User::factory()->technician()->create();
        $other = User::factory()->technician()->create();
        $assigned = WorkOrder::factory()->assigned($technician)->create();
        $someoneElses = WorkOrder::factory()->assigned($other)->create();
        WorkOrder::factory()->create();

        Sanctum::actingAs($technician, $technician->tokenAbilities());

        $this->getJson('/api/v1/work-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id);

        $this->getJson("/api/v1/work-orders/{$someoneElses->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_technician_cannot_use_administrative_endpoints(): void
    {
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create();
        Sanctum::actingAs($technician, $technician->tokenAbilities());

        $this->getJson('/api/v1/technicians')->assertForbidden();
        $this->postJson('/api/v1/work-orders', $this->workOrderPayload())->assertForbidden();
        $this->patchJson("/api/v1/work-orders/{$order->id}", ['version' => 1, 'title' => 'Changed'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/work-orders/{$order->id}", ['version' => 1])->assertForbidden();

        $this->assertDatabaseHas('work_orders', ['id' => $order->id, 'title' => $order->title, 'version' => 1]);
    }

    public function test_admin_can_list_technicians_and_create_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $this->getJson('/api/v1/technicians')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $technician->id);

        $this->postJson('/api/v1/work-orders', $this->workOrderPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
    }

    private function workOrderPayload(): array
    {
        return [
            'title' => 'Install access point',
            'description' => 'Replace the old unit and validate coverage.',
            'customer' => ['name' => 'Acme SAC', 'email' => 'ops@acme.test'],
            'address' => ['line' => 'Av. Principal 123', 'district' => 'Miraflores', 'city' => 'Lima'],
            'priority' => 'high',
        ];
    }
}
