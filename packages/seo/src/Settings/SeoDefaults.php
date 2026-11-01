<?php

declare(strict_types=1);

namespace Shopper\Seo\Settings;

use Shopper\Seo\Registry\SeoableRegistry;

/**
 * Defaults close to Yoast/Rank Math out of the box.
 */
final class SeoDefaults
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'general' => [
                'site_name' => null,
                'tagline' => null,
                'separator' => '-',
                'site_icon_upload_id' => null,
                'home_title' => '%sitename% %sep% %tagline%',
                'home_description' => null,
                'discourage_search_engines' => false,
            ],
            'titles' => self::titles(),
            'social' => [
                'default_image_upload_id' => null,
                'facebook_url' => null,
                'facebook_app_id' => null,
                'twitter_handle' => null,
                'twitter_card' => 'summary_large_image',
                'profiles' => [],
            ],
            'schema' => [
                'entity' => 'organization',
                'name' => null,
                'logo_upload_id' => null,
                'email' => null,
                'phone' => null,
                'local_business_type' => null,
                'opening_hours' => [],
                'search_url' => null,
                'article_type' => 'BlogPosting',
            ],
            'indexing' => [
                'noindex_date_archives' => true,
                'noindex_author_archives' => true,
                'noindex_search' => true,
                'noindex_paginated' => false,
                'noindex_empty_terms' => true,
                'noindex_paths' => [
                    ['pattern' => '/cart*', 'nofollow' => true],
                    ['pattern' => '/checkout*', 'nofollow' => true],
                    ['pattern' => '/account*', 'nofollow' => true],
                ],
            ],
            'tracking' => [
                'gtm_id' => null,
                'ga4_id' => null,
                'verification_google' => null,
                'verification_bing' => null,
                'verification_yandex' => null,
                'verification_pinterest' => null,
                'verification_facebook' => null,
                'custom_head' => null,
                'custom_body_open' => null,
                'custom_body_close' => null,
            ],
            'robots' => [
                'content' => "User-agent: *\nDisallow: /".mb_trim((string) config('shopper.admin.prefix', 'cpanel'), '/')."/\n",
            ],
        ];
    }

    /**
     * Templates for every registered type plus the special pages.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function titles(): array
    {
        $titles = [];

        foreach (array_keys(resolve(SeoableRegistry::class)->all()) as $type) {
            $titles[$type] = [
                'title' => '%title% %sep% %sitename%',
                'description' => in_array($type, ['post', 'page', 'product'], true) ? '%excerpt%' : '%description%',
                'noindex' => false,
                'sitemap' => true,
                'schema_type' => match ($type) {
                    'post' => 'BlogPosting',
                    'page' => 'WebPage',
                    'product' => 'Product',
                    default => 'CollectionPage',
                },
                'og_image_upload_id' => null,
            ];
        }

        $titles['search'] = ['title' => '%search% %sep% %sitename%', 'description' => null, 'noindex' => true, 'sitemap' => false];
        $titles['not_found'] = ['title' => '%notfound% %sep% %sitename%', 'description' => null, 'noindex' => true, 'sitemap' => false];

        return $titles;
    }
}
