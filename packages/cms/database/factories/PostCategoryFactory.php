<?php

declare(strict_types=1);

namespace Shopper\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shopper\Cms\Models\PostCategory;

/**
 * @extends Factory<PostCategory>
 */
class PostCategoryFactory extends Factory
{
    protected $model = PostCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'description' => $this->faker->sentence(),
        ];
    }
}
