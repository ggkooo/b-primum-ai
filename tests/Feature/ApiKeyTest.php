<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    /**
     * Test that a request without an API key is unauthorized.
     */
    public function test_request_without_api_key_fails(): void
    {
        $response = $this->getJson('/');

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthorized: Invalid or missing API Key']);
    }

    /**
     * Test that a request with an invalid API key is unauthorized.
     */
    public function test_request_with_invalid_api_key_fails(): void
    {
        $response = $this->getJson('/', [
            'X-API-KEY' => 'invalid-key-123'
        ]);

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthorized: Invalid or missing API Key']);
    }

    /**
     * Test that a request with a valid API key is successful.
     */
    public function test_request_with_valid_api_key_succeeds(): void
    {
        $apiKey = env('APP_API_KEY');
        
        $response = $this->getJson('/', [
            'X-API-KEY' => $apiKey
        ]);

        $response->assertStatus(200);
    }
}
