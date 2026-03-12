<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * Register a new user.
     *
     * @param  \App\Http\Requests\RegisterRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        $user = $this->authService->register(
            (string) $request->name,
            (string) $request->email,
            (string) $request->password,
        );

        return $this->success([
            'user' => $user,
        ], 'User registered successfully', 201);
    }

    /**
     * Authenticate a user and return a token.
     *
     * @param  \App\Http\Requests\LoginRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $loginResult = $this->authService->login(
            (string) $request->email,
            (string) $request->password,
        );

        if (!$loginResult) {
            return $this->error('Invalid credentials', 401);
        }

        return $this->success([
            'access_token' => $loginResult['token'],
            'token_type' => 'Bearer',
            'user' => $loginResult['user'],
        ], 'Login successful');
    }
}
