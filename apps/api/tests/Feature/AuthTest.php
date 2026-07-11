<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_use_the_token_and_revoke_it(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@example.test',
            'password' => 'a-secure-password',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'a-secure-password',
            'device_name' => 'test-suite',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user']]);

        $plainTextToken = $login->json('data.token');
        $storedToken = $user->tokens()->sole();
        $this->assertSame($user->tokenAbilities(), $storedToken->abilities);
        $this->assertNotNull($storedToken->expires_at);

        $this->withToken($plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->withToken($plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.message', 'Token revoked.');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $storedToken->id]);
    }

    public function test_login_rejects_invalid_credentials_without_leaking_account_existence(): void
    {
        User::factory()->technician()->create(['email' => 'tech@example.test', 'password' => 'correct-password']);

        foreach (['tech@example.test', 'missing@example.test'] as $email) {
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertUnauthorized()
                ->assertExactJson(['message' => 'The provided credentials are invalid.', 'code' => 'invalid_credentials']);
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_routes_require_a_bearer_token(): void
    {
        $this->getJson('/api/v1/work-orders')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }
}
