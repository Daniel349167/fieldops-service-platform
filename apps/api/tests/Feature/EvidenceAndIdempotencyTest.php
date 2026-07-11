<?php

namespace Tests\Feature;

use App\Enums\WorkOrderStatus;
use App\Models\ApiIdempotencyKey;
use App\Models\Evidence;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EvidenceAndIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_idempotent_request_replays_original_response_without_duplicate_side_effects(): void
    {
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create();
        Sanctum::actingAs($technician, $technician->tokenAbilities());
        $payload = $this->evidencePayload();

        $first = $this->withHeader('Idempotency-Key', 'evidence-mobile-001')
            ->postJson("/api/v1/work-orders/{$order->id}/evidences", $payload)
            ->assertCreated();
        $reorderedPayload = array_reverse($payload, true);
        $reorderedPayload['metadata'] = array_reverse($payload['metadata'], true);
        $second = $this->withHeader('Idempotency-Key', 'evidence-mobile-001')
            ->postJson("/api/v1/work-orders/{$order->id}/evidences", $reorderedPayload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('evidences', 1);
        $this->assertDatabaseCount('api_idempotency_keys', 1);
        $this->assertSame(1, $order->events()->where('type', 'evidence_added')->count());
    }

    public function test_reusing_idempotency_key_for_different_payload_is_rejected(): void
    {
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create();
        Sanctum::actingAs($technician, $technician->tokenAbilities());

        $this->withHeader('Idempotency-Key', 'evidence-mobile-002')
            ->postJson("/api/v1/work-orders/{$order->id}/evidences", $this->evidencePayload())
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'evidence-mobile-002')
            ->postJson("/api/v1/work-orders/{$order->id}/evidences", $this->evidencePayload('different.jpg'))
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_conflict');

        $this->assertDatabaseCount('evidences', 1);
    }

    public function test_in_progress_idempotency_reservation_blocks_duplicate_side_effect(): void
    {
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create();
        Sanctum::actingAs($technician, $technician->tokenAbilities());
        $payload = $this->evidencePayload();
        $canonicalPayload = $payload;
        ksort($canonicalPayload);
        ksort($canonicalPayload['metadata']);

        ApiIdempotencyKey::query()->create([
            'user_id' => $technician->id,
            'key' => 'evidence-mobile-in-progress',
            'method' => 'POST',
            'path' => "api/v1/work-orders/{$order->id}/evidences",
            'request_hash' => hash('sha256', json_encode($canonicalPayload, JSON_THROW_ON_ERROR)),
            'expires_at' => now()->addMinute(),
        ]);

        $this->withHeader('Idempotency-Key', 'evidence-mobile-in-progress')
            ->postJson("/api/v1/work-orders/{$order->id}/evidences", $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_in_progress');

        $this->assertDatabaseCount('evidences', 0);
    }

    public function test_technician_only_deletes_evidence_they_uploaded(): void
    {
        $technician = User::factory()->technician()->create();
        $other = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create();
        $ownEvidence = Evidence::factory()->create([
            'work_order_id' => $order->id,
            'uploaded_by' => $technician->id,
        ]);
        $otherEvidence = Evidence::factory()->create([
            'work_order_id' => $order->id,
            'uploaded_by' => $other->id,
        ]);
        Sanctum::actingAs($technician, $technician->tokenAbilities());

        $this->deleteJson("/api/v1/work-orders/{$order->id}/evidences/{$otherEvidence->id}", ['version' => 1])
            ->assertForbidden();
        $this->deleteJson("/api/v1/work-orders/{$order->id}/evidences/{$ownEvidence->id}", ['version' => 1])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('evidences', ['id' => $otherEvidence->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('evidences', ['id' => $ownEvidence->id, 'version' => 2]);
    }

    public function test_nested_evidence_must_belong_to_work_order(): void
    {
        $admin = User::factory()->admin()->create();
        $firstOrder = WorkOrder::factory()->create();
        $secondOrder = WorkOrder::factory()->create();
        $evidence = Evidence::factory()->create(['work_order_id' => $secondOrder->id]);
        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $this->deleteJson("/api/v1/work-orders/{$firstOrder->id}/evidences/{$evidence->id}", ['version' => 1])
            ->assertNotFound();
    }

    public function test_evidence_payload_and_version_are_validated(): void
    {
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create();
        Sanctum::actingAs($technician, $technician->tokenAbilities());

        $this->postJson("/api/v1/work-orders/{$order->id}/evidences", [
            'file_name' => 'malware.exe',
            'mime_type' => 'application/octet-stream',
            'size_bytes' => 50_000_000,
            'storage_path' => 'invalid/malware.exe',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['mime_type', 'size_bytes']);

        $evidence = Evidence::factory()->create([
            'work_order_id' => $order->id,
            'uploaded_by' => $technician->id,
        ]);
        $evidence->increment('version');

        $this->deleteJson("/api/v1/work-orders/{$order->id}/evidences/{$evidence->id}", ['version' => 1])
            ->assertConflict()
            ->assertJsonPath('code', 'version_conflict')
            ->assertJsonPath('errors.current', 2);
    }

    public function test_evidence_cannot_be_added_to_closed_orders(): void
    {
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create(['status' => WorkOrderStatus::Cancelled]);
        Sanctum::actingAs($technician, $technician->tokenAbilities());

        $this->postJson("/api/v1/work-orders/{$order->id}/evidences", $this->evidencePayload())
            ->assertUnprocessable()
            ->assertJsonPath('code', 'work_order_closed');
    }

    public function test_evidence_on_closed_order_is_immutable(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $order = WorkOrder::factory()->assigned($technician)->create(['status' => WorkOrderStatus::Completed]);
        $evidence = Evidence::factory()->create([
            'work_order_id' => $order->id,
            'uploaded_by' => $technician->id,
        ]);
        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $this->deleteJson("/api/v1/work-orders/{$order->id}/evidences/{$evidence->id}", ['version' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'closed_order_evidence_locked');

        $this->assertDatabaseHas('evidences', ['id' => $evidence->id, 'deleted_at' => null]);
    }

    private function evidencePayload(string $fileName = 'installation.jpg'): array
    {
        return [
            'file_name' => $fileName,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 250000,
            'storage_path' => 'orders/evidence/'.$fileName,
            'checksum' => hash('sha256', $fileName),
            'captured_at' => now()->toIso8601String(),
            'metadata' => ['source' => 'mobile', 'offline' => true],
        ];
    }
}
