<?php

namespace Tests\Support\Tickets;

use App\Contracts\Tickets\QrImageGeneratorInterface;

final class RecordingQrImageGenerator implements QrImageGeneratorInterface
{
    /** @var list<string> */
    public array $payloads = [];

    public int $calls = 0;

    public function generatePng(string $payload): string
    {
        $this->calls++;
        $this->payloads[] = $payload;

        return $this->minimalPng();
    }

    /**
     * @return non-empty-string
     */
    private function minimalPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        ) ?: 'x';
    }
}
