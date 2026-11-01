<?php

declare(strict_types=1);

namespace Shopper\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Models\Post;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = mb_rtrim($this->faker->unique()->sentence(4), '.');

        return [
            'type' => 'post',
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => $this->faker->sentence(18),
            'content' => '<p>'.implode('</p><p>', $this->faker->paragraphs(3)).'</p>',
            'status' => PostStatus::Published,
            'published_at' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
        ];
    }

    public function page(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'page',
            'excerpt' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostStatus::Pending,
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostStatus::Scheduled,
            'published_at' => now()->addWeek(),
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostStatus::Private,
            'published_at' => now()->subDay(),
        ]);
    }

    public function trashed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deleted_at' => now(),
        ]);
    }
}
