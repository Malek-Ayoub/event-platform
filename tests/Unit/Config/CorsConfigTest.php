<?php

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CorsConfigTest extends TestCase
{
    /**
     * @return array{previous: ?string, wasSet: bool}
     */
    private function setCorsOriginsEnv(?string $value): array
    {
        $wasSet = array_key_exists('CORS_ALLOWED_ORIGINS', $_ENV)
            || array_key_exists('CORS_ALLOWED_ORIGINS', $_SERVER)
            || getenv('CORS_ALLOWED_ORIGINS') !== false;

        $previous = $_ENV['CORS_ALLOWED_ORIGINS']
            ?? $_SERVER['CORS_ALLOWED_ORIGINS']
            ?? (getenv('CORS_ALLOWED_ORIGINS') !== false ? (string) getenv('CORS_ALLOWED_ORIGINS') : null);

        if ($value === null) {
            unset($_ENV['CORS_ALLOWED_ORIGINS'], $_SERVER['CORS_ALLOWED_ORIGINS']);
            putenv('CORS_ALLOWED_ORIGINS');
        } else {
            $_ENV['CORS_ALLOWED_ORIGINS'] = $value;
            $_SERVER['CORS_ALLOWED_ORIGINS'] = $value;
            putenv('CORS_ALLOWED_ORIGINS='.$value);
        }

        return ['previous' => $previous, 'wasSet' => $wasSet];
    }

    /**
     * @param  array{previous: ?string, wasSet: bool}  $snapshot
     */
    private function restoreCorsOriginsEnv(array $snapshot): void
    {
        if (! $snapshot['wasSet'] || $snapshot['previous'] === null) {
            unset($_ENV['CORS_ALLOWED_ORIGINS'], $_SERVER['CORS_ALLOWED_ORIGINS']);
            putenv('CORS_ALLOWED_ORIGINS');

            return;
        }

        $_ENV['CORS_ALLOWED_ORIGINS'] = $snapshot['previous'];
        $_SERVER['CORS_ALLOWED_ORIGINS'] = $snapshot['previous'];
        putenv('CORS_ALLOWED_ORIGINS='.$snapshot['previous']);
    }

    #[Test]
    public function it_parses_comma_separated_cors_allowed_origins_from_env(): void
    {
        $snapshot = $this->setCorsOriginsEnv('https://a.com,https://b.com');

        try {
            $config = require base_path('config/cors.php');

            $this->assertSame(['https://a.com', 'https://b.com'], $config['allowed_origins']);
        } finally {
            $this->restoreCorsOriginsEnv($snapshot);
        }
    }

    #[Test]
    public function it_trims_whitespace_around_cors_allowed_origins(): void
    {
        $snapshot = $this->setCorsOriginsEnv(' https://a.com , https://b.com ');

        try {
            $config = require base_path('config/cors.php');

            $this->assertSame(['https://a.com', 'https://b.com'], $config['allowed_origins']);
        } finally {
            $this->restoreCorsOriginsEnv($snapshot);
        }
    }

    #[Test]
    public function it_defaults_to_wildcard_when_cors_allowed_origins_is_unset(): void
    {
        $snapshot = $this->setCorsOriginsEnv(null);

        try {
            $config = require base_path('config/cors.php');

            $this->assertSame(['*'], $config['allowed_origins']);
        } finally {
            $this->restoreCorsOriginsEnv($snapshot);
        }
    }
}
