<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $product_id
 * @property string $name
 * @property string|null $sku
 * @property string|null $price_override
 * @property bool $is_active
 */
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'product_id',
        'name',
        'sku',
        'price_override',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'product_id' => 'integer',
            'name' => 'string',
            'sku' => 'string',
            'price_override' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
