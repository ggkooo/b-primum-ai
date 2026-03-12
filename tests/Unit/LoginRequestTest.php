<?php

namespace Tests\Unit;

use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LoginRequestTest extends TestCase
{
    public function test_validation_passes_with_valid_payload(): void
    {
        $request = new LoginRequest();

        $validator = Validator::make([
            'email' => 'user@example.com',
            'password' => 'password123',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_validation_fails_when_fields_are_missing(): void
    {
        $request = new LoginRequest();

        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }
}
