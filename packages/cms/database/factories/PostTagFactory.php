<?php

declare(strict_types=1);

namespace Shopper\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shopper\Cms\Models\PostTag;

/**
 * @extends Factory<PostTag>
 */
class PostTagFactory extends Factory
{
    protected $model = PostTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
        ];
    }
}
