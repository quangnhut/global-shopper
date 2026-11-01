<?php

declare(strict_types=1);

namespace Shopper\Seo\Sources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shopper\Core\Models\Price;
use Shopper\Core\Models\Product;
use Shopper\Core\Models\ProductVariant;
use Shopper\Core\Models\Review;
use Shopper\Seo\Schema\GraphContext;
use Shopper\Seo\Support\TemplateRenderer;

/**
 * Products: price and availability from core, ratings from approved reviews, breadcrumbs through the first category.
 */
final class ProductSource extends ShopSource
{
    public const int REVIEWS_IN_SCHEMA = 5;

    /**
     * @param  Product  $model
     */
    public function isPublic(Model $model): bool
    {
        return $model->isPublished();
    }

    /**
     * @param  Product  $model
     */
    public function variables(Model $model): array
    {
        $price = $this->price($model);
        $brand = $model->brand_id === null ? null : $model->brand;

        return [
            ...parent::variables($model),
            'excerpt' => TemplateRenderer::excerpt($model->summary ?: $model->description),
            'description' => TemplateRenderer::excerpt($model->summary ?: $model->description),
            'brand' => $brand?->name,
            'category' => $model->categories->first()?->name,
            'price' => $price?->amount === null ? null : shopper_money_format($price->amount, $price->currency->code),
        ];
    }

    public function ogType(Model $model): string
    {
        return 'product';
    }

    /**
     * Shop breadcrumbs: the product's first category (with its parents), then the product.
     *
     * @param  Product  $model
     */
    public function breadcrumbs(Model $model): array
    {
        $category = $model->categories->first();
        $trail = $category === null ? [] : resolve(CategorySource::class)->breadcrumbs($category);

        return [...$trail, ['name' => $model->name, 'url' => $this->url($model)]];
    }

    /**
     * @param  Product  $model
     */
    public function schema(Model $model, GraphContext $context): array
    {
        $brand = $model->brand_id === null ? null : $model->brand;
        $product = array_filter([
            '@type' => 'Product',
            '@id' => $context->id('product'),
            'name' => $model->name,
            'description' => TemplateRenderer::excerpt($model->description, 5000) ?: null,
            'url' => $context->url,
            'mainEntityOfPage' => ['@id' => $context->webpageId],
            'image' => $this->images($model, 10) ?: ($context->image === null ? null : [$context->image]),
            'sku' => $model->sku,
            ...$this->gtin($model->barcode),
            'brand' => $brand === null ? null : ['@type' => 'Brand', 'name' => $brand->name],
            'category' => $model->categories->first()?->name,
            'offers' => $this->offers($model, $context),
            ...$this->ratings($model),
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        return [$product];
    }

    public function sitemapQuery(string $type): Builder
    {
        return config('shopper.models.product')::query()->publish()->orderBy('id');
    }

    protected function type(): string
    {
        return 'product';
    }

    /**
     * @return array<string, string>
     */
    private function gtin(?string $barcode): array
    {
        $digits = preg_replace('/\D/', '', (string) $barcode);

        return match (mb_strlen((string) $digits)) {
            8, 12, 13, 14 => ['gtin'.mb_strlen((string) $digits) => (string) $digits],
            default => [],
        };
    }

    /**
     * Prices are loaded first: `getPrice()` only matches the currency reliably on a loaded relation.
     */
    private function price(Product|ProductVariant $product): ?Price
    {
        $product->loadMissing('prices.currency');
        $price = $product->getPrice();

        return $price?->amount !== null ? $price : null;
    }

    /**
     * One `Offer` for a simple product, an `AggregateOffer` (lowest/highest) when only variants have prices.
     *
     * @return array<string, mixed>|null
     */
    private function offers(Product $product, GraphContext $context): ?array
    {
        $availability = 'https://schema.org/'.$this->availability($product);
        $price = $this->price($product);

        if ($price !== null) {
            return [
                '@type' => 'Offer',
                'url' => $context->url,
                'price' => $this->decimal($price),
                'priceCurrency' => $price->currency->code,
                'availability' => $availability,
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@id' => $context->publisherId],
            ];
        }

        $prices = $product->variants()->get()
            ->map(fn (ProductVariant $variant): ?Price => $this->price($variant))
            ->filter();

        if ($prices->isEmpty()) {
            return null;
        }

        /** @var Price $lowest */
        $lowest = $prices->sortBy('amount')->first();
        /** @var Price $highest */
        $highest = $prices->sortByDesc('amount')->first();

        return [
            '@type' => 'AggregateOffer',
            'url' => $context->url,
            'lowPrice' => $this->decimal($lowest),
            'highPrice' => $this->decimal($highest),
            'offerCount' => $prices->count(),
            'priceCurrency' => $lowest->currency->code,
            'availability' => $availability,
        ];
    }

    private function availability(Product $product): string
    {
        if (! $product->tracksInventory()) {
            return 'InStock';
        }

        if ($product->variants()->exists()) {
            return $product->variants()->get()->contains(fn (Model $variant): bool => (int) $variant->getAttribute('stock') > 0)
                ? 'InStock'
                : 'OutOfStock';
        }

        return match (true) {
            $product->stock > 0 => 'InStock',
            $product->allow_backorder => 'BackOrder',
            default => 'OutOfStock',
        };
    }

    private function decimal(Price $price): string
    {
        $amount = (int) $price->amount;

        return is_no_division_currency($price->currency->code)
            ? (string) $amount
            : number_format($amount / 100, 2, '.', '');
    }

    /**
     * `aggregateRating` and the latest reviews, from approved reviews only.
     *
     * @return array<string, mixed>
     */
    private function ratings(Product $product): array
    {
        $approved = $product->ratings()->where('approved', true);
        $count = (clone $approved)->count();

        if ($count === 0) {
            return [];
        }

        $reviews = (clone $approved)->with('author')->latest()->limit(self::REVIEWS_IN_SCHEMA)->get();

        return [
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) (clone $approved)->avg('rating'), 1),
                'reviewCount' => $count,
                'bestRating' => 5,
                'worstRating' => 1,
            ],
            'review' => $reviews->map(fn (Review $review): array => array_filter([
                '@type' => 'Review',
                'name' => $review->title,
                'reviewBody' => $review->content,
                'datePublished' => $review->created_at->toDateString(),
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $review->rating, 'bestRating' => 5, 'worstRating' => 1],
                'author' => ['@type' => 'Person', 'name' => $this->authorName($review)],
            ], fn (mixed $value): bool => $value !== null))->all(),
        ];
    }

    private function authorName(Review $review): string
    {
        $author = $review->author;

        if (! $author instanceof Model) {
            return __('shopper-seo::output.anonymous');
        }

        return (string) ($author->getAttribute('full_name') ?: $author->getAttribute('name') ?: __('shopper-seo::output.anonymous'));
    }
}
