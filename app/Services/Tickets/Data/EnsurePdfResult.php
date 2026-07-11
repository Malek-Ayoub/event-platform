<?php

namespace App\Services\Tickets\Data;

final class EnsurePdfResult
{
    public function __construct(
        public readonly bool $wasGenerated,
        public readonly string $storagePath,
        public readonly int $version,
    ) {}
}
