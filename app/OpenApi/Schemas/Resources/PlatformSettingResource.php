<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\PlatformSettings\PlatformSettingResource`. */
#[OA\Schema(
    schema: 'PlatformSettingResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'commission_rate', type: 'string'),
        new OA\Property(property: 'settings', type: 'object'),
        new OA\Property(property: 'version', type: 'integer'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final class PlatformSettingResource {}
