<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\TicketType;
use App\Services\Events\Data\PublicEventCatalogItem;
use App\Services\PlatformSettings\PlatformSettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class PublishedEventCatalogService
{
    public function __construct(
        private readonly PlatformSettingService $platformSettingService,
    ) {}

    public function listPublished(int $perPage, string $sort): LengthAwarePaginator
    {
        $query = Event::query()
            ->published()
            ->with([
                'venue:id,name',
                'ticketTypes' => static fn ($relation) => $relation->select([
                    'id',
                    'event_id',
                    'price',
                    'quantity',
                    'quantity_sold',
                    'sale_start',
                    'sale_end',
                ]),
            ]);

        match ($sort) {
            'starts_at' => $query->orderBy('start_datetime'),
            default => $query->orderBy('start_datetime'),
        };

        $paginator = $query->paginate($perPage);
        $currency = $this->resolveCatalogCurrency();

        // starting_price is derived from the eager-loaded ticketTypes relation only — no per-event queries.
        $paginator->setCollection(
            $paginator->getCollection()->map(function (Event $event) use ($currency): PublicEventCatalogItem {
                return new PublicEventCatalogItem(
                    event: $event,
                    startingPriceAmount: $this->resolveStartingPrice($event),
                    currency: $currency,
                );
            }),
        );

        return $paginator;
    }

    /**
     * Lowest available ticket type price for catalog display.
     *
     * Returns null when no ticket types are currently on sale, when all are sold out,
     * or when the minimum price is zero (treated as a free event for public catalog).
     */
    public function resolveStartingPrice(Event $event): ?string
    {
        if (! $event->relationLoaded('ticketTypes')) {
            return null;
        }

        $now = now();
        $minimumPrice = null;

        foreach ($event->ticketTypes as $ticketType) {
            if (! $this->isTicketTypeAvailable($ticketType, $now)) {
                continue;
            }

            $price = (float) $ticketType->price;

            if ($price <= 0) {
                continue;
            }

            $minimumPrice = $minimumPrice === null ? $price : min($minimumPrice, $price);
        }

        if ($minimumPrice === null) {
            return null;
        }

        return $this->formatAmount($minimumPrice);
    }

    /**
     * Catalog currency is intentionally sourced from platform_settings.default_currency only.
     *
     * We do not read payment_accounts or settlement_entries here: those tables belong to
     * operational payment/settlement flows, may be unset for new venues, and this
     * unauthenticated public route must stay isolated from sensitive financial data.
     */
    private function resolveCatalogCurrency(): string
    {
        $settings = $this->platformSettingService->get()->settings ?? [];
        $currency = $settings['default_currency'] ?? null;

        return is_string($currency) && $currency !== ''
            ? strtoupper($currency)
            : 'USD';
    }

    private function isTicketTypeAvailable(TicketType $ticketType, Carbon $now): bool
    {
        if ($ticketType->quantity <= $ticketType->quantity_sold) {
            return false;
        }

        if ($ticketType->sale_start !== null && $ticketType->sale_start->gt($now)) {
            return false;
        }

        if ($ticketType->sale_end !== null && $ticketType->sale_end->lt($now)) {
            return false;
        }

        return true;
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
