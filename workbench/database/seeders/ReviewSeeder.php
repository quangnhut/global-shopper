<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Shopper\Core\Models\Contracts\Product as ProductContract;
use Shopper\Core\Models\Review;

final class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $userModel = config('auth.providers.users.model');
        $customers = $userModel::query()->role(config('shopper.admin.roles.user'))->get();
        $products = resolve(ProductContract::class)::query()->get();

        for ($i = 0; $i < 60; $i++) {
            $product = $products->random();
            $customer = $customers->random();
            $rating = fake()->numberBetween(1, 5);

            Review::query()->create([
                'rating' => $rating,
                'title' => fake()->sentence(4),
                'content' => fake()->paragraph(),
                'is_recommended' => $rating >= 4,
                'approved' => fake()->boolean(80),
                'reviewrateable_type' => $product->getMorphClass(),
                'reviewrateable_id' => $product->id,
                'author_type' => $customer->getMorphClass(),
                'author_id' => $customer->id,
            ]);
        }
    }
}
