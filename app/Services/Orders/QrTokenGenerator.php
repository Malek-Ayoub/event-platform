<?php

namespace App\Services\Orders;

use Illuminate\Support\Str;

/**
 * Generates opaque QR lookup tokens (Phase 8.3.2).
 *
 * Uses UUID v7 for time-ordered index locality while remaining non-guessable.
 */
final class QrTokenGenerator
{
    public function generate(): string
    {
        return (string) Str::uuid7();
    }
}
