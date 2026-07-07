<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\TokenResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TenantContextInterface $tenantContext,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register(RegisterDTO::fromArray($request->validated()));

        return (new TokenResource($result))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResource
    {
        $result = $this->authService->login(LoginDTO::fromArray($request->validated()));

        return new TokenResource($result);
    }

    public function tenantLogin(LoginRequest $request): JsonResource
    {
        $result = $this->authService->login(
            LoginDTO::fromArray($request->validated()),
            $this->tenantContext->requireVenueId(),
        );

        return (new TokenResource($result))->additional([
            'venue_id' => $this->tenantContext->requireVenueId(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authService->logout($user);

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authService->logoutAll($user);

        return response()->json([
            'message' => 'All sessions revoked successfully.',
        ]);
    }

    public function user(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user);
    }

    public function tenantUser(Request $request): JsonResource
    {
        /** @var User $user */
        $user = $request->user();

        return (new UserResource($user))->additional([
            'venue_id' => $this->tenantContext->requireVenueId(),
        ]);
    }

    public function sendVerificationEmail(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification link sent.',
        ]);
    }

    public function verifyEmail(EmailVerificationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email verified successfully.',
        ]);
    }
}
