<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register($request->validated());

        return api_response(true, 'Registration successful.', [
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_expires_at' => $result['token_expires_at'],
        ], null, 201);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->validated());
        info($request);
        
        return api_response(true, 'Login successful.', [
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_expires_at' => $result['token_expires_at'],
        ]);
    }

    public function logout()
    {
        $this->authService->logout(request()->user());

        return api_response(true, 'Logged out successfully.');
    }
}
