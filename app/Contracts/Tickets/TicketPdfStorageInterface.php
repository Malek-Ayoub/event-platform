<?php

namespace App\Contracts\Tickets;

use App\Models\Ticket;

interface TicketPdfStorageInterface
{
    public function pathForTicket(Ticket $ticket, int $version): string;

    public function exists(string $path): bool;

    public function get(string $path): string;

    /**
     * @param  non-empty-string  $contents
     */
    public function put(string $path, string $contents): void;

    public function delete(string $path): void;
}
