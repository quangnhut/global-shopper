<?php

declare(strict_types=1);

namespace Shopper\Storefront\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shopper\Storefront\Enums\HomeSectionLayout;
use Shopper\Storefront\Enums\HomeSectionStyle;
use Shopper\Storefront\Enums\HomeSectionType;
use Shopper\Storefront\Models\HomeSection;

/**
 * @extends Factory<HomeSection>
 */
class HomeSectionFactory extends Factory
{
    protected $model = HomeSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => HomeSectionType::Products,
            'title' => mb_rtrim($this->faker->sentence(3), '.'),
            'style' => HomeSectionStyle::Default,
            'layout' => HomeSectionLayout::Grid,
            'columns' => 5,
            'position' => $this->faker->numberBetween(0, 100),
            'is_enabled' => true,
        ];
    }

    public function posts(): static
    {
        return $this->state(fn (): array => ['type' => HomeSectionType::Posts, 'columns' => 4]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }
}
