<?php

declare(strict_types=1);

namespace Shopper\Storefront\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Permalinks\PermalinkGenerator;
use Shopper\Core\Models\Product;
use Shopper\Core\Models\ProductVariant;
use Shopper\Storefront\Data\PostCardData;
use Shopper\Storefront\Data\ProductCardData;
use Shopper\Storefront\Data\SectionData;
use Shopper\Storefront\Enums\HomeSectionType;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Support\ProductPrice;
use Shopper\Storefront\Support\SectionItems;

/**
 * The live home page sections, top to bottom, each with the cards it can show. Sections left
 * without a single showable item are skipped.
 */
final readonly class BuildHomeSectionsAction
{
    public function __construct(private SectionItems $sectionItems) {}

    /**
     * Current stock of the products and their variants, two queries in total.
     *
     * @param  iterable<int, Model>  $products
     */
    public static function loadStock(iterable $products): void
    {
        $products = new Collection(collect($products)->unique(fn (Model $product): int => (int) $product->getKey())->values()->all());

        if ($products->isEmpty()) {
            return;
        }

        $variants = new Collection($products->flatMap(fn (Model $product) => $product->getRelation('variants'))->all());

        Product::loadCurrentStock($products);

        if ($variants->isNotEmpty()) {
            ProductVariant::loadCurrentStock($variants);
        }
    }

    /**
     * @return array<int, SectionData>
     */
    public function execute(): array
    {
        $sections = HomeSection::query()->live()->ordered()->with('items')->get();

        $resolved = $this->sectionItems->resolve(
            $sections,
            fn (Builder $query) => $query->with([...ProductPrice::WITH, 'media']),
            fn (Builder $query) => $query->with(['featuredImage', 'parent', 'primaryCategory', 'categories']),
        );

        self::loadStock(collect($resolved)->flatten(1)->filter(fn (Model $model): bool => $model instanceof Product)->values());
        $permalinks = resolve(PermalinkGenerator::class);

        return $sections
            ->map(fn (HomeSection $section): ?SectionData => $this->section($section, $resolved[$section->id] ?? collect(), $permalinks))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Model>  $models
     */
    private function section(HomeSection $section, \Illuminate\Support\Collection $models, PermalinkGenerator $permalinks): ?SectionData
    {
        $items = $models
            ->map(fn (Model $model): ProductCardData|PostCardData|null => match (true) {
                $section->type === HomeSectionType::Products => ProductCardData::fromModel($model),
                $model instanceof Post => PostCardData::fromModel($model, $permalinks),
                default => null,
            })
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return new SectionData(
            id: $section->id,
            type: $section->type,
            title: $section->title,
            subtitle: $section->subtitle,
            style: $section->style,
            layout: $section->layout,
            columns: $section->columns,
            viewAllUrl: $section->view_all_url,
            endsAt: $section->ends_at,
            items: $items,
        );
    }
}
