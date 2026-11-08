<?php

declare(strict_types=1);

namespace Shopper\Seo;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Shopper\Seo\Contracts\SeoSource;
use Shopper\Seo\Data\SeoData;
use Shopper\Seo\Models\SeoMeta;
use Shopper\Seo\Registry\SeoableRegistry;
use Shopper\Seo\Schema\GraphBuilder;
use Shopper\Seo\Schema\GraphContext;
use Shopper\Seo\Settings\SeoSettings;
use Shopper\Seo\Support\IndexingRules;
use Shopper\Seo\Support\SeoCache;
use Shopper\Seo\Support\SeoFields;
use Shopper\Seo\Support\SeoUrls;
use Shopper\Seo\Support\TemplateRenderer;

/**
 * Turns SEO settings and per-entry SEO meta into what a page outputs (title, description, canonical, robots,
 * Open Graph, Twitter, verification codes, icons, JSON-LD, GTM, custom code).
 *
 * Context keys: `page` (current page of an archive), `last_page` (prev/next links), `search` (search term),
 * `not_found` (404 page), `archive` (`date` or `author`).
 */
final class SeoManager
{
    public const array VERIFICATION_METAS = [
        'verification_google' => 'google-site-verification',
        'verification_bing' => 'msvalidate.01',
        'verification_yandex' => 'yandex-verification',
        'verification_pinterest' => 'p:domain_verify',
        'verification_facebook' => 'facebook-domain-verification',
    ];

    public function __construct(
        private readonly SeoableRegistry $registry,
        private readonly SeoSettings $settings,
        private readonly TemplateRenderer $renderer,
        private readonly GraphBuilder $graph,
        private readonly IndexingRules $rules,
    ) {}

    /**
     * SEO data of an entry, or of a public path (the current request's path when null). Cached until the next change.
     *
     * @param  array<string, mixed>  $context
     */
    public function for(Model|string|null $subject = null, array $context = []): SeoData
    {
        $subject ??= request()->getPathInfo();
        $key = ($subject instanceof Model ? $subject->getMorphClass().':'.$subject->getKey() : 'path:'.$subject)
            .':'.app()->getLocale().':'.json_encode($context);

        return SeoData::fromArray(SeoCache::remember($key, fn (): array => $this->build($subject, $context)->toArray()));
    }

    /**
     * Same as `for()`, without the cache.
     *
     * @param  array<string, mixed>  $context
     */
    public function build(Model|string $subject, array $context = []): SeoData
    {
        if ($subject instanceof Model) {
            return $this->forEntry($subject, $context);
        }

        $path = SeoUrls::path($subject);

        if (! ($context['not_found'] ?? false) && ! filled($context['search'] ?? null)) {
            $entry = $this->resolvePath($path);

            if ($entry !== null) {
                return $this->forEntry($entry, $context);
            }
        }

        return $this->forPath($path, $context);
    }

    /**
     * The public entry behind a path (drafts and disabled entries are never returned).
     */
    public function resolvePath(string $path): ?Model
    {
        foreach ($this->registry->pathResolvers() as $resolver) {
            $model = resolve($resolver)->resolve($path);

            if ($model === null) {
                continue;
            }

            $type = $this->registry->keyFor($model);
            $source = $type === null ? null : $this->registry->source($type);

            return $source?->isPublic($model) ? $model : null;
        }

        return null;
    }

    /**
     * Whether an entry belongs in the sitemap: public, indexable, not excluded.
     */
    public function inSitemap(Model $model, string $type, ?SeoMeta $meta, SeoSource $source): bool
    {
        if (! $source->isPublic($model) || $meta?->exclude_from_sitemap || ! ($this->settings->get("titles.{$type}.sitemap") ?? true)) {
            return false;
        }

        return $this->indexing(
            path: SeoUrls::path($source->url($model)),
            entryIndex: $meta?->robots_index,
            entryFollow: $meta?->robots_follow,
            noindexByDefault: $this->typeNoindex($type, $model, $source, 1),
        )['index'];
    }

    /**
     * Site-wide tracking: GTM container (validated again, it lands in a script), GA4 only without GTM, and the
     * raw custom code (only users with `manage_seo_scripts` can save it).
     *
     * @return array{gtm: ?string, ga4: ?string, custom_head: ?string, custom_body_open: ?string, custom_body_close: ?string}
     */
    public function tracking(): array
    {
        $tracking = $this->settings->group('tracking');
        $gtm = $this->validId($tracking['gtm_id'] ?? null, '/^GTM-[A-Z0-9]+$/');

        return [
            'gtm' => $gtm,
            'ga4' => $gtm === null ? $this->validId($tracking['ga4_id'] ?? null, '/^G-[A-Z0-9]+$/') : null,
            'custom_head' => filled($tracking['custom_head'] ?? null) ? (string) $tracking['custom_head'] : null,
            'custom_body_open' => filled($tracking['custom_body_open'] ?? null) ? (string) $tracking['custom_body_open'] : null,
            'custom_body_close' => filled($tracking['custom_body_close'] ?? null) ? (string) $tracking['custom_body_close'] : null,
        ];
    }

    /**
     * A page past the last one is an empty duplicate: never indexed.
     *
     * @param  array<string, mixed>  $context
     */
    private static function outOfRange(int $page, array $context): bool
    {
        return $page > 1 && isset($context['last_page']) && $page > max(1, (int) $context['last_page']);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function forEntry(Model $model, array $context): SeoData
    {
        $type = $this->registry->keyFor($model) ?? throw new InvalidArgumentException('['.$model::class.'] is not registered with SEO.');
        $source = $this->registry->source($type) ?? throw new InvalidArgumentException("SEO type [{$type}] has no source.");
        $meta = SeoFields::meta($model);
        $template = (array) $this->settings->get("titles.{$type}", []);
        $page = max(1, (int) ($context['page'] ?? 1));
        $variables = [...$this->siteVariables(), ...$source->variables($model), 'page' => $this->pageText($page, $context)];

        $entryTitle = SeoFields::usesColumn($model, 'title') ? $model->getAttribute('seo_title') : $meta?->title;
        $entryDescription = SeoFields::usesColumn($model, 'description') ? $model->getAttribute('seo_description') : $meta?->description;

        $title = $this->title(filled($entryTitle) ? (string) $entryTitle : ($template['title'] ?? null), $variables, $page);
        $description = filled($entryDescription)
            ? $this->renderer->render((string) $entryDescription, $variables, $this->separator())
            : (TemplateRenderer::excerpt($this->renderer->render($template['description'] ?? null, $variables, $this->separator()), 160)
                ?: TemplateRenderer::excerpt((string) ($variables['excerpt'] ?? ''), 160));

        $url = $source->url($model);
        $canonical = filled($meta?->canonical_url) ? SeoUrls::absolute((string) $meta->canonical_url) : SeoUrls::paginated($url, $page);

        $indexing = $this->indexing(
            path: SeoUrls::path($url),
            entryIndex: $meta?->robots_index,
            entryFollow: $meta?->robots_follow,
            noindexByDefault: $this->typeNoindex($type, $model, $source, $page),
            forceNoindex: ! $source->isPublic($model) || self::outOfRange($page, $context),
            flags: (array) ($meta?->robots_advanced['flags'] ?? []),
            maxImagePreview: $meta?->robots_advanced['max_image_preview'] ?? null,
        );

        $image = SeoUrls::upload($meta?->og_image_upload_id)
            ?? $source->image($model)
            ?? SeoUrls::upload($template['og_image_upload_id'] ?? null)
            ?? SeoUrls::upload($this->settings->get('social.default_image_upload_id'));

        $schemaType = (string) ($meta?->schema_type ?: ($template['schema_type'] ?? null) ?: ($type === 'post' ? $this->settings->get('schema.article_type') : 'WebPage'));
        $published = $source->publishedAt($model)?->toIso8601String();
        $modified = $source->modifiedAt($model)?->toIso8601String();
        $breadcrumbs = $source->breadcrumbs($model);

        if ($breadcrumbs !== [] && filled($meta?->breadcrumb_title)) {
            $breadcrumbs[array_key_last($breadcrumbs)]['name'] = (string) $meta->breadcrumb_title;
        }

        $graphContext = new GraphContext(
            url: $canonical,
            name: $title,
            description: $description ?: null,
            image: $image,
            language: GraphBuilder::language(),
            websiteId: GraphBuilder::websiteId(),
            publisherId: $this->graph->publisherId(),
            webpageId: $canonical.'#webpage',
            breadcrumbId: $breadcrumbs === [] ? null : $canonical.'#breadcrumb',
            schemaType: $schemaType,
            datePublished: $published,
            dateModified: $modified,
        );

        $ogType = $source->ogType($model);
        $social = [
            'og_title' => $meta?->og_title ?: $title,
            'og_description' => $meta?->og_description ?: $description,
            'twitter_title' => $meta?->twitter_title,
            'twitter_description' => $meta?->twitter_description,
            'twitter_image' => SeoUrls::upload($meta?->twitter_image_upload_id),
        ];

        return $this->data(
            type: $type,
            title: $title,
            description: $description ?: null,
            canonical: $canonical,
            indexing: $indexing,
            image: $image,
            ogType: $ogType,
            social: $social,
            articleTimes: $ogType === 'article' ? [$published, $modified] : [],
            links: $this->paginationLinks($canonical === SeoUrls::paginated($url, $page) ? $url : null, $page, $context),
            jsonLd: $this->graph->build(
                $graphContext,
                $breadcrumbs,
                $source->schema($model, $graphContext),
                $meta?->schema_custom,
                isHome: SeoUrls::path($url) === '/',
            ),
        );
    }

    /**
     * Home page, search results, 404 and paths without an entry (`/cart`, `/checkout`…).
     *
     * @param  array<string, mixed>  $context
     */
    private function forPath(string $path, array $context): SeoData
    {
        $page = max(1, (int) ($context['page'] ?? 1));
        $variables = [...$this->siteVariables(), 'page' => $this->pageText($page, $context), 'title' => $context['title'] ?? null];
        $indexing = $this->settings->group('indexing');

        [$type, $titleTemplate, $description, $noindex] = match (true) {
            (bool) ($context['not_found'] ?? false) => [
                'not_found',
                $this->settings->get('titles.not_found.title'),
                null,
                true,
            ],
            filled($context['search'] ?? null) => [
                'search',
                $this->settings->get('titles.search.title'),
                null,
                (bool) $indexing['noindex_search'] || (bool) $this->settings->get('titles.search.noindex'),
            ],
            $path === '/' => [
                'home',
                $this->settings->get('general.home_title'),
                $this->settings->get('general.home_description') ?: $this->settings->get('general.tagline'),
                false,
            ],
            default => [
                'path',
                '%title% %sep% %sitename%',
                null,
                (($context['archive'] ?? null) === 'date' && $indexing['noindex_date_archives'])
                    || (($context['archive'] ?? null) === 'author' && $indexing['noindex_author_archives'])
                    // Private pages (cart, checkout, order confirmation) ask for it.
                    || (bool) ($context['noindex'] ?? false),
            ],
        };

        $variables['search'] = $context['search'] ?? null;
        $variables['notfound'] = __('shopper-seo::output.not_found');

        $title = $this->title($titleTemplate, $variables, $page);
        $description = filled($description) ? $this->renderer->render((string) $description, $variables, $this->separator()) : null;
        $url = SeoUrls::absolute($path);
        $canonical = $type === 'not_found' || $type === 'search' ? null : SeoUrls::paginated($url, $page);

        $indexing = $this->indexing(
            path: $path,
            entryIndex: null,
            entryFollow: null,
            noindexByDefault: $noindex || ($page > 1 && $indexing['noindex_paginated']),
            forceNoindex: $type === 'not_found' || self::outOfRange($page, $context),
        );

        $image = SeoUrls::upload($this->settings->get('social.default_image_upload_id'));
        $graphContext = new GraphContext(
            url: $canonical ?? $url,
            name: $title,
            description: $description,
            image: $image,
            language: GraphBuilder::language(),
            websiteId: GraphBuilder::websiteId(),
            publisherId: $this->graph->publisherId(),
            webpageId: ($canonical ?? $url).'#webpage',
            schemaType: $type === 'search' ? 'SearchResultsPage' : 'WebPage',
        );

        return $this->data(
            type: $type,
            title: $title,
            description: $description,
            canonical: $canonical,
            indexing: $indexing,
            image: $image,
            ogType: 'website',
            social: ['og_title' => $title, 'og_description' => $description],
            links: $canonical === null ? [] : $this->paginationLinks($url, $page, $context),
            jsonLd: $type === 'not_found' ? null : $this->graph->build($graphContext, isHome: $type === 'home'),
        );
    }

    /**
     * @param  array{index: bool, follow: bool, robots: string}  $indexing
     * @param  array<string, string|null>  $social
     * @param  array<int, string|null>  $articleTimes
     * @param  array<int, array<string, string>>  $links
     * @param  array<string, mixed>|null  $jsonLd
     */
    private function data(
        string $type,
        string $title,
        ?string $description,
        ?string $canonical,
        array $indexing,
        ?string $image,
        string $ogType,
        array $social,
        array $articleTimes = [],
        array $links = [],
        ?array $jsonLd = null,
    ): SeoData {
        $tracking = $this->settings->group('tracking');
        $socialSettings = $this->settings->group('social');
        $twitterHandle = mb_ltrim((string) ($socialSettings['twitter_handle'] ?? ''), '@');

        $meta = array_values(array_filter([
            $description !== null ? ['name' => 'description', 'content' => $description] : null,
            ['name' => 'robots', 'content' => $indexing['robots']],
            ['property' => 'og:locale', 'content' => app()->getLocale() === 'en' ? 'en_US' : str_replace('-', '_', app()->getLocale())],
            ['property' => 'og:type', 'content' => $ogType],
            ['property' => 'og:title', 'content' => (string) $social['og_title']],
            filled($social['og_description'] ?? null) ? ['property' => 'og:description', 'content' => (string) $social['og_description']] : null,
            ['property' => 'og:url', 'content' => $canonical ?? SeoUrls::home()],
            ['property' => 'og:site_name', 'content' => (string) $this->siteVariables()['sitename']],
            $image !== null ? ['property' => 'og:image', 'content' => $image] : null,
            filled($articleTimes[0] ?? null) ? ['property' => 'article:published_time', 'content' => (string) $articleTimes[0]] : null,
            filled($articleTimes[1] ?? null) ? ['property' => 'article:modified_time', 'content' => (string) $articleTimes[1]] : null,
            $ogType === 'article' && filled($socialSettings['facebook_url'] ?? null) ? ['property' => 'article:publisher', 'content' => (string) $socialSettings['facebook_url']] : null,
            filled($socialSettings['facebook_app_id'] ?? null) ? ['property' => 'fb:app_id', 'content' => (string) $socialSettings['facebook_app_id']] : null,
            ['name' => 'twitter:card', 'content' => (string) ($socialSettings['twitter_card'] ?? 'summary_large_image')],
            $twitterHandle !== '' ? ['name' => 'twitter:site', 'content' => '@'.$twitterHandle] : null,
            filled($social['twitter_title'] ?? null) ? ['name' => 'twitter:title', 'content' => (string) $social['twitter_title']] : null,
            filled($social['twitter_description'] ?? null) ? ['name' => 'twitter:description', 'content' => (string) $social['twitter_description']] : null,
            filled($social['twitter_image'] ?? null) ? ['name' => 'twitter:image', 'content' => (string) $social['twitter_image']] : null,
            ...$this->verificationMetas($tracking),
        ]));

        $code = $this->tracking();

        return new SeoData(
            type: $type,
            title: $title,
            description: $description,
            // Like Yoast, noindex pages get no canonical: the two signals contradict each other.
            canonical: $indexing['index'] ? $canonical : null,
            robots: $indexing['robots'],
            meta: $meta,
            links: [
                ...($indexing['index'] && $canonical !== null ? [['rel' => 'canonical', 'href' => $canonical]] : []),
                ...$links,
                ...$this->iconLinks(),
            ],
            jsonLd: $jsonLd,
            gtm: $code['gtm'],
            ga4: $code['ga4'],
            customHead: $code['custom_head'],
            customBodyOpen: $code['custom_body_open'],
            customBodyClose: $code['custom_body_close'],
        );
    }

    /**
     * Robots resolution: entry value → URL pattern rules → type/context defaults; "discourage search engines" wins over all.
     *
     * @param  array<int, string>  $flags  noarchive, nosnippet, noimageindex
     * @return array{index: bool, follow: bool, robots: string}
     */
    private function indexing(
        string $path,
        ?bool $entryIndex,
        ?bool $entryFollow,
        bool $noindexByDefault,
        bool $forceNoindex = false,
        array $flags = [],
        ?string $maxImagePreview = null,
    ): array {
        if ($this->settings->get('general.discourage_search_engines')) {
            return ['index' => false, 'follow' => false, 'robots' => 'noindex, nofollow'];
        }

        $rule = $this->rules->match($path);
        $index = ! $forceNoindex && ($entryIndex ?? ($rule === null ? ! $noindexByDefault : false));
        $follow = $entryFollow ?? ! ($rule['nofollow'] ?? false);

        $parts = [$index ? 'index' : 'noindex', $follow ? 'follow' : 'nofollow'];

        foreach (array_intersect($flags, ['noarchive', 'nosnippet', 'noimageindex']) as $flag) {
            $parts[] = $flag;
        }

        if ($index) {
            $parts[] = 'max-image-preview:'.(in_array($maxImagePreview, ['none', 'standard', 'large'], true) ? $maxImagePreview : 'large');

            if (! in_array('nosnippet', $flags, true)) {
                $parts[] = 'max-snippet:-1';
            }

            $parts[] = 'max-video-preview:-1';
        }

        return ['index' => $index, 'follow' => $follow, 'robots' => implode(', ', $parts)];
    }

    private function typeNoindex(string $type, Model $model, SeoSource $source, int $page): bool
    {
        $indexing = $this->settings->group('indexing');

        return (bool) $this->settings->get("titles.{$type}.noindex")
            || ($page > 1 && $indexing['noindex_paginated'])
            || ($indexing['noindex_empty_terms'] && $source->isEmpty($model));
    }

    /**
     * Templates get " - Page 2 of 5" appended on page ≥ 2 when they do not place `%page%` themselves,
     * so every page of an archive has a unique title.
     *
     * @param  array<string, string|int|float|null>  $variables
     */
    private function title(?string $template, array $variables, int $page): string
    {
        $template = filled($template) ? (string) $template : '%title% %sep% %sitename%';

        if ($page > 1 && ! str_contains($template, '%page%')) {
            $template .= ' %sep% %page%';
        }

        return $this->renderer->render($template, $variables, $this->separator()) ?: (string) $variables['sitename'];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function pageText(int $page, array $context): ?string
    {
        if ($page < 2) {
            return null;
        }

        return isset($context['last_page']) && ! self::outOfRange($page, $context)
            ? __('shopper-seo::output.page_of', ['page' => $page, 'total' => (int) $context['last_page']])
            : __('shopper-seo::output.page', ['page' => $page]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, string>>
     */
    private function paginationLinks(?string $url, int $page, array $context): array
    {
        if ($url === null || ! isset($context['last_page'])) {
            return [];
        }

        $last = (int) $context['last_page'];

        return array_values(array_filter([
            $page > 1 ? ['rel' => 'prev', 'href' => SeoUrls::paginated($url, $page - 1)] : null,
            $page < $last ? ['rel' => 'next', 'href' => SeoUrls::paginated($url, $page + 1)] : null,
        ]));
    }

    /**
     * Favicons, Apple touch icon and web manifest generated from the site icon (plan 07).
     *
     * @return array<int, array<string, string>>
     */
    private function iconLinks(): array
    {
        $icons = (array) $this->settings->get('general.site_icons', []);

        if ($icons === []) {
            return [];
        }

        $links = [];

        foreach ([32, 16] as $size) {
            if (isset($icons[$size])) {
                $links[] = ['rel' => 'icon', 'href' => (string) SeoUrls::disk($icons[$size]), 'sizes' => "{$size}x{$size}", 'type' => 'image/png'];
            }
        }

        if (isset($icons[180])) {
            $links[] = ['rel' => 'apple-touch-icon', 'href' => (string) SeoUrls::disk($icons[180]), 'sizes' => '180x180'];
        }

        $links[] = ['rel' => 'manifest', 'href' => (string) SeoUrls::disk('seo/icons/site.webmanifest')];

        return $links;
    }

    /**
     * @param  array<string, mixed>  $tracking
     * @return array<int, array<string, string>>
     */
    private function verificationMetas(array $tracking): array
    {
        $metas = [];

        foreach (self::VERIFICATION_METAS as $key => $name) {
            $code = $tracking[$key] ?? null;

            // Validated on save; checked again because the value lands in an attribute.
            if (is_string($code) && preg_match('/^[A-Za-z0-9_\-=.]+$/', $code) === 1) {
                $metas[] = ['name' => $name, 'content' => $code];
            }
        }

        return $metas;
    }

    private function validId(mixed $value, string $pattern): ?string
    {
        return is_string($value) && preg_match($pattern, $value) === 1 ? $value : null;
    }

    /**
     * @return array{sitename: string, tagline: ?string, currentyear: string}
     */
    private function siteVariables(): array
    {
        return [
            'sitename' => (string) ($this->settings->get('general.site_name') ?: shopper_setting('name') ?: config('app.name')),
            'tagline' => $this->settings->get('general.tagline'),
            'currentyear' => now()->format('Y'),
        ];
    }

    private function separator(): string
    {
        return (string) ($this->settings->get('general.separator') ?: '-');
    }
}
