<?php

declare(strict_types=1);

namespace Shopper\Cms\Permalinks;

/**
 * First path segments that belong to the application, never to content.
 */
final class ReservedPaths
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_unique(array_filter([
            mb_trim(shopper()->prefix(), '/'),
            mb_trim((string) config('shopper.cms.api.prefix', 'api'), '/'),
            mb_trim((string) config('shopper.cms.preview_path', 'cms-preview'), '/'),
            'storage', 'vendor', 'livewire', 'js', 'css', 'fonts', 'build', 'cms-unlock',
            'robots.txt', 'sitemap.xml', 'favicon.ico',
            ...(array) config('shopper.cms.permalinks.reserved', []),
        ])));
    }

    public static function contains(string $segment): bool
    {
        return in_array($segment, self::all(), true);
    }
}
