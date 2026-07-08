<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordController extends BaseApiController
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink($request->toDto());

        return $this->respondPlainMessage('Password reset link sent.');
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->resetPassword($request->toDto());

        return $this->respondPlainMessage('Password reset successfully.');
    }

    public function change(ChangePasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->changePassword(
            $request->user(),
            $request->toDto(),
        );

        return $this->respondPlainMessage('Password changed successfully.');
    }
}
