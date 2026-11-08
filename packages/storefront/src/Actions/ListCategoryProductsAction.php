<?php

declare(strict_types=1);

namespace Shopper\Storefront\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Shopper\Core\Models\Category;
use Shopper\Core\Models\Price;
use Shopper\Core\Models\Product;
use Shopper\Core\Models\ProductVariant;
use Shopper\Storefront\Data\ProductCardData;
use Shopper\Storefront\Support\ProductPrice;
use Shopper\Storefront\Support\SectionItems;

/**
 * Products of a category page: the category and all its enabled sub-categories,
 * only products the storefront can show, sorted as the customer chose.
 */
final class ListCategoryProductsAction
{
    public const array SORTS = ['newest', 'price_asc', 'price_desc', 'name'];

    /**
     * @return LengthAwarePaginator<int, ProductCardData>
     */
    public function execute(Category $category, string $sort = 'newest', int $perPage = 24, int $page = 1): LengthAwarePaginator
    {
        $categoryIds = $category->descendantsAndSelf()->where('is_enabled', true)->pluck('id')->all();

        $query = SectionItems::constrainProducts(config('shopper.models.product')::query())
            ->whereHas('categories', fn (Builder $categories): Builder => $categories->whereIn($categories->qualifyColumn('id'), $categoryIds));

        $this->sort($query, in_array($sort, self::SORTS, true) ? $sort : 'newest');

        $paginator = $query
            ->with([...ProductPrice::WITH, 'media'])
            ->paginate($perPage, ['*'], 'page', $page);

        BuildHomeSectionsAction::loadStock($paginator->items());

        return new LengthAwarePaginator(
            collect($paginator->items())->map(fn ($product): ?ProductCardData => ProductCardData::fromModel($product))->filter()->values(),
            $paginator->total(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function sort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name' => $query->orderBy('name'),
            'price_asc', 'price_desc' => $query
                ->orderByRaw(...$this->priceOrder($query, $sort === 'price_asc' ? 'asc' : 'desc'))
                ->orderBy('name'),
            default => $query->latest('published_at')->orderByDesc('id'),
        };
    }

    /**
     * Order by the price a card shows: the product's own price, else its cheapest variant price.
     *
     * @param  Builder<Product>  $query
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function priceOrder(Builder $query, string $direction): array
    {
        $currencyId = shopper_setting('default_currency_id');
        $prices = (new Price)->getTable();
        $variants = (new ProductVariant)->getTable();
        $products = $query->getModel()->getTable();

        $own = Price::query()->toBase()
            ->selectRaw('min(amount)')
            ->where('priceable_type', $query->getModel()->getMorphClass())
            ->whereColumn("{$prices}.priceable_id", "{$products}.id")
            ->when($currencyId, fn (QueryBuilder $builder): QueryBuilder => $builder->where("{$prices}.currency_id", $currencyId));

        $variant = Price::query()->toBase()
            ->selectRaw("min({$prices}.amount)")
            ->join($variants, "{$variants}.id", '=', "{$prices}.priceable_id")
            ->where("{$prices}.priceable_type", (new ProductVariant)->getMorphClass())
            ->whereColumn("{$variants}.product_id", "{$products}.id")
            ->when($currencyId, fn (QueryBuilder $builder): QueryBuilder => $builder->where("{$prices}.currency_id", $currencyId));

        return [
            'coalesce(('.$own->toSql().'), ('.$variant->toSql().')) '.$direction,
            [...$own->getBindings(), ...$variant->getBindings()],
        ];
    }
}
