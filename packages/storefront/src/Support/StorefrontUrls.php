<?php

declare(strict_types=1);

namespace Shopper\Storefront\Support;

use Shopper\Seo\Support\SeoUrls;

/**
 * URLs of the storefront's own pages (`shopper.storefront.paths`), on the storefront base URL.
 */
final class StorefrontUrls
{
    public static function base(): string
    {
        return mb_rtrim((string) (config('shopper.storefront.url') ?: SeoUrls::base()), '/');
    }

    public static function home(): string
    {
        return self::base().'/';
    }

    /**
     * @param  array<string, string>  $replace  e.g. `['slug' => 'refund']`
     */
    public static function to(string $page, array $replace = []): string
    {
        $path = (string) config("shopper.storefront.paths.{$page}", "/{$page}");

        foreach ($replace as $key => $value) {
            $path = str_replace('{'.$key.'}', rawurlencode($value), $path);
        }

        return self::base().'/'.mb_ltrim($path, '/');
    }
}
