<?php

declare(strict_types=1);

namespace Shopper\Storefront\Support;

use Illuminate\Support\Facades\Cache;
use Shopper\Storefront\Actions\BuildHomeSectionsAction;
use Shopper\Storefront\Actions\BuildLayoutAction;
use Shopper\Storefront\Data\HomePageData;
use Shopper\Storefront\Models\HomeSection;

/**
 * Cached home page data, per locale. Every entry carries the current version number, so `flush()`
 * invalidates all locales at once on any cache store (no tags needed).
 */
final class StorefrontCache
{
    private const string VERSION_KEY = 'shopper-storefront:version';

    /** Bump when the cached data classes change shape, so entries cached by older code are never read back. */
    private const int SCHEMA = 2;

    public static function home(): HomePageData
    {
        return Cache::remember(
            self::key('home'),
            self::ttl(),
            function (): HomePageData {
                $layout = resolve(BuildLayoutAction::class)->execute();

                return new HomePageData($layout['header'], resolve(BuildHomeSectionsAction::class)->execute(), $layout['footer']);
            },
        );
    }

    /**
     * Header and footer alone, for pages other than the home page.
     *
     * @return array{header: \Shopper\Storefront\Data\HeaderData, footer: \Shopper\Storefront\Data\FooterData}
     */
    public static function layout(): array
    {
        return Cache::remember(self::key('layout'), (int) config('shopper.storefront.cache_ttl', 3600), fn (): array => resolve(BuildLayoutAction::class)->execute());
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    /**
     * The configured lifetime, cut short at the next start or end of a scheduled section,
     * so a flash sale appears and disappears on time.
     */
    public static function ttl(): int
    {
        $ttl = max(1, (int) config('shopper.storefront.cache_ttl', 3600));
        $now = now();

        $next = HomeSection::query()
            ->where('is_enabled', true)
            ->where(fn ($query) => $query->where('starts_at', '>', $now)->orWhere('ends_at', '>', $now))
            ->get(['starts_at', 'ends_at'])
            ->flatMap(fn (HomeSection $section): array => [$section->starts_at, $section->ends_at])
            ->filter(fn ($date): bool => $date !== null && $date->greaterThan($now))
            ->min();

        return $next === null ? $ttl : max(1, min($ttl, (int) ceil($now->diffInSeconds($next))));
    }

    private static function key(string $name): string
    {
        return 'shopper-storefront:'.$name.':s'.self::SCHEMA.':'.app()->getLocale().':'.self::version();
    }

    private static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
