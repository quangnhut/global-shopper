<?php

declare(strict_types=1);

namespace Shopper\Seo\Sources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shopper\Core\Models\Category;

final class CategorySource extends ShopSource
{
    public function isPublic(Model $model): bool
    {
        return (bool) $model->getAttribute('is_enabled');
    }

    /**
     * @param  Category  $model
     */
    public function isEmpty(Model $model): bool
    {
        $ids = $model->descendantsAndSelf()->pluck($model->getKeyName())->all();

        return config('shopper.models.product')::query()
            ->publish()
            ->whereHas('categories', fn (Builder $query) => $query->whereIn($model->getQualifiedKeyName(), $ids))
            ->doesntExist();
    }

    /**
     * Parent categories first.
     *
     * @param  Category  $model
     */
    public function breadcrumbs(Model $model): array
    {
        $trail = [];
        $category = $model;
        $seen = [];

        while ($category instanceof Model && ! isset($seen[$category->getKey()])) {
            $seen[$category->getKey()] = true;
            array_unshift($trail, ['name' => (string) $category->getAttribute('name'), 'url' => $this->url($category)]);
            $category = $category->getAttribute('parent');
        }

        return $trail;
    }

    public function sitemapQuery(string $type): Builder
    {
        return config('shopper.models.category')::query()->where('is_enabled', true)->orderBy('id');
    }

    protected function type(): string
    {
        return 'category';
    }
}
