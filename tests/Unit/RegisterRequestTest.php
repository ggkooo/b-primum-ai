<?php

namespace Tests\Unit;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RegisterRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        $request = new RegisterRequest();

        $validator = Validator::make([
            'name' => 'Giordano',
            'email' => 'giordano@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_validation_fails_for_duplicate_email(): void
    {
        User::factory()->create(['email' => 'used@example.com']);
        $request = new RegisterRequest();

        $validator = Validator::make([
            'name' => 'Giordano',
            'email' => 'used@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }
}
