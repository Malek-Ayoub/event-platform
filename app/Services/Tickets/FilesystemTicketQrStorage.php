<?php

namespace App\Services\Tickets;

use App\Contracts\Tickets\TicketQrStorageInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

final class FilesystemTicketQrStorage implements TicketQrStorageInterface
{
    public function pathForToken(string $qrToken): string
    {
        return "tickets/qr/{$qrToken}.png";
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
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
        return Storage::disk((string) config('tickets.qr.disk', 'local'));
    }
}
