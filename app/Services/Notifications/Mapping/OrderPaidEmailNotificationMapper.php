<?php

namespace App\Services\Notifications\Mapping;

use App\Models\Order;
use App\Services\Notifications\Data\NotificationMessage;
use InvalidArgumentException;

final class OrderPaidEmailNotificationMapper
{
    public function toMessage(Order $order): NotificationMessage
    {
        $order->loadMissing(['event', 'tickets']);

        $recipient = trim((string) $order->customer_email);

        if ($recipient === '') {
            throw new InvalidArgumentException('order.paid notification requires customer_email on the order.');
        }

        $total = (string) $order->total;

        return new NotificationMessage(
            channelKey: 'email',
            recipient: $recipient,
            templateSlug: 'order.paid',
            variables: [
                'customer_name' => (string) ($order->customer_name ?: 'Customer'),
                'event_name' => (string) ($order->event?->name ?? 'Event'),
                'order_number' => (string) $order->order_number,
                'ticket_count' => (string) $order->tickets->count(),
                'total' => $total,
                'amount' => $total,
                'ticket_download_url' => $this->ticketDownloadPlaceholder($order),
            ],
            venueId: (int) $order->venue_id,
        );
    }

    private function ticketDownloadPlaceholder(Order $order): string
    {
        $pattern = (string) config(
            'notifications.placeholders.ticket_download_url',
            '#pending-ticket-fulfillment',
        );

        return str_replace('{{order_number}}', (string) $order->order_number, $pattern);
    }
}
