<?php

namespace App\Exceptions\PlatformSettings;

use RuntimeException;

class PlatformSettingSingletonViolationException extends RuntimeException
{
    public static function detected(int $rowCount): self
    {
        return new self(
            "Platform settings must be a singleton; found {$rowCount} rows.",
        );
    }
}
