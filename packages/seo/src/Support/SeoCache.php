<?php

declare(strict_types=1);

namespace Shopper\Seo\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Shopper\Seo\Jobs\GenerateSitemapsJob;

/**
 * SEO output and sitemaps, cached under a version number: any change bumps it, which drops everything
 * at once with every cache store (no tags needed).
 */
final class SeoCache
{
    private const string VERSION_KEY = 'shopper-seo:output-version';

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public static function remember(string $key, Closure $callback): mixed
    {
        $ttl = (int) config('shopper.seo.cache_ttl', 3600);

        if ($ttl <= 0) {
            return $callback();
        }

        return Cache::remember(self::key($key), $ttl, $callback);
    }

    public static function key(string $key): string
    {
        return 'shopper-seo:'.self::version().':'.md5($key);
    }

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);

        if (config('shopper.seo.sitemaps.queue') && (int) config('shopper.seo.cache_ttl', 3600) > 0) {
            GenerateSitemapsJob::dispatch()->delay(now()->addSeconds(30));
        }
    }
}
