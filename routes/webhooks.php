<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment Webhook Routes
|--------------------------------------------------------------------------
|
| Placeholder for provider webhooks. Phase 14 will attach
| VerifyWebhookSignature middleware (§56) before any business processing.
|
*/

Route::prefix('webhooks')->group(function (): void {
    Route::post('/{provider}', function (string $provider) {
        return response()->json([
            'message' => 'webhook route placeholder',
            'provider' => $provider,
        ], 501);
    })->name('webhooks.receive');
});
