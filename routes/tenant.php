<?php

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\TicketTypeController;
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
    Route::get('/ping', function (TenantContextInterface $tenantContext) {
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

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('events', [EventController::class, 'index'])->name('tenant.events.index');
        Route::post('events', [EventController::class, 'store'])->name('tenant.events.store');
        Route::get('events/{event}', [EventController::class, 'show'])->name('tenant.events.show');
        Route::put('events/{event}', [EventController::class, 'update'])->name('tenant.events.update');
        Route::delete('events/{event}', [EventController::class, 'destroy'])->name('tenant.events.destroy');
        Route::post('events/{event}/publish', [EventController::class, 'publish'])->name('tenant.events.publish');
        Route::post('events/{event}/archive', [EventController::class, 'archive'])->name('tenant.events.archive');

        Route::get('categories', [CategoryController::class, 'index'])->name('tenant.categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('tenant.categories.store');
        Route::get('categories/{category}', [CategoryController::class, 'show'])->name('tenant.categories.show');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('tenant.categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('tenant.categories.destroy');

        Route::get('events/{event}/ticket-types', [TicketTypeController::class, 'index'])->name('tenant.events.ticket-types.index');
        Route::post('events/{event}/ticket-types', [TicketTypeController::class, 'store'])->name('tenant.events.ticket-types.store');
        Route::get('ticket-types/{ticketType}', [TicketTypeController::class, 'show'])->name('tenant.ticket-types.show');
        Route::put('ticket-types/{ticketType}', [TicketTypeController::class, 'update'])->name('tenant.ticket-types.update');
        Route::delete('ticket-types/{ticketType}', [TicketTypeController::class, 'destroy'])->name('tenant.ticket-types.destroy');
    });
});
