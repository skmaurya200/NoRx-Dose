<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::ucfirst($this->faker->unique()->words(6, true));

        return [
            'category_id' => BlogCategory::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'excerpt' => $this->faker->sentence(14),
            // Already in the shape the sanitiser would leave it, so a factory
            // post and a real one render identically.
            'body' => '<p>'.implode('</p><p>', $this->faker->paragraphs(4)).'</p>',
            'takeaways' => [
                $this->faker->sentence(7),
                $this->faker->sentence(6),
            ],
            'author_name' => 'The Aurum team',
            'read_minutes' => $this->faker->numberBetween(4, 10),
            'status' => 'published',
            'is_featured' => false,
            'views_count' => 0,
            'published_at' => now()->subDays($this->faker->numberBetween(1, 200)),
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

    /**
     * Published, but dated in the future - visible in the panel and a 404 on
     * the storefront.
     */
    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now()->addWeek(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function uncategorised(): static
    {
        return $this->state(fn () => ['category_id' => null]);
    }
}
