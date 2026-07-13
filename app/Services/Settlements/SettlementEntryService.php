<?php

namespace App\Services\Settlements;

use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Models\SettlementEntry;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\TransactionRunner;
use Illuminate\Database\QueryException;

class SettlementEntryService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
    ) {}

    /**
     * Append an immutable row to the platform commission receivable ledger.
     *
     * balance_after is the outstanding commission owed by the venue to the platform.
     */
    public function append(AppendSettlementEntryData $data): SettlementEntry
    {
        return $this->transactionRunner->run(function () use ($data): SettlementEntry {
            $existing = SettlementEntry::query()
                ->where('reference_type', $data->referenceType)
                ->where('reference_id', $data->referenceId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $previousBalance = $this->latestVenueBalance($data->venueId);
            $balanceAfter = $this->applyDirection($previousBalance, $data->direction, $data->amount);

            try {
                return SettlementEntry::query()->create([
                    'venue_id' => $data->venueId,
                    'event_id' => $data->eventId,
                    'payment_transaction_id' => $data->paymentTransactionId,
                    'order_id' => $data->orderId,
                    'type' => $data->type,
                    'direction' => $data->direction,
                    'amount' => $this->formatAmount($data->amount),
                    'currency' => $data->currency,
                    'reference_type' => $data->referenceType,
                    'reference_id' => $data->referenceId,
                    'balance_after' => $balanceAfter,
                    'correlation_id' => $data->correlationId,
                    'metadata' => $data->metadata,
                    'occurred_at' => $data->occurredAt,
                ]);
            } catch (QueryException) {
                return SettlementEntry::query()
                    ->where('reference_type', $data->referenceType)
                    ->where('reference_id', $data->referenceId)
                    ->firstOrFail();
            }
        });
    }

    private function latestVenueBalance(int $venueId): string
    {
        $latest = SettlementEntry::query()
            ->where('venue_id', $venueId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('balance_after');

        return $this->formatAmount($latest ?? '0.00');
    }

    private function applyDirection(string $previousBalance, SettlementEntryDirection $direction, string $amount): string
    {
        $normalizedAmount = $this->formatAmount($amount);

        if ($direction === SettlementEntryDirection::Credit) {
            return bcadd($previousBalance, $normalizedAmount, 2);
        }

        return bcsub($previousBalance, $normalizedAmount, 2);
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
