<?php

namespace App\Services\Tickets\CheckIn\Data;

final class CheckInTicketData
{
    public function __construct(
        public readonly string $qrToken,
        public readonly int $checkedInByUserId,
        public readonly ?int $gateId = null,
        public readonly ?string $deviceId = null,
        public readonly ?string $notes = null,
    ) {}
}
