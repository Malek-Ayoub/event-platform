<?php

namespace App\Services\Tickets;

use App\Contracts\Tickets\QrImageGeneratorInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

final class EndroidQrImageGenerator implements QrImageGeneratorInterface
{
    public function generatePng(string $payload): string
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: $payload,
            size: (int) config('tickets.qr.size', 300),
            margin: (int) config('tickets.qr.margin', 10),
        ))->build();

        $binary = $result->getString();

        if ($binary === '') {
            throw new \RuntimeException('QR image generator returned empty PNG data.');
        }

        return $binary;
    }
}
