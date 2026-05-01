<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function show(): JsonResponse
    {
        return api_response(true, 'Profile fetched successfully.', [
            'user' => new UserResource(request()->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return api_response(true, 'Profile updated successfully.', [
            'user' => new UserResource($user->refresh()),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->validated()['current_password'],
            $request->validated()['password']
        );

        return api_response(true, 'Password changed successfully.');
    }
}
