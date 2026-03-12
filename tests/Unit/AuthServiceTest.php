<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_with_hashed_password(): void
    {
        $service = new AuthService();

        $user = $service->register('Alice', 'alice@example.com', 'password123');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'alice@example.com',
        ]);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_login_returns_token_and_user_when_credentials_are_valid(): void
    {
        $service = new AuthService();
        $user = User::factory()->create([
            'email' => 'bob@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $result = $service->login('bob@example.com', 'secret123');

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result['user']->id);
        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
    }

    public function test_login_returns_null_when_credentials_are_invalid(): void
    {
        $service = new AuthService();
        User::factory()->create([
            'email' => 'carol@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $result = $service->login('carol@example.com', 'wrong-password');

        $this->assertNull($result);
    }
}
