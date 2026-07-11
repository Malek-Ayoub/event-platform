<?php

namespace Tests\Support\Tickets;

use App\Contracts\Tickets\TicketPdfRendererInterface;

final class RecordingTicketPdfRenderer implements TicketPdfRendererInterface
{
    /** @var list<array{payload: array<string, mixed>, qr: string}> */
    public array $calls = [];

    public function render(array $snapshotPayload, string $qrPngBinary): string
    {
        $this->calls[] = [
            'payload' => $snapshotPayload,
            'qr' => $qrPngBinary,
        ];

        return '%PDF-recording-'.hash('sha256', json_encode($snapshotPayload).$qrPngBinary);
    }
}
