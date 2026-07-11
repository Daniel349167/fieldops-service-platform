<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw new ApiException('The provided credentials are invalid.', 'invalid_credentials', 401);
        }

        $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 43200));
        $token = $user->createToken(
            $request->validated('device_name', 'fieldops-client'),
            $user->tokenAbilities(),
            $expiresAt,
        )->plainTextToken;

        return response()->json(['data' => [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => (new UserResource($user))->resolve($request),
        ]]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['data' => ['message' => 'Token revoked.']]);
    }
}
