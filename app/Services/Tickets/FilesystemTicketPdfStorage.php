<?php

namespace App\Services\Tickets;

use App\Contracts\Tickets\TicketPdfStorageInterface;
use App\Models\Ticket;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

final class FilesystemTicketPdfStorage implements TicketPdfStorageInterface
{
    public function pathForTicket(Ticket $ticket, int $version): string
    {
        return "tickets/pdf/{$ticket->id}/v{$version}.pdf";
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function get(string $path): string
    {
        return $this->disk()->get($path);
    }

    public function put(string $path, string $contents): void
    {
        $this->disk()->put($path, $contents);
    }

    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('tickets.pdf.disk', 'local'));
    }
}
