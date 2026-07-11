<?php

namespace App\Contracts\Tickets;

interface TicketPdfRendererInterface
{
    /**
     * @param  array<string, mixed>  $snapshotPayload
     */
    public function render(array $snapshotPayload, string $qrPngBinary): string;
}
