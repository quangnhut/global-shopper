<?php

declare(strict_types=1);

namespace Shopper\Seo\Sources;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shopper\Core\Models\Collection;

final class CollectionSource extends ShopSource
{
    public function isPublic(Model $model): bool
    {
        $publishedAt = $model->getAttribute('published_at');

        return $publishedAt instanceof CarbonInterface && ! $publishedAt->isFuture();
    }

    /**
     * @param  Collection  $model
     */
    public function isEmpty(Model $model): bool
    {
        return $model->productsQuery()
            ->where('is_visible', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->doesntExist();
    }

    public function sitemapQuery(string $type): Builder
    {
        return config('shopper.models.collection')::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('id');
    }

    protected function type(): string
    {
        return 'collection';
    }
}
