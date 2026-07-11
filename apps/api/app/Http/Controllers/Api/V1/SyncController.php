<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EvidenceResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\Evidence;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SyncController extends Controller
{
    private const RESOURCE_ORDER = [
        'work_order' => 0,
        'evidence' => 1,
        'access_revocation' => 2,
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['sometimes', 'date'],
            'cursor' => ['sometimes', 'string', 'max:1024'],
            'limit' => ['sometimes', 'integer', 'between:1,500'],
        ]);

        $cursor = isset($validated['cursor']) ? $this->decodeCursor($validated['cursor']) : null;
        $since = isset($validated['since'])
            ? CarbonImmutable::parse($validated['since'])
            : CarbonImmutable::create(1970, 1, 1, 0, 0, 0, 'UTC');
        $syncAt = $cursor
            ? CarbonImmutable::parse($cursor['until'])
            : CarbonImmutable::now();
        $limit = $validated['limit'] ?? 200;

        if (! $cursor && $since->greaterThan($syncAt)) {
            throw ValidationException::withMessages(['since' => 'The since timestamp cannot be in the future.']);
        }

        $workQuery = WorkOrder::withTrashed()->with('assignedTechnician');
        if (! $request->user()->isAdmin()) {
            $workQuery->where('assigned_technician_id', $request->user()->id);
        }
        $this->applyWindow($workQuery, 'work_order', $since, $syncAt, $cursor);
        $workRows = $workQuery->orderBy('updated_at')->orderBy('id')->limit($limit + 1)->get();

        $accessibleWorkOrders = WorkOrder::withTrashed()->select('id');
        if (! $request->user()->isAdmin()) {
            $accessibleWorkOrders->where('assigned_technician_id', $request->user()->id);
        }
        $evidenceQuery = Evidence::withTrashed()->whereIn('work_order_id', $accessibleWorkOrders);
        $this->applyWindow($evidenceQuery, 'evidence', $since, $syncAt, $cursor);
        $evidenceRows = $evidenceQuery->orderBy('updated_at')->orderBy('id')->limit($limit + 1)->get();

        $revocationRows = collect();
        if (! $request->user()->isAdmin()) {
            $revocationQuery = WorkOrderEvent::query()
                ->with('workOrder')
                ->where('type', 'assignment_changed')
                ->where('metadata->previous_assigned_technician_id', $request->user()->id)
                ->whereHas('workOrder', function (Builder $query) use ($request): void {
                    $query->withTrashed()->where(function (Builder $query) use ($request): void {
                        $query->whereNull('assigned_technician_id')
                            ->orWhere('assigned_technician_id', '!=', $request->user()->id);
                    });
                });
            $this->applyWindow($revocationQuery, 'access_revocation', $since, $syncAt, $cursor);
            $revocationRows = $revocationQuery->orderBy('updated_at')->orderBy('id')->limit($limit + 1)->get();
        }

        $changes = $this->mergeChanges($workRows, $evidenceRows, $revocationRows);
        $hasMore = $changes->count() > $limit;
        $page = $changes->take($limit)->values();
        $last = $page->last();

        $workOrders = $page
            ->where('resource', 'work_order')
            ->filter(fn (array $change): bool => $change['model']->deleted_at === null)
            ->pluck('model')
            ->values();
        $evidences = $page
            ->where('resource', 'evidence')
            ->filter(fn (array $change): bool => $change['model']->deleted_at === null)
            ->pluck('model')
            ->values();
        $tombstones = $page
            ->filter(fn (array $change): bool => $change['resource'] === 'access_revocation' || $change['model']->deleted_at !== null)
            ->map(fn (array $change): array => $this->tombstone($change))
            ->values();

        $nextCursor = $hasMore && $last ? $this->encodeCursor($last, $syncAt) : null;
        $nextSince = $hasMore && $last ? $last['model']->updated_at : $syncAt;

        return response()->json([
            'data' => [
                'work_orders' => WorkOrderResource::collection($workOrders)->resolve($request),
                'evidences' => EvidenceResource::collection($evidences)->resolve($request),
                'tombstones' => $tombstones,
            ],
            'meta' => [
                'sync_at' => $syncAt->toIso8601String(),
                'next_since' => $nextSince->toIso8601String(),
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
            ],
        ]);
    }

    private function applyWindow(
        Builder $query,
        string $resource,
        CarbonImmutable $since,
        CarbonImmutable $syncAt,
        ?array $cursor,
    ): void {
        $query->where('updated_at', '<=', $syncAt);

        if (! $cursor) {
            $query->where('updated_at', '>', $since);

            return;
        }

        $cursorAt = CarbonImmutable::parse($cursor['at']);
        $resourceOrder = self::RESOURCE_ORDER[$resource];
        $cursorOrder = self::RESOURCE_ORDER[$cursor['resource']];

        $query->where(function (Builder $query) use ($cursorAt, $resourceOrder, $cursorOrder, $cursor): void {
            $query->where('updated_at', '>', $cursorAt)
                ->orWhere(function (Builder $query) use ($cursorAt, $resourceOrder, $cursorOrder, $cursor): void {
                    $query->where('updated_at', $cursorAt);

                    if ($resourceOrder === $cursorOrder) {
                        $query->where('id', '>', $cursor['id']);
                    } elseif ($resourceOrder < $cursorOrder) {
                        $query->whereRaw('1 = 0');
                    }
                });
        });
    }

    private function mergeChanges(Collection $workRows, Collection $evidenceRows, Collection $revocationRows): Collection
    {
        return $workRows->map(fn ($model): array => ['resource' => 'work_order', 'model' => $model])
            ->concat($evidenceRows->map(fn ($model): array => ['resource' => 'evidence', 'model' => $model]))
            ->concat($revocationRows->map(fn ($model): array => ['resource' => 'access_revocation', 'model' => $model]))
            ->sort(function (array $left, array $right): int {
                $timestampComparison = strcmp(
                    $left['model']->updated_at->format('Y-m-d H:i:s.u'),
                    $right['model']->updated_at->format('Y-m-d H:i:s.u'),
                );

                return $timestampComparison
                    ?: (self::RESOURCE_ORDER[$left['resource']] <=> self::RESOURCE_ORDER[$right['resource']])
                    ?: strcmp((string) $left['model']->id, (string) $right['model']->id);
            })
            ->values();
    }

    private function tombstone(array $change): array
    {
        if ($change['resource'] === 'access_revocation') {
            return [
                'resource' => 'work_order',
                'id' => $change['model']->work_order_id,
                'deleted_at' => $change['model']->created_at->toIso8601String(),
                'version' => (int) data_get($change['model']->metadata, 'version', 1),
                'reason' => 'access_revoked',
            ];
        }

        return [
            'resource' => $change['resource'],
            'id' => $change['model']->id,
            'deleted_at' => $change['model']->deleted_at->toIso8601String(),
            'version' => $change['model']->version,
            'reason' => 'deleted',
        ];
    }

    private function encodeCursor(array $change, CarbonImmutable $syncAt): string
    {
        $payload = json_encode([
            'at' => $change['model']->updated_at->toIso8601String(),
            'resource' => $change['resource'],
            'id' => (string) $change['model']->id,
            'until' => $syncAt->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    private function decodeCursor(string $cursor): array
    {
        $padding = strlen($cursor) % 4;
        $decoded = base64_decode(strtr($cursor.($padding ? str_repeat('=', 4 - $padding) : ''), '-_', '+/'), true);

        try {
            $payload = $decoded === false ? null : json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload)
                || ! isset($payload['at'], $payload['resource'], $payload['id'], $payload['until'])
                || ! isset(self::RESOURCE_ORDER[$payload['resource']])) {
                throw new \UnexpectedValueException;
            }
            CarbonImmutable::parse($payload['at']);
            CarbonImmutable::parse($payload['until']);

            return $payload;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['cursor' => 'The sync cursor is invalid or malformed.']);
        }
    }
}
