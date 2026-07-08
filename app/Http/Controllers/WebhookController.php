<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Webhooks\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends BaseApiController
{
    public function __construct(
        private readonly WebhookService $webhookService,
    ) {}

    public function receive(string $provider, Request $request): JsonResponse
    {
        $result = $this->webhookService->receive($provider, $request);

        return response()->json([
            'message' => $result->duplicate ? 'Webhook already processed.' : 'Webhook processed.',
            'data' => $result->toArray(),
        ], $result->duplicate ? 200 : 202);
    }
}
