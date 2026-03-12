<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_endpoint_delegates_to_auth_service(): void
    {
        $user = User::factory()->make([
            'id' => 123,
            'name' => 'Teste',
            'email' => 'teste@example.com',
        ]);

        $authServiceMock = Mockery::mock(AuthService::class);
        $authServiceMock->shouldReceive('register')
            ->once()
            ->with('Teste', 'teste@example.com', 'password123')
            ->andReturn($user);

        $this->app->instance(AuthService::class, $authServiceMock);

        $response = $this->postJson('/api/register', [
            'name' => 'Teste',
            'email' => 'teste@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'User registered successfully')
            ->assertJsonPath('data.user.email', 'teste@example.com');
    }

    public function test_login_endpoint_returns_error_when_auth_service_fails(): void
    {
        $authServiceMock = Mockery::mock(AuthService::class);
        $authServiceMock->shouldReceive('login')
            ->once()
            ->with('teste@example.com', 'wrong-password')
            ->andReturn(null);

        $this->app->instance(AuthService::class, $authServiceMock);

        $response = $this->postJson('/api/login', [
            'email' => 'teste@example.com',
            'password' => 'wrong-password',
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ]);
    }
}
