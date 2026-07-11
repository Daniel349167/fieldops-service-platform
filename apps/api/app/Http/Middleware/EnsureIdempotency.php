<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\ApiIdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '') {
            return $next($request);
        }

        if (mb_strlen($key) > 100) {
            throw new ApiException('The Idempotency-Key header is too long.', 'invalid_idempotency_key', 422);
        }

        $hash = hash('sha256', json_encode($this->canonicalize($request->all()), JSON_THROW_ON_ERROR));
        $stored = $this->findStored($request, $key);

        if ($stored?->expires_at->isPast()) {
            $stored->delete();
            $stored = null;
        }

        if ($stored) {
            return $this->replayOrReject($stored, $request, $hash);
        }

        try {
            $reservation = ApiIdempotencyKey::query()->create([
                'user_id' => $request->user()->id,
                'key' => $key,
                'method' => $request->method(),
                'path' => $request->path(),
                'request_hash' => $hash,
                'expires_at' => now()->addDay(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $reservation = $this->findStored($request, $key);
            if (! $reservation) {
                throw new ApiException('The request could not reserve its idempotency key.', 'idempotency_unavailable', 503);
            }

            return $this->replayOrReject($reservation, $request, $hash);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $reservation->delete();

            throw $exception;
        }

        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            $reservation->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getData(true),
            ]);
        } else {
            $reservation->delete();
        }

        return $response;
    }

    private function findStored(Request $request, string $key): ?ApiIdempotencyKey
    {
        return ApiIdempotencyKey::query()
            ->where('user_id', $request->user()->id)
            ->where('key', $key)
            ->first();
    }

    private function replayOrReject(ApiIdempotencyKey $stored, Request $request, string $hash): Response
    {
        if ($stored->method !== $request->method()
            || $stored->path !== $request->path()
            || $stored->request_hash !== $hash) {
            throw new ApiException(
                'This idempotency key was already used for a different request.',
                'idempotency_conflict',
                409,
            );
        }

        if ($stored->response_status === null) {
            throw new ApiException(
                'A request with this idempotency key is already being processed.',
                'idempotency_in_progress',
                409,
            );
        }

        return response()->json($stored->response_body, $stored->response_status)
            ->header('Idempotency-Replayed', 'true');
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
