<?php

namespace App\Services\Payments\Data;

use App\Models\User;

/**
 * `PaymentInstructionService::createInstructions()` input (Batch 7.6).
 */
readonly class CreatePaymentInstructionsData
{
    public function __construct(
        public int $orderId,
        public string $provider,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
