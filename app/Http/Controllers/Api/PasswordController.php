<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Auth\ChangePasswordDTO;
use App\DTOs\Auth\ResetPasswordDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink($request->validated('email'));

        return response()->json([
            'message' => 'Password reset link sent.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->resetPassword(ResetPasswordDTO::fromArray($request->validated()));

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    public function change(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->passwordResetService->changePassword(
            $user,
            ChangePasswordDTO::fromArray($request->validated()),
        );

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
