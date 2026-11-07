<?php

declare(strict_types=1);

namespace Shopper\Storefront\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shopper\Cms\Models\Post;
use Shopper\Core\Models\Product;
use Shopper\Storefront\Enums\HomeSectionType;

/**
 * Options of the section editor's item picker: only items the storefront can show are offered.
 * Labels are HTML (thumbnail, name, SKU, price); every value in them is escaped.
 */
final class ItemPicker
{
    private const int RESULTS = 30;

    /**
     * @return array<int, string>
     */
    public function search(HomeSectionType $type, ?string $term): array
    {
        $term = mb_trim((string) $term);

        $models = $type === HomeSectionType::Products
            ? SectionItems::constrainProducts($this->productQuery())
                ->when($term !== '', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")))
                ->orderBy('name')
                ->limit(self::RESULTS)
                ->get()
            : SectionItems::constrainPosts(Post::configuredClass()::query())
                ->when($term !== '', fn (Builder $query): Builder => $query->where('title', 'like', "%{$term}%"))
                ->latest('published_at')
                ->limit(self::RESULTS)
                ->get();

        return $models->mapWithKeys(fn (Model $model): array => [(int) $model->getKey() => $this->label($model)])->all();
    }

    /**
     * Labels for ids already picked, including items that can no longer be shown (flagged as hidden).
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, string>
     */
    public function labels(HomeSectionType $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $isProducts = $type === HomeSectionType::Products;
        $models = ($isProducts ? $this->productQuery() : Post::configuredClass()::query())->whereKey($ids)->get();
        $visible = ($isProducts
            ? SectionItems::constrainProducts(config('shopper.models.product')::query())
            : SectionItems::constrainPosts(Post::configuredClass()::query()))
            ->whereKey($ids)
            ->pluck((new (config('shopper.models.product')))->getKeyName())
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $models->mapWithKeys(fn (Model $model): array => [
            (int) $model->getKey() => $this->label($model, hidden: ! in_array((int) $model->getKey(), $visible, true)),
        ])->all();
    }

    public function label(Model $model, bool $hidden = false): string
    {
        if ($model instanceof Product) {
            $price = ProductPrice::for($model);
            $image = ProductImage::url($model);
            $details = array_filter([$model->sku, $price ? shopper_money_format($price['amount']) : null]);

            return $this->html((string) $model->name, implode(' · ', $details), $image, $hidden);
        }

        if ($model instanceof Post) {
            $model->loadMissing('featuredImage');

            return $this->html((string) $model->title, $model->published_at?->translatedFormat('d/m/Y') ?? '', $model->featuredImage?->url, $hidden);
        }

        return e((string) $model->getKey());
    }

    /**
     * @return Builder<Product>
     */
    private function productQuery(): Builder
    {
        return config('shopper.models.product')::query()->with([...ProductPrice::WITH, 'media']);
    }

    private function html(string $title, string $details, ?string $image, bool $hidden): string
    {
        $thumbnail = filled($image)
            ? '<img src="'.e($image).'" alt="" class="size-8 shrink-0 rounded object-cover" />'
            : '<span class="size-8 shrink-0 rounded bg-gray-100 dark:bg-white/10"></span>';
        $flag = $hidden
            ? ' <span class="text-xs font-medium text-danger-600">'.e(__('shopper-storefront::admin.sections.hidden_item')).'</span>'
            : '';

        return '<span class="flex items-center gap-2">'.$thumbnail
            .'<span class="min-w-0"><span class="block truncate">'.e($title).$flag.'</span>'
            .'<span class="block truncate text-xs text-gray-500">'.e($details).'</span></span></span>';
    }
}
