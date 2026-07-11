<?php

namespace App\Providers;

use App\Contracts\Tickets\QrImageGeneratorInterface;
use App\Contracts\Tickets\TicketPdfRendererInterface;
use App\Contracts\Tickets\TicketPdfStorageInterface;
use App\Contracts\Tickets\TicketQrStorageInterface;
use App\Services\Tickets\EndroidQrImageGenerator;
use App\Services\Tickets\FilesystemTicketPdfStorage;
use App\Services\Tickets\FilesystemTicketQrStorage;
use App\Services\Tickets\Renderers\TicketPdfRenderer;
use Illuminate\Support\ServiceProvider;

class TicketArtifactServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QrImageGeneratorInterface::class, EndroidQrImageGenerator::class);
        $this->app->singleton(TicketQrStorageInterface::class, FilesystemTicketQrStorage::class);
        $this->app->singleton(TicketPdfRendererInterface::class, TicketPdfRenderer::class);
        $this->app->singleton(TicketPdfStorageInterface::class, FilesystemTicketPdfStorage::class);
    }
}
