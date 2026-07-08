<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks')->group(function (): void {
    Route::post('/{provider}', [WebhookController::class, 'receive'])
        ->name('webhooks.receive');
});
