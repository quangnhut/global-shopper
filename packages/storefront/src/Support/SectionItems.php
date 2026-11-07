<?php

declare(strict_types=1);

namespace Shopper\Storefront\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Shopper\Cms\Models\Post;
use Shopper\Core\Models\Product;
use Shopper\Storefront\Enums\HomeSectionType;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Models\HomeSectionItem;

/**
 * Turns the items picked for sections into the models that may be shown, in the admin's order.
 * Loads every section's products in one query and every section's posts in another.
 *
 * A product is shown when it is published and visible and has a price in the store currency
 * (on itself or one of its variants). A post is shown when it is a published, public `post`
 * without a password. Anything else is skipped silently, so the page never breaks.
 */
final class SectionItems
{
    /**
     * Products that may be shown on the storefront.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function constrainProducts(Builder $query): Builder
    {
        $currencyId = shopper_setting('default_currency_id');
        $hasPrice = fn (Builder $prices): Builder => $prices->whereNotNull('amount')
            ->when($currencyId, fn (Builder $prices): Builder => $prices->where('currency_id', $currencyId));

        return $query->publish()
            ->where(fn (Builder $query): Builder => $query->whereHas('prices', $hasPrice)
                ->orWhereHas('variants.prices', $hasPrice));
    }

    /**
     * Posts that may be shown on the storefront.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public static function constrainPosts(Builder $query): Builder
    {
        return $query->ofType('post')
            ->published()
            ->where(fn (Builder $query): Builder => $query->whereNull('password')->orWhere('password', ''));
    }

    public static function productMorph(): string
    {
        return (new (config('shopper.models.product')))->getMorphClass();
    }

    public static function postMorph(): string
    {
        return (new (Post::configuredClass()))->getMorphClass();
    }

    /**
     * @param  Collection<int, HomeSection>|EloquentCollection<int, HomeSection>  $sections
     * @param  Closure(Builder<Product>): mixed|null  $productQuery  extra eager loads / constraints
     * @param  Closure(Builder<Post>): mixed|null  $postQuery
     * @return array<int, Collection<int, Model>> keyed by section id
     */
    public function resolve(Collection $sections, ?Closure $productQuery = null, ?Closure $postQuery = null): array
    {
        $sections = $sections instanceof EloquentCollection ? $sections : new EloquentCollection($sections->all());
        $sections->loadMissing('items');

        $productIds = $this->itemIds($sections, HomeSectionType::Products, self::productMorph());
        $postIds = $this->itemIds($sections, HomeSectionType::Posts, self::postMorph());

        $products = $productIds === [] ? collect() : $this->products($productIds, $productQuery)->keyBy(fn (Model $model): int => (int) $model->getKey());
        $posts = $postIds === [] ? collect() : $this->posts($postIds, $postQuery)->keyBy(fn (Model $model): int => (int) $model->getKey());

        $resolved = [];

        foreach ($sections as $section) {
            [$pool, $morph] = $section->type === HomeSectionType::Products
                ? [$products, self::productMorph()]
                : [$posts, self::postMorph()];

            $models = $section->items
                ->filter(fn (HomeSectionItem $item): bool => $item->itemable_type === $morph)
                ->map(fn (HomeSectionItem $item): ?Model => $pool->get((int) $item->itemable_id))
                ->filter()
                ->values();

            $resolved[$section->id] = $section->limit ? $models->take($section->limit)->values() : $models;
        }

        return $resolved;
    }

    /**
     * @param  EloquentCollection<int, HomeSection>  $sections
     * @return array<int, int>
     */
    private function itemIds(EloquentCollection $sections, HomeSectionType $type, string $morph): array
    {
        return $sections
            ->filter(fn (HomeSection $section): bool => $section->type === $type)
            ->flatMap(fn (HomeSection $section): Collection => $section->items)
            ->filter(fn (HomeSectionItem $item): bool => $item->itemable_type === $morph)
            ->map(fn (HomeSectionItem $item): int => (int) $item->itemable_id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return EloquentCollection<int, Product>
     */
    private function products(array $ids, ?Closure $extra): EloquentCollection
    {
        $query = self::constrainProducts(config('shopper.models.product')::query()->whereKey($ids));

        if ($extra instanceof Closure) {
            $extra($query);
        }

        return $query->get();
    }

    /**
     * @param  array<int, int>  $ids
     * @return EloquentCollection<int, Post>
     */
    private function posts(array $ids, ?Closure $extra): EloquentCollection
    {
        $query = self::constrainPosts(Post::configuredClass()::query()->whereKey($ids));

        if ($extra instanceof Closure) {
            $extra($query);
        }

        return $query->get();
    }
}
