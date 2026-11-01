<?php

declare(strict_types=1);

namespace Shopper\Cms\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Shopper\Seo\Support\SeoCache;

/**
 * Public API responses, cached per path. Any change to CMS content bumps a version number, which
 * invalidates every cached response at once (works with every cache store, no tags needed).
 */
final class CmsCache
{
    private const string VERSION_KEY = 'shopper-cms:cache-version';

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @param  (Closure(TValue): bool)|null  $shouldCache  e.g. only successful responses
     * @return TValue
     */
    public static function remember(string $key, Closure $callback, ?Closure $shouldCache = null): mixed
    {
        $ttl = (int) config('shopper.cms.api.cache_ttl', 3600);

        if ($ttl <= 0) {
            return $callback();
        }

        $cacheKey = 'shopper-cms:'.(int) Cache::get(self::VERSION_KEY, 1).':'.md5($key);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $value = $callback();

        if ($shouldCache === null || $shouldCache($value)) {
            Cache::put($cacheKey, $value, $ttl);
        }

        return $value;
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 1) + 1);

        // Titles, breadcrumbs and sitemaps depend on CMS content too (term sync, permalink settings).
        SeoCache::flush();
    }
}
