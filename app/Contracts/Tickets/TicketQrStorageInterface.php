<?php

namespace App\Contracts\Tickets;

interface TicketQrStorageInterface
{
    public function pathForToken(string $qrToken): string;

    public function exists(string $path): bool;

    /**
     * @param  non-empty-string  $contents
     */
    public function put(string $path, string $contents): void;

    public function delete(string $path): void;
}
