<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_configured_web_origin_can_preflight_api_requests(): void
    {
        $this->call('OPTIONS', '/api/v1/auth/login', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type,idempotency-key',
        ])->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Methods');
    }

    public function test_unknown_web_origin_is_not_granted_cors_access(): void
    {
        $this->call('OPTIONS', '/api/v1/auth/login', server: [
            'HTTP_ORIGIN' => 'https://untrusted.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertNoContent()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
