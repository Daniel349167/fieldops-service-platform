<?php

namespace Tests\Feature;

use App\Models\Evidence;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sync_cursor_paginates_globally_without_duplicates(): void
    {
        $admin = User::factory()->admin()->create();
        $firstOrder = WorkOrder::factory()->create();
        $evidence = Evidence::factory()->create([
            'work_order_id' => $firstOrder->id,
            'uploaded_by' => $admin->id,
        ]);
        $secondOrder = WorkOrder::factory()->create();
        $this->setUpdatedAt('work_orders', $firstOrder->id, '2026-07-10 12:00:01');
        $this->setUpdatedAt('evidences', $evidence->id, '2026-07-10 12:00:02');
        $this->setUpdatedAt('work_orders', $secondOrder->id, '2026-07-10 12:00:03');
        Carbon::setTestNow('2026-07-10 12:00:10');
        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $firstPage = $this->getJson('/api/v1/sync?'.http_build_query([
            'since' => '2026-07-10T12:00:00Z',
            'limit' => 2,
        ]))->assertOk()
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonStructure(['meta' => ['next_cursor']]);

        $firstIds = $this->changeIds($firstPage->json());
        $this->assertCount(2, $firstIds);

        $secondPage = $this->getJson('/api/v1/sync?'.http_build_query([
            'cursor' => $firstPage->json('meta.next_cursor'),
            'limit' => 2,
        ]))->assertOk()
            ->assertJsonPath('meta.has_more', false);

        $allIds = [...$firstIds, ...$this->changeIds($secondPage->json())];
        $this->assertCount(3, $allIds);
        $this->assertCount(3, array_unique($allIds));
        $this->assertEqualsCanonicalizing([$firstOrder->id, $evidence->id, $secondOrder->id], $allIds);
    }

    public function test_deletions_are_returned_as_versioned_tombstones(): void
    {
        Carbon::setTestNow('2026-07-10 13:00:00');
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create();
        $evidence = Evidence::factory()->create([
            'work_order_id' => $order->id,
            'uploaded_by' => $technician->id,
        ]);
        Sanctum::actingAs($admin, $admin->tokenAbilities());
        Carbon::setTestNow('2026-07-10 13:00:05');

        $this->deleteJson("/api/v1/work-orders/{$order->id}", ['version' => 1])->assertOk();
        Carbon::setTestNow('2026-07-10 13:00:10');

        $sync = $this->getJson('/api/v1/sync?'.http_build_query([
            'since' => '2026-07-10T13:00:01Z',
        ]))->assertOk();

        $sync->assertJsonFragment([
            'resource' => 'work_order',
            'id' => $order->id,
            'version' => 2,
            'reason' => 'deleted',
        ])->assertJsonFragment([
            'resource' => 'evidence',
            'id' => $evidence->id,
            'version' => 2,
            'reason' => 'deleted',
        ]);
        $sync->assertJsonCount(0, 'data.work_orders')->assertJsonCount(0, 'data.evidences');
    }

    public function test_reassignment_produces_access_revocation_for_previous_technician(): void
    {
        Carbon::setTestNow('2026-07-10 14:00:00');
        $admin = User::factory()->admin()->create();
        $firstTechnician = User::factory()->technician()->create();
        $secondTechnician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($firstTechnician)->create();

        Sanctum::actingAs($firstTechnician, $firstTechnician->tokenAbilities());
        $initialSync = $this->getJson('/api/v1/sync?since=2026-07-10T13%3A59%3A00Z')->assertOk();
        $since = $initialSync->json('meta.sync_at');

        Carbon::setTestNow('2026-07-10 14:00:05');
        Sanctum::actingAs($admin, $admin->tokenAbilities());
        $this->patchJson("/api/v1/work-orders/{$order->id}", [
            'version' => 1,
            'assigned_technician_id' => $secondTechnician->id,
        ])->assertOk();

        Carbon::setTestNow('2026-07-10 14:00:10');
        Sanctum::actingAs($firstTechnician, $firstTechnician->tokenAbilities());
        $this->getJson('/api/v1/sync?'.http_build_query(['since' => $since]))
            ->assertOk()
            ->assertJsonCount(0, 'data.work_orders')
            ->assertJsonFragment([
                'resource' => 'work_order',
                'id' => $order->id,
                'version' => 2,
                'reason' => 'access_revoked',
            ]);
    }

    public function test_technician_sync_never_exposes_other_technicians_data(): void
    {
        $technician = User::factory()->technician()->create();
        $other = User::factory()->technician()->create();
        $own = WorkOrder::factory()->assigned($technician)->create();
        $foreign = WorkOrder::factory()->assigned($other)->create();
        Evidence::factory()->create(['work_order_id' => $foreign->id, 'uploaded_by' => $other->id]);
        Sanctum::actingAs($technician, $technician->tokenAbilities());

        $response = $this->getJson('/api/v1/sync')->assertOk();
        $response->assertJsonCount(1, 'data.work_orders')
            ->assertJsonPath('data.work_orders.0.id', $own->id)
            ->assertJsonCount(0, 'data.evidences');
    }

    public function test_malformed_cursor_and_future_since_are_validation_errors(): void
    {
        Carbon::setTestNow('2026-07-10 15:00:00');
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $this->getJson('/api/v1/sync?cursor=not-a-cursor')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cursor');
        $this->getJson('/api/v1/sync?since=2026-07-11T15%3A00%3A00Z')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('since');
    }

    private function setUpdatedAt(string $table, string $id, string $timestamp): void
    {
        DB::table($table)->where('id', $id)->update([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function changeIds(array $payload): array
    {
        return [
            ...array_column($payload['data']['work_orders'], 'id'),
            ...array_column($payload['data']['evidences'], 'id'),
            ...array_column($payload['data']['tombstones'], 'id'),
        ];
    }
}
