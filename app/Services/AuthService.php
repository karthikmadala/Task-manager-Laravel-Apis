<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'zip' => $data['zip'] ?? null,
            'consent_checkbox' => $data['consent_checkbox'] ?? false,
            'role' => 'user',
        ]);

        $token = $user->createToken(
            $data['device_name'] ?? 'api-token',
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 60))
        );

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
            'token_expires_at' => optional($token->accessToken->expires_at)?->toISOString(),
        ];
    }

    public function login(array $data): array
    {
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken(
            $data['device_name'] ?? 'api-token',
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 60))
        );

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
            'token_expires_at' => optional($token->accessToken->expires_at)?->toISOString(),
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token) {
            $token->forceFill(['expires_at' => now()])->save();
        }
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is invalid.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($newPassword)])->save();
    }
}
