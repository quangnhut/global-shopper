<?php

declare(strict_types=1);

use Shopper\Cms\Models\Post;
use Shopper\Core\Models\Currency;
use Shopper\Core\Models\Product;
use Shopper\Core\Models\ProductVariant;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Support\SectionItems;

if (! function_exists('storefrontVnd')) {
    function storefrontVnd(): Currency
    {
        setupCurrencies(['VND'], 'VND');

        return Currency::query()->where('code', 'VND')->firstOrFail();
    }

    /**
     * A published product with a VND price, unless told otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    function storefrontProduct(array $attributes = [], ?int $amount = 1_500_000, ?int $compareAmount = null): Product
    {
        $product = Product::factory()->standard()->create(['allow_backorder' => false, 'is_visible' => true, 'published_at' => now(), ...$attributes]);

        if ($amount !== null) {
            $product->prices()->create([
                'amount' => $amount,
                'compare_amount' => $compareAmount,
                'currency_id' => storefrontVnd()->id,
            ]);
        }

        return $product;
    }

    function storefrontVariantPricedProduct(int $amount = 990_000): Product
    {
        $product = Product::factory()->create(['type' => Shopper\Core\Enum\ProductType::Variant, 'allow_backorder' => false, 'is_visible' => true, 'published_at' => now()]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->prices()->create(['amount' => $amount, 'currency_id' => storefrontVnd()->id]);

        return $product;
    }

    /**
     * @param  array<int, Illuminate\Database\Eloquent\Model>  $models
     */
    function storefrontAttach(HomeSection $section, array $models): HomeSection
    {
        foreach (array_values($models) as $position => $model) {
            $section->items()->create([
                'itemable_type' => $model->getMorphClass(),
                'itemable_id' => $model->getKey(),
                'position' => $position,
            ]);
        }

        return $section;
    }

    /**
     * @return array<int, int|string>
     */
    function storefrontResolvedIds(HomeSection $section): array
    {
        return resolve(SectionItems::class)
            ->resolve(collect([$section->fresh()]))[$section->id]
            ->map(fn ($model) => $model->getKey())
            ->all();
    }

    function storefrontPost(array $attributes = []): Post
    {
        return Post::factory()->create($attributes);
    }
}
