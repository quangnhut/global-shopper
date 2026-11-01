<?php

declare(strict_types=1);

namespace Shopper\Seo\Sources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shopper\Core\Models\Brand;

final class BrandSource extends ShopSource
{
    public function isPublic(Model $model): bool
    {
        return (bool) $model->getAttribute('is_enabled');
    }

    /**
     * @param  Brand  $model
     */
    public function isEmpty(Model $model): bool
    {
        return $model->products()->publish()->doesntExist();
    }

    public function sitemapQuery(string $type): Builder
    {
        return config('shopper.models.brand')::query()->where('is_enabled', true)->orderBy('id');
    }

    protected function type(): string
    {
        return 'brand';
    }
}
