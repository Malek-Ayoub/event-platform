<?php

namespace App\Services\Tickets\Data;

final class EnsureQrImageResult
{
    public function __construct(
        public readonly bool $wasGenerated,
        public readonly string $storagePath,
    ) {}
}
