<?php

namespace App\Contracts\Payments;

use App\DTOs\Payments\Gateway\VerifyTransactionRequest;
use App\DTOs\Payments\Gateway\VerifyTransactionResponse;

/**
 * External payment provider transaction-lookup contract (Batch 7.6 — Manual
 * Wallet Transfer, IMPLEMENTATION_ROADMAP.md §7.9.4). Replaces the hosted
 * checkout `PaymentGateway::initiate()` flow for providers (e.g. API Syria)
 * that only expose a transaction-lookup endpoint (`find_tx`).
 *
 * Implementations must not access the database or domain services.
 */
interface PaymentVerificationGateway
{
    public function provider(): string;

    public function verifyTransaction(VerifyTransactionRequest $request): VerifyTransactionResponse;
}
