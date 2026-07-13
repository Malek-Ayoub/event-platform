<?php

namespace App\Services\Commissions;

use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Exceptions\Settlements\NoOutstandingCommissionException;
use App\Exceptions\Settlements\PaymentExceedsOutstandingCommissionException;
use App\Models\CommissionPayment;
use App\Models\PaymentAccount;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\Venue;
use App\Services\ActivityLogService;
use App\Services\Commissions\Data\RecordCommissionPaymentData;
use App\Services\Commissions\Data\RecordCommissionPaymentResult;
use App\Services\OutboxService;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\SettlementEntryService;
use App\Services\TransactionRunner;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class CommissionPaymentService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private SettlementEntryService $settlementEntryService,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function recordPayment(RecordCommissionPaymentData $data): RecordCommissionPaymentResult
    {
        return $this->transactionRunner->run(function () use ($data): RecordCommissionPaymentResult {
            Venue::query()->whereKey($data->venueId)->firstOrFail();

            if ($data->paymentAccountId !== null) {
                $account = PaymentAccount::query()
                    ->withoutGlobalScope(BelongsToVenueScope::class)
                    ->whereKey($data->paymentAccountId)
                    ->firstOrFail();

                if ((int) $account->venue_id !== $data->venueId) {
                    throw new InvalidArgumentException(
                        "Payment account {$account->id} does not belong to venue {$data->venueId}.",
                    );
                }
            }

            $outstanding = $this->settlementEntryService->outstandingBalanceForVenue($data->venueId);
            $requestedAmount = $this->formatAmount($data->amount);

            if (bccomp($outstanding, '0.00', 2) !== 1) {
                throw NoOutstandingCommissionException::forVenue($data->venueId);
            }

            if (bccomp($requestedAmount, $outstanding, 2) === 1) {
                throw PaymentExceedsOutstandingCommissionException::forVenue(
                    $data->venueId,
                    $requestedAmount,
                    $outstanding,
                );
            }

            $ledgerCurrency = $this->settlementEntryService->ledgerCurrencyForVenue($data->venueId);

            if ($ledgerCurrency !== null && $ledgerCurrency !== $data->currency) {
                throw new InvalidArgumentException(
                    "Commission payment currency {$data->currency} does not match ledger currency {$ledgerCurrency}.",
                );
            }

            try {
                $payment = CommissionPayment::query()
                    ->withoutGlobalScope(BelongsToVenueScope::class)
                    ->create([
                        'venue_id' => $data->venueId,
                        'payment_account_id' => $data->paymentAccountId,
                        'amount' => $requestedAmount,
                        'currency' => $data->currency,
                        'payment_method' => $data->paymentMethod,
                        'reference_number' => $data->referenceNumber,
                        'received_at' => $data->receivedAt,
                        'received_by_user_id' => $data->receivedBy->id,
                        'notes' => $data->notes,
                        'metadata' => $data->metadata,
                    ]);
            } catch (QueryException $exception) {
                throw $exception;
            }

            $settlementEntry = $this->settlementEntryService->append(new AppendSettlementEntryData(
                venueId: $data->venueId,
                eventId: null,
                orderId: null,
                type: SettlementEntryType::CommissionPaid,
                direction: SettlementEntryDirection::Debit,
                amount: $requestedAmount,
                currency: $data->currency,
                referenceType: 'commission_payment',
                referenceId: (int) $payment->id,
                occurredAt: $data->receivedAt,
                paymentTransactionId: null,
                correlationId: "commission_payment:{$payment->id}",
                metadata: [
                    'payment_method' => $data->paymentMethod->value,
                    'reference_number' => $data->referenceNumber,
                    'received_by_user_id' => $data->receivedBy->id,
                    'payment_account_id' => $data->paymentAccountId,
                ],
            ));

            $this->activityLogService->record(
                actor: $data->receivedBy,
                entity: $payment,
                action: 'recorded',
                newValues: [
                    'venue_id' => $payment->venue_id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'payment_method' => $payment->payment_method->value,
                    'reference_number' => $payment->reference_number,
                    'received_at' => $payment->received_at?->toIso8601String(),
                    'outstanding_after' => $settlementEntry->balance_after,
                ],
                changedFields: [
                    'venue_id',
                    'amount',
                    'currency',
                    'payment_method',
                    'reference_number',
                    'received_at',
                ],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'commission.payment_recorded',
                aggregate: $payment,
                payload: [
                    'commission_payment_id' => $payment->id,
                    'venue_id' => $payment->venue_id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'payment_method' => $payment->payment_method->value,
                    'reference_number' => $payment->reference_number,
                    'received_at' => $payment->received_at?->toIso8601String(),
                    'outstanding_after' => $settlementEntry->balance_after,
                ],
                venueId: $data->venueId,
            );

            return new RecordCommissionPaymentResult($payment, $settlementEntry);
        });
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
