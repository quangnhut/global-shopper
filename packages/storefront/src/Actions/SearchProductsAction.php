<?php

declare(strict_types=1);

namespace Shopper\Storefront\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Shopper\Storefront\Data\ProductCardData;
use Shopper\Storefront\Support\ProductPrice;
use Shopper\Storefront\Support\SectionItems;

/**
 * Product search for the header suggestions and the search page: name or SKU contains every word.
 * Only products the storefront can show are returned.
 */
final class SearchProductsAction
{
    public const int MAX_TERM_LENGTH = 100;

    /**
     * @return LengthAwarePaginator<int, ProductCardData>
     */
    public function execute(?string $term, int $perPage = 24, int $page = 1): LengthAwarePaginator
    {
        $words = collect(preg_split('/\s+/u', mb_trim(mb_substr((string) $term, 0, self::MAX_TERM_LENGTH))) ?: [])
            ->filter(fn (string $word): bool => $word !== '')
            ->take(8);

        if ($words->isEmpty()) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $paginator = SectionItems::constrainProducts(config('shopper.models.product')::query())
            ->where(function (Builder $query) use ($words): void {
                foreach ($words as $word) {
                    $like = '%'.addcslashes($word, '%_\\').'%';
                    $query->where(fn (Builder $query): Builder => $query->where('name', 'like', $like)->orWhere('sku', 'like', $like));
                }
            })
            ->with([...ProductPrice::WITH, 'media'])
            ->orderBy('name')
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
}
