<?php

declare(strict_types=1);

namespace Shopper\Seo\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shopper\Seo\Schema\GraphContext;

/**
 * Describes the entries of one content type to the SEO output: where they live, what the title and
 * description templates can use, their schema.org nodes and which of them belong in the sitemap.
 */
interface SeoSource
{
    /**
     * Absolute public URL of the entry.
     */
    public function url(Model $model): string;

    /**
     * Published and visible to everyone (drafts, private or disabled entries get `noindex`).
     */
    public function isPublic(Model $model): bool;

    /**
     * Values for the title/description templates, without the `%`: `title`, `excerpt`, `description`,
     * `category`, `tag`, `author`, `date`, `modified`, `price`, `brand`…
     *
     * @return array<string, string|int|float|null>
     */
    public function variables(Model $model): array;

    /**
     * Main image (Open Graph fallback, schema image).
     */
    public function image(Model $model): ?string;

    /**
     * `website`, `article` or `product`.
     */
    public function ogType(Model $model): string;

    public function publishedAt(Model $model): ?CarbonInterface;

    public function modifiedAt(Model $model): ?CarbonInterface;

    /**
     * A taxonomy page with nothing in it (noindex when "empty taxonomy pages" is on).
     */
    public function isEmpty(Model $model): bool;

    /**
     * Breadcrumb trail after the home page, the entry itself last.
     *
     * @return array<int, array{name: string, url: string}>
     */
    public function breadcrumbs(Model $model): array;

    /**
     * JSON-LD nodes for the entry besides WebSite, Organization, WebPage and BreadcrumbList.
     *
     * @return array<int, array<string, mixed>>
     */
    public function schema(Model $model, GraphContext $context): array;

    /**
     * Public entries of a type (key in the registry) for the sitemap, in a stable order.
     *
     * @return Builder<covariant Model>
     */
    public function sitemapQuery(string $type): Builder;

    /**
     * Images listed with the entry in the sitemap.
     *
     * @return array<int, string>
     */
    public function sitemapImages(Model $model): array;
}
