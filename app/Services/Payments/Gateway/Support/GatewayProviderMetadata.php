<?php

namespace App\Services\Payments\Gateway\Support;

/**
 * Canonical provider metadata shape returned by all gateway implementations.
 *
 * @phpstan-type GatewayProviderMetadataShape array{
 *     provider: string,
 *     provider_transaction_id: string,
 *     provider_reference: string,
 *     provider_status: string,
 *     raw: array<string, mixed>
 * }
 */
final class GatewayProviderMetadata
{
    public const PROVIDER = 'provider';

    public const PROVIDER_TRANSACTION_ID = 'provider_transaction_id';

    public const PROVIDER_REFERENCE = 'provider_reference';

    public const PROVIDER_STATUS = 'provider_status';

    public const RAW = 'raw';

    /**
     * @param  array<string, mixed>  $raw
     * @return GatewayProviderMetadataShape
     */
    public static function build(
        string $provider,
        ?string $providerTransactionId = null,
        ?string $providerReference = null,
        ?string $providerStatus = null,
        array $raw = [],
    ): array {
        return [
            self::PROVIDER => $provider,
            self::PROVIDER_TRANSACTION_ID => $providerTransactionId ?? '',
            self::PROVIDER_REFERENCE => $providerReference ?? '',
            self::PROVIDER_STATUS => $providerStatus ?? '',
            self::RAW => $raw,
        ];
    }
}
