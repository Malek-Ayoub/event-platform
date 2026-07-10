<?php

namespace Tests\Unit\Services\Orders;

use App\Services\Orders\QrTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QrTokenGeneratorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_uuid_v7_tokens(): void
    {
        $token = app(QrTokenGenerator::class)->generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $token,
        );
    }

    #[Test]
    public function it_generates_one_thousand_unique_qr_tokens(): void
    {
        $generator = app(QrTokenGenerator::class);
        $tokens = [];

        for ($i = 0; $i < 1000; $i++) {
            $tokens[] = $generator->generate();
        }

        $this->assertCount(1000, $tokens);
        $this->assertSame(1000, count(array_unique($tokens)));
    }
}
