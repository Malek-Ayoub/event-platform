<?php

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Third-Party API Client Routes
|--------------------------------------------------------------------------
|
| Integration routes resolved via ApiClientMiddleware (independent from subdomain).
|
*/

Route::prefix('partner')->group(function (): void {
    Route::get('/ping', function (TenantContextInterface $tenantContext) {
        return response()->json([
            'message' => 'api client route placeholder',
            'venue_id' => $tenantContext->getVenueId(),
            'source' => $tenantContext->getSource(),
            'api_client_id' => $tenantContext->getApiClientId(),
            'scopes' => $tenantContext->getScopes(),
        ]);
    })->name('api_clients.ping');
});
