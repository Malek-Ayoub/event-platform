<?php

namespace App\Contracts\Tickets;

interface QrImageGeneratorInterface
{
    /**
     * @return non-empty-string PNG binary
     */
    public function generatePng(string $payload): string;
}
