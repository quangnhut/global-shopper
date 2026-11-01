<?php

declare(strict_types=1);

namespace Shopper\Seo\Sources;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Shopper\Seo\Contracts\SeoSource;
use Shopper\Seo\Schema\GraphContext;
use Shopper\Seo\Support\SeoUrls;
use Shopper\Seo\Support\TemplateRenderer;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;

/**
 * Shared behaviour of shop entities (products, categories, brands, collections): a `name`, a `slug`, an
 * HTML `description` and images in the media library.
 */
abstract class ShopSource implements SeoSource
{
    /**
     * Key in `shopper.seo.paths`.
     */
    abstract protected function type(): string;

    public function url(Model $model): string
    {
        return SeoUrls::entity($this->type(), (string) ($model->getAttribute('slug') ?: $model->getKey()));
    }

    public function variables(Model $model): array
    {
        $description = TemplateRenderer::excerpt($model->getAttribute('description'));

        return [
            'title' => $model->getAttribute('name'),
            'description' => $description,
            'excerpt' => $description,
            'date' => $this->publishedAt($model)?->translatedFormat('j F Y'),
            'modified' => $this->modifiedAt($model)?->translatedFormat('j F Y'),
        ];
    }

    public function image(Model $model): ?string
    {
        return $this->images($model, 1)[0] ?? null;
    }

    public function ogType(Model $model): string
    {
        return 'website';
    }

    public function publishedAt(Model $model): ?CarbonInterface
    {
        $date = $model->getAttribute('published_at') ?? $model->getAttribute('created_at');

        return $date instanceof CarbonInterface ? $date : null;
    }

    public function modifiedAt(Model $model): ?CarbonInterface
    {
        $date = $model->getAttribute('updated_at');

        return $date instanceof CarbonInterface ? $date : null;
    }

    public function isEmpty(Model $model): bool
    {
        return false;
    }

    public function breadcrumbs(Model $model): array
    {
        return [['name' => (string) $model->getAttribute('name'), 'url' => $this->url($model)]];
    }

    public function schema(Model $model, GraphContext $context): array
    {
        return [];
    }

    public function sitemapImages(Model $model): array
    {
        return $this->images($model, 10);
    }

    /**
     * Thumbnail first, then the gallery.
     *
     * @return array<int, string>
     */
    protected function images(Model $model, int $limit): array
    {
        if (! $model instanceof SpatieHasMedia) {
            return [];
        }

        $urls = [];

        foreach ([config('shopper.media.storage.thumbnail_collection', 'thumbnail'), config('shopper.media.storage.collection_name', 'default')] as $collection) {
            foreach ($model->getMedia((string) $collection) as $media) {
                $urls[] = $media->getFullUrl();
            }
        }

        return array_slice(array_values(array_unique($urls)), 0, $limit);
    }
}
