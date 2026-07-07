<?php

namespace App\Models;

use App\Support\Concerns\HasOptimisticLock;
use Database\Factories\PlatformSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $commission_rate
 * @property array<string, mixed>|null $settings
 * @property int $version
 */
class PlatformSetting extends Model
{
    /** @use HasFactory<PlatformSettingFactory> */
    use HasFactory, HasOptimisticLock;

    protected $fillable = [
        'commission_rate',
        'settings',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'settings' => 'array',
            'version' => 'integer',
        ];
    }
}
