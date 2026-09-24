<?php

namespace Database\Factories;

use App\Models\SearchQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchQuery>
 */
class SearchQueryFactory extends Factory
{
    protected $model = SearchQuery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term' => SearchQuery::normalise(fake()->unique()->words(2, true)),
            'search_count' => fake()->numberBetween(1, 40),
            'result_count' => fake()->numberBetween(1, 12),
            'last_searched_at' => now(),
        ];
    }

    /**
     * A term the shop cannot answer - the interesting half of the table.
     */
    public function unanswered(): static
    {
        return $this->state(fn () => ['result_count' => 0]);
    }
}
