<?php

namespace App\Enums\Payments;

enum GatewayOutcome: string
{
    case Success = 'success';
    case Declined = 'declined';
    case InvalidSignature = 'invalid_signature';
    case Timeout = 'timeout';
    case NetworkError = 'network_error';
    case ProviderError = 'provider_error';
    case Unknown = 'unknown';
}
