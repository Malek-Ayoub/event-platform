<?php

namespace App\Contracts\Payments\Http;

readonly class GatewayHttpResponse
{
    /**
     * @param  array<string, mixed>|null  $body
     */
    public function __construct(
        public int $status,
        public ?array $body,
        public string $rawBody = '',
    ) {}

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
