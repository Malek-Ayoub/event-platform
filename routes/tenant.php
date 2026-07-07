<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant (Subdomain) Routes
|--------------------------------------------------------------------------
|
| Tenant-scoped routes resolved via TenantMiddleware (subdomain path only).
|
*/

Route::prefix('tenant')->group(function (): void {
    Route::get('/ping', function (\App\Domain\Tenancy\Contracts\TenantContextInterface $tenantContext) {
        return response()->json([
            'message' => 'tenant route placeholder',
            'venue_id' => $tenantContext->getVenueId(),
            'source' => $tenantContext->getSource(),
        ]);
    })->name('tenant.ping');

    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'tenantLogin'])->name('tenant.auth.login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('user', [AuthController::class, 'tenantUser'])->name('tenant.auth.user');
            Route::post('logout', [AuthController::class, 'logout'])->name('tenant.auth.logout');
        });
    });
});
