<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Content types
    |--------------------------------------------------------------------------
    |
    | The content types that get SEO templates (Titles & meta), the per-entry
    | SEO panel, head output and sitemaps. Other packages add theirs at
    | runtime (the CMS registers posts, pages, categories and tags). Labels
    | are translation keys; `source` describes an entry (URL, variables,
    | schema, sitemap query).
    |
    */

    'types' => [
        'product' => [
            'label' => 'shopper-seo::types.product',
            'model' => 'product',
            'source' => Shopper\Seo\Sources\ProductSource::class,
        ],
        'category' => [
            'label' => 'shopper-seo::types.category',
            'model' => 'category',
            'source' => Shopper\Seo\Sources\CategorySource::class,
        ],
        'brand' => [
            'label' => 'shopper-seo::types.brand',
            'model' => 'brand',
            'source' => Shopper\Seo\Sources\BrandSource::class,
        ],
        'collection' => [
            'label' => 'shopper-seo::types.collection',
            'model' => 'collection',
            'source' => Shopper\Seo\Sources\CollectionSource::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storefront URLs
    |--------------------------------------------------------------------------
    |
    | Shopper is headless: canonical URLs, sitemaps and Open Graph tags point
    | to the storefront. `frontend_url` defaults to APP_URL. The paths of shop
    | entities use `{slug}`; the CMS builds its own (Settings → Permalinks).
    |
    */

    'frontend_url' => env('SHOPPER_SEO_FRONTEND_URL'),

    'paths' => [
        'product' => '/products/{slug}',
        'category' => '/categories/{slug}',
        'brand' => '/brands/{slug}',
        'collection' => '/collections/{slug}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Output cache
    |--------------------------------------------------------------------------
    |
    | Resolved SEO data and sitemaps are cached (seconds, 0 disables). Any change
    | to SEO settings, SEO meta or a registered model drops the whole cache.
    | Prices and stock (Product schema) can lag behind by up to this TTL.
    |
    */

    'cache_ttl' => (int) env('SHOPPER_SEO_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Public routes
    |--------------------------------------------------------------------------
    |
    | `/sitemap.xml`, `/sitemap-{type}-{page}.xml`, `/sitemap.xsl` and
    | `/robots.txt` at the root of the app (delete a static public/robots.txt,
    | or the route never runs), plus the headless API under `api.prefix`.
    |
    */

    'routes' => [
        'enabled' => (bool) env('SHOPPER_SEO_ROUTES', true),
        'middleware' => [],
    ],

    'api' => [
        'enabled' => true,
        'prefix' => 'api',
        'middleware' => ['api', 'throttle:120,1'],
    ],

    'sitemaps' => [
        // URLs per sitemap file (the protocol allows 50,000).
        'per_page' => 1000,
        // Rebuild sitemaps in a queued job after changes instead of on the next request.
        'queue' => (bool) env('SHOPPER_SEO_SITEMAP_QUEUE', false),
        // Link `/sitemap.xsl` so browsers show a table. Off by default: browsers are removing XSLT
        // (Chrome warns about it), and crawlers ignore the stylesheet anyway.
        'stylesheet' => (bool) env('SHOPPER_SEO_SITEMAP_XSL', false),
    ],

];
