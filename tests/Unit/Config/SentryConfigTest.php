<?php

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SentryConfigTest extends TestCase
{
    #[Test]
    public function sentry_dsn_is_empty_by_default_in_local_and_testing(): void
    {
        $dsn = config('sentry.dsn');

        $this->assertTrue(
            $dsn === null || $dsn === '',
            'Sentry DSN must stay empty in testing/local so the SDK stays a no-op (no network).',
        );
    }
}
