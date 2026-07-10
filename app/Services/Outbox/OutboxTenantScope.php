<?php

namespace App\Services\Outbox;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Models\OutboxEvent;
use Closure;

/**
 * Runs outbox consumer logic with tenant context bound from the event's venue_id.
 */
final class OutboxTenantScope
{
    public function __construct(
        private TenantContextInterface $tenantContext,
    ) {}

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runForEvent(OutboxEvent $event, Closure $callback): mixed
    {
        $previousVenueId = $this->tenantContext->getVenueId();
        $previousSource = $this->tenantContext->getSource();

        if ($event->venue_id !== null) {
            $this->tenantContext->bind((int) $event->venue_id, 'outbox_worker');
        }

        try {
            return $callback();
        } finally {
            if ($previousVenueId !== null) {
                $this->tenantContext->bind($previousVenueId, $previousSource ?? 'restored');
            } else {
                $this->tenantContext->clear();
            }
        }
    }
}
