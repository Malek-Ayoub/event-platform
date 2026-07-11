<?php

namespace App\Services\Tickets\Renderers;

use App\Contracts\Tickets\TicketPdfRendererInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Pure snapshot + QR renderer (Phase 8.3.3b.2).
 *
 * Must never read live Event, Venue, Order, TicketType, or Payment models.
 */
final class TicketPdfRenderer implements TicketPdfRendererInterface
{
    /**
     * @param  array<string, mixed>  $snapshotPayload
     */
    public function render(array $snapshotPayload, string $qrPngBinary): string
    {
        $html = $this->buildHtml($snapshotPayload, base64_encode($qrPngBinary));

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $snapshotPayload
     */
    private function buildHtml(array $snapshotPayload, string $qrBase64): string
    {
        $eventName = e((string) data_get($snapshotPayload, 'event.name', ''));
        $eventStartsAt = e((string) data_get($snapshotPayload, 'event.starts_at', ''));
        $venueName = e((string) data_get($snapshotPayload, 'venue.name', ''));
        $ticketTypeName = e((string) data_get($snapshotPayload, 'ticket_type.name', ''));
        $ticketTypeColor = e((string) data_get($snapshotPayload, 'ticket_type.color', ''));
        $holderName = e((string) data_get($snapshotPayload, 'holder.name', ''));
        $seatLabel = data_get($snapshotPayload, 'seat.label');
        $priceAmount = e((string) data_get($snapshotPayload, 'price.amount', ''));
        $priceCurrency = e((string) data_get($snapshotPayload, 'price.currency', ''));
        $ticketNumber = e((string) data_get($snapshotPayload, 'ticket.number', ''));
        $issuedAt = e((string) data_get($snapshotPayload, 'ticket.issued_at', ''));

        $seatRow = is_string($seatLabel) && $seatLabel !== ''
            ? '<tr><th>Seat</th><td>'.e($seatLabel).'</td></tr>'
            : '';

        $colorStyle = $ticketTypeColor !== ''
            ? "border-left: 6px solid {$ticketTypeColor};"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket {$ticketNumber}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; margin: 24px; }
        .card { border: 1px solid #d1d5db; border-radius: 8px; padding: 24px; {$colorStyle} }
        h1 { font-size: 24px; margin: 0 0 8px; }
        .subtitle { color: #4b5563; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { text-align: left; width: 140px; color: #6b7280; font-weight: normal; padding: 6px 0; }
        td { padding: 6px 0; font-weight: bold; }
        .qr { text-align: center; margin-top: 12px; }
        .qr img { width: 180px; height: 180px; }
        .ticket-number { font-size: 18px; letter-spacing: 0.04em; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{$eventName}</h1>
        <div class="subtitle">{$venueName} · {$eventStartsAt}</div>
        <table>
            <tr><th>Ticket</th><td class="ticket-number">{$ticketNumber}</td></tr>
            <tr><th>Type</th><td>{$ticketTypeName}</td></tr>
            <tr><th>Holder</th><td>{$holderName}</td></tr>
            {$seatRow}
            <tr><th>Price</th><td>{$priceAmount} {$priceCurrency}</td></tr>
            <tr><th>Issued</th><td>{$issuedAt}</td></tr>
        </table>
        <div class="qr">
            <img src="data:image/png;base64,{$qrBase64}" alt="Ticket QR code">
        </div>
    </div>
</body>
</html>
HTML;
    }
}
