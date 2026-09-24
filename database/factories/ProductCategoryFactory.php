<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            // The service derives this in real use; the factory sets it so a
            // test can create a category without going through the service.
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => $this->faker->sentence(12),
            'sort_order' => $this->faker->numberBetween(0, 20),
            'is_active' => true,
            'is_featured' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function childOf(ProductCategory $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }
}
