<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_and_conversation_routes_require_sanctum_authentication(): void
    {
        $headers = [
            'X-API-KEY' => env('APP_API_KEY'),
        ];

        $chatResponse = $this->postJson('/api/chat', [
            'message' => 'Teste',
        ], $headers);

        $listResponse = $this->getJson('/api/conversations', $headers);
        $showResponse = $this->getJson('/api/conversations/1', $headers);
        $deleteResponse = $this->deleteJson('/api/conversations/1', [], $headers);

        $chatResponse->assertStatus(401);
        $listResponse->assertStatus(401);
        $showResponse->assertStatus(401);
        $deleteResponse->assertStatus(401);
    }
}
