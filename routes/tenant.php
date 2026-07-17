<?php

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrganizerDashboardController;
use App\Http\Controllers\Api\OrganizerReportController;
use App\Http\Controllers\Api\OrganizerSettlementController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\PromoCodeController;
use App\Http\Controllers\Api\PublicEventController;
use App\Http\Controllers\Api\PublicOrderController;
use App\Http\Controllers\Api\PublicPaymentController;
use App\Http\Controllers\Api\TaxRateController;
use App\Http\Controllers\Api\TicketCheckInController;
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

Route::prefix('public')->group(function (): void {
    Route::get('events', [PublicEventController::class, 'index'])->name('public.events.index');
    Route::get('events/{slug}', [PublicEventController::class, 'show'])->name('public.events.show');
    Route::post('orders', [PublicOrderController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('public.orders.store');
    Route::post('orders/{orderNumber}/payment-instructions', [PublicPaymentController::class, 'instructions'])
        ->middleware('throttle:10,1')
        ->name('public.orders.payment-instructions');
    Route::post('orders/{orderNumber}/payment-verification', [PublicPaymentController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('public.orders.payment-verification');
});

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

        Route::get('products', [ProductController::class, 'index'])->name('tenant.products.index');
        Route::post('products', [ProductController::class, 'store'])->name('tenant.products.store');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('tenant.products.show');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('tenant.products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('tenant.products.destroy');

        Route::get('products/{product}/variants', [ProductVariantController::class, 'index'])->name('tenant.products.variants.index');
        Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->name('tenant.products.variants.store');
        Route::get('product-variants/{productVariant}', [ProductVariantController::class, 'show'])->name('tenant.product-variants.show');
        Route::put('product-variants/{productVariant}', [ProductVariantController::class, 'update'])->name('tenant.product-variants.update');
        Route::delete('product-variants/{productVariant}', [ProductVariantController::class, 'destroy'])->name('tenant.product-variants.destroy');

        Route::get('coupons', [CouponController::class, 'index'])->name('tenant.coupons.index');
        Route::post('coupons', [CouponController::class, 'store'])->name('tenant.coupons.store');
        Route::get('coupons/{coupon}', [CouponController::class, 'show'])->name('tenant.coupons.show');
        Route::put('coupons/{coupon}', [CouponController::class, 'update'])->name('tenant.coupons.update');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('tenant.coupons.destroy');

        Route::get('promo-codes', [PromoCodeController::class, 'index'])->name('tenant.promo-codes.index');
        Route::post('promo-codes', [PromoCodeController::class, 'store'])->name('tenant.promo-codes.store');
        Route::get('promo-codes/{promoCode}', [PromoCodeController::class, 'show'])->name('tenant.promo-codes.show');
        Route::put('promo-codes/{promoCode}', [PromoCodeController::class, 'update'])->name('tenant.promo-codes.update');
        Route::delete('promo-codes/{promoCode}', [PromoCodeController::class, 'destroy'])->name('tenant.promo-codes.destroy');

        Route::get('orders', [OrderController::class, 'index'])->name('tenant.orders.index');
        Route::post('orders', [OrderController::class, 'store'])->name('tenant.orders.store');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('tenant.orders.show');

        Route::get('payments', [PaymentController::class, 'index'])->name('tenant.payments.index');
        Route::post('payments', [PaymentController::class, 'store'])->name('tenant.payments.store');
        Route::get('payments/{paymentTransaction}', [PaymentController::class, 'show'])->name('tenant.payments.show');
        Route::post('payments/{paymentTransaction}/verify', [PaymentController::class, 'verify'])->name('tenant.payments.verify');

        Route::get('tax-rates', [TaxRateController::class, 'index'])->name('tenant.tax-rates.index');
        Route::post('tax-rates', [TaxRateController::class, 'store'])->name('tenant.tax-rates.store');
        Route::get('tax-rates/{taxRate}', [TaxRateController::class, 'show'])->name('tenant.tax-rates.show');
        Route::put('tax-rates/{taxRate}', [TaxRateController::class, 'update'])->name('tenant.tax-rates.update');
        Route::delete('tax-rates/{taxRate}', [TaxRateController::class, 'destroy'])->name('tenant.tax-rates.destroy');

        Route::post('tickets/check-in', [TicketCheckInController::class, 'store'])->name('tenant.tickets.check-in');

        Route::prefix('organizer/settlement')->group(function (): void {
            Route::get('summary', [OrganizerSettlementController::class, 'summary'])
                ->name('tenant.organizer.settlement.summary');
            Route::get('entries', [OrganizerSettlementController::class, 'entries'])
                ->name('tenant.organizer.settlement.entries');
        });

        Route::get('organizer/reports', [OrganizerReportController::class, 'show'])
            ->name('tenant.organizer.reports.show');

        Route::get('organizer/dashboard', [OrganizerDashboardController::class, 'show'])
            ->name('tenant.organizer.dashboard.show');
    });
});
