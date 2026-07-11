<?php

namespace App\Enums\Tickets;

enum TicketArtifactType: string
{
    case Qr = 'qr';
    case Pdf = 'pdf';
    case WalletPass = 'wallet_pass';
}
