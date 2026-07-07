<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->words(2, true),
            'sku' => fake()->optional()->bothify('SKU-####'),
            'price_override' => fake()->optional()->randomFloat(2, 5, 200),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ProductVariant $variant): void {
            if ($variant->product_id !== null) {
                $product = Product::query()->find($variant->product_id);
                if ($product !== null) {
                    $variant->venue_id = $product->venue_id;
                }
            }
        });
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'venue_id' => $product->venue_id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
