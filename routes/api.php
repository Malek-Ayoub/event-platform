<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AdminSettlementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommissionPaymentController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\PlatformSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform API Routes
|--------------------------------------------------------------------------
|
| Global API routes (authentication, platform admin). Tenant-scoped routes
| live in routes/tenant.php. Third-party integrations use routes/api_clients.php.
|
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
    ]);
})->name('api.health');

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:login')
        ->name('auth.register');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::post('password/forgot', [PasswordController::class, 'forgot'])
        ->middleware('throttle:login')
        ->name('auth.password.forgot');
    Route::post('password/reset', [PasswordController::class, 'reset'])
        ->middleware('throttle:login')
        ->name('auth.password.reset');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
        Route::get('user', [AuthController::class, 'user'])->name('auth.user');

        Route::post('password/change', [PasswordController::class, 'change'])->name('auth.password.change');

        Route::post('email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
            ->name('verification.send');

        Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
    });
});

Route::middleware('auth:sanctum')->prefix('platform')->group(function (): void {
    Route::get('settings', [PlatformSettingController::class, 'show'])->name('platform.settings.show');
    Route::put('settings', [PlatformSettingController::class, 'update'])->name('platform.settings.update');
});

Route::middleware('auth:sanctum')->prefix('admin')->group(function (): void {
    Route::post('commission-payments', [CommissionPaymentController::class, 'store'])
        ->name('admin.commission-payments.store');

    Route::get('settlement/venues', [AdminSettlementController::class, 'venues'])
        ->name('admin.settlement.venues.index');
    Route::get('venues/{venue}/settlement', [AdminSettlementController::class, 'venueSettlement'])
        ->name('admin.venues.settlement.show');

    Route::get('reports', [AdminReportController::class, 'show'])
        ->name('admin.reports.show');

    Route::get('dashboard', [AdminDashboardController::class, 'show'])
        ->name('admin.dashboard.show');
});
