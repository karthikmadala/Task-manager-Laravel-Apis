<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Wallet\MetaMaskNonceRequest;
use App\Http\Requests\Wallet\MetaMaskVerifyRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly WalletService $walletService,
    ) {}

    /** POST /api/v1/auth/register */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return api_response(true, 'Registration successful.', [
            'user'             => new UserResource($result['user']),
            'token'            => $result['token'],
            'token_expires_at' => $result['token_expires_at'],
        ], null, 201);
    }

    /** POST /api/v1/auth/login */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return api_response(true, 'Login successful.', [
            'user'             => new UserResource($result['user']),
            'token'            => $result['token'],
            'token_expires_at' => $result['token_expires_at'],
        ]);
    }

    /** POST /api/v1/auth/refresh  (authenticated) */
    public function refresh(): JsonResponse
    {
        $result = $this->authService->refresh(auth()->user());

        return api_response(true, 'Token refreshed.', [
            'token'            => $result['token'],
            'token_expires_at' => $result['token_expires_at'],
        ]);
    }

    /** POST /api/v1/auth/logout  (authenticated) */
    public function logout(): JsonResponse
    {
        $this->authService->logout(auth()->user());

        return api_response(true, 'Logged out successfully.');
    }

    // ─── MetaMask login flow (public — no Sanctum required) ──────────────────

    /** POST /api/v1/auth/metamask/nonce */
    public function metamaskNonce(MetaMaskNonceRequest $request): JsonResponse
    {
        $nonce = $this->walletService->generateLoginNonce($request->validated('address'));

        return api_response(true, 'Sign this nonce with MetaMask.', ['nonce' => $nonce]);
    }

    /** POST /api/v1/auth/metamask/verify */
    public function metamaskVerify(MetaMaskVerifyRequest $request): JsonResponse
    {
        $user = $this->walletService->verifyLoginSignature(
            $request->validated('address'),
            $request->validated('signature')
        );

        $result = $this->authService->issueTokenPublic($user);

        return api_response(true, 'MetaMask login successful.', [
            'user'             => new UserResource($user),
            'token'            => $result['token'],
            'token_expires_at' => $result['token_expires_at'],
        ]);
    }
}
