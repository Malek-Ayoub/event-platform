<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Http\Requests\Auth\CurrentUserRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutAllRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendEmailVerificationRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\Auth\ApiTokenResource;
use App\Http\Resources\Auth\CurrentUserResource;
use App\Services\Auth\AuthService;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\JsonResponse;

class AuthController extends BaseApiController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly TenantContextInterface $tenantContext,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->toDto());

        return $this->respondCreated(new ApiTokenResource($result));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->toDto());

        return $this->respondResource(new ApiTokenResource($result));
    }

    public function tenantLogin(LoginRequest $request): JsonResponse
    {
        $venueId = $this->tenantContext->requireVenueId();
        $result = $this->authService->login($request->toDto(), $venueId);

        return $this->respondResource(
            (new ApiTokenResource($result))->additional(['venue_id' => $venueId]),
        );
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->respondPlainMessage('Logged out successfully.');
    }

    public function logoutAll(LogoutAllRequest $request): JsonResponse
    {
        $this->authService->logoutAll($request->user());

        return $this->respondPlainMessage('All sessions revoked successfully.');
    }

    public function user(CurrentUserRequest $request): JsonResponse
    {
        return $this->respondResource(new CurrentUserResource($request->user()));
    }

    public function tenantUser(CurrentUserRequest $request): JsonResponse
    {
        return $this->respondResource(
            (new CurrentUserResource($request->user()))->additional([
                'venue_id' => $this->tenantContext->requireVenueId(),
            ]),
        );
    }

    public function sendVerificationEmail(SendEmailVerificationRequest $request): JsonResponse
    {
        $message = $this->emailVerificationService->sendNotification($request->user());

        return $this->respondPlainMessage($message);
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $message = $this->emailVerificationService->verify($request->user());

        return $this->respondPlainMessage($message);
    }
}
