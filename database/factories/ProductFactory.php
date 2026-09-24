<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title($this->faker->unique()->words(3, true));
        $price = $this->faker->randomFloat(2, 9, 120);

        return [
            'category_id' => ProductCategory::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'sku' => 'AW-'.Str::upper(Str::random(8)),
            'brand' => 'NoRx Dose',
            'short_description' => $this->faker->sentence(10),
            'description' => $this->faker->paragraphs(3, true),
            'price' => $price,
            // Always above the selling price, so the factory never produces a
            // product that its own validation rules would reject.
            'compare_at_price' => round($price * 1.3, 2),
            'cost_price' => round($price * 0.45, 2),
            'currency' => 'USD',
            'unit' => $this->faker->randomElement(['30 capsules', '60 capsules', '250 g', '500 ml']),
            'weight_grams' => $this->faker->randomFloat(2, 50, 900),
            'track_inventory' => true,
            // Comfortably above low_stock_threshold. A range starting at zero
            // would make any test that filters on stock level randomly fail,
            // so the low and out-of-stock states are opted into explicitly.
            'stock_quantity' => $this->faker->numberBetween(40, 180),
            'low_stock_threshold' => 10,
            'allow_backorder' => false,
            'status' => 'active',
            'is_featured' => false,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft', 'published_at' => null]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived']);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => [
            'track_inventory' => true,
            'allow_backorder' => false,
            'stock_quantity' => 0,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => [
            'track_inventory' => true,
            'stock_quantity' => 3,
            'low_stock_threshold' => 10,
        ]);
    }
}
