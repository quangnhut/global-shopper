<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Shopper\Cms\Support\HtmlSanitizer;
use Shopper\Core\Models\Category;
use Shopper\Core\Models\Product;
use Shopper\Core\Models\ProductVariant;
use Shopper\Seo\Support\SeoUrls;
use Shopper\Storefront\Actions\BuildHomeSectionsAction;
use Shopper\Storefront\Cart\StorefrontCart;
use Shopper\Storefront\Data\ProductCardData;
use Shopper\Storefront\Support\ProductPrice;
use Shopper\Storefront\Support\SectionItems;
use Shopper\Storefront\Support\StorefrontCache;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Product page at `shopper.seo.paths.product` (`/products/{slug}` by default).
 */
final class ProductController
{
    public function __invoke(string $slug, HtmlSanitizer $sanitizer): View
    {
        /** @var Product|null $product */
        $product = SectionItems::constrainProducts(config('shopper.models.product', Product::class)::query())
            ->where('slug', $slug)
            ->with([...ProductPrice::WITH, 'media', 'brand', 'categories' => fn ($query) => $query->where('is_enabled', true)])
            ->first();

        abort_if($product === null, 404);

        BuildHomeSectionsAction::loadStock([$product]);

        $card = ProductCardData::fromModel($product);
        abort_if($card === null, 404);

        $currency = shopper_currency();
        $variants = $product->variants
            ->filter(fn (ProductVariant $variant): bool => $variant->getPrice($currency)?->amount !== null)
            ->sortBy('position')
            ->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'name' => (string) $variant->name,
                'price' => shopper_money_format((int) $variant->getPrice($currency)?->amount, $currency),
                'in_stock' => StorefrontCart::available($variant) > 0,
            ])
            ->values()
            ->all();

        $category = $product->categories->first();

        return view('shopper-storefront::product', [
            ...StorefrontCache::layout(),
            'product' => $product,
            'card' => $card,
            'images' => $this->images($product),
            'variants' => $variants,
            'maxQuantity' => $variants === [] ? StorefrontCart::available($product) : StorefrontCart::MAX_QUANTITY,
            'description' => filled($product->description) ? $sanitizer->clean((string) $product->description) : null,
            'breadcrumbs' => $category instanceof Category ? $this->breadcrumbs($category) : [],
            'related' => $this->related($product),
        ]);
    }

    /**
     * Thumbnail first, then the gallery.
     *
     * @return array<int, string>
     */
    private function images(Product $product): array
    {
        if (! $product instanceof SpatieHasMedia) {
            return [];
        }

        return collect([
            config('shopper.media.storage.thumbnail_collection', 'thumbnail'),
            config('shopper.media.storage.collection_name', 'uploads'),
        ])
            ->flatMap(fn (string $collection) => $product->getMedia($collection))
            ->map(fn (Media $media): string => $media->getFullUrl())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function breadcrumbs(Category $category): array
    {
        // Ancestors have negative depths: the root comes first.
        return $category->ancestorsAndSelf()
            ->orderBy('depth')
            ->get()
            ->map(fn (Category $item): array => ['label' => (string) $item->name, 'url' => SeoUrls::entity('category', (string) $item->slug)])
            ->values()
            ->all();
    }

    /**
     * Other products of the same categories.
     *
     * @return array<int, ProductCardData>
     */
    private function related(Product $product): array
    {
        $categoryIds = $product->categories->pluck('id')->all();

        if ($categoryIds === []) {
            return [];
        }

        $products = SectionItems::constrainProducts(config('shopper.models.product', Product::class)::query())
            ->whereKeyNot($product->id)
            ->whereHas('categories', fn (Builder $query): Builder => $query->whereIn($query->qualifyColumn('id'), $categoryIds))
            ->with([...ProductPrice::WITH, 'media'])
            ->latest('published_at')
            ->limit(5)
            ->get();

        BuildHomeSectionsAction::loadStock($products);

        return $products->map(fn (Product $item): ?ProductCardData => ProductCardData::fromModel($item))->filter()->values()->all();
    }
}
