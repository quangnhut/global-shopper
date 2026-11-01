<?php

declare(strict_types=1);

namespace Shopper\Seo\Settings;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Shopper\Core\Models\Setting;
use Shopper\Seo\Support\SeoCache;

/**
 * All site-wide SEO options, stored as one `Setting` row per group (`seo_general`, `seo_titles`, …),
 * merged with defaults and cached until the next save.
 */
final class SeoSettings
{
    public const string CACHE_KEY = 'shopper-seo:settings';

    public const array GROUPS = ['general', 'titles', 'social', 'schema', 'indexing', 'tracking', 'robots'];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $values = null;

    /**
     * Like `array_replace_recursive()`, except that lists (noindex paths, profiles…) are replaced as a
     * whole: merging them item by item would keep entries the user removed.
     *
     * @param  array<array-key, mixed>  $base
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function merge(array $base, array $values): array
    {
        foreach ($values as $key => $value) {
            $base[$key] = is_array($value) && ! array_is_list($value) && is_array($base[$key] ?? null) && ! array_is_list($base[$key])
                ? self::merge($base[$key], $value)
                : $value;
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $this->assertGroup($group);

        return $this->all()[$group];
    }

    /**
     * Dot access, e.g. `get('general.site_name')` or `get('titles.post.title')`.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(string $group, array $values): void
    {
        $this->assertGroup($group);

        Setting::query()->updateOrCreate(
            ['key' => "seo_{$group}"],
            ['value' => self::merge($this->group($group), $values), 'locked' => true],
        );

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->values = null;

        SeoCache::flush();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->values ??= Cache::rememberForever(self::CACHE_KEY, function (): array {
            $stored = Setting::query()
                ->whereIn('key', array_map(fn (string $group): string => "seo_{$group}", self::GROUPS))
                ->pluck('value', 'key');

            $values = [];

            foreach (SeoDefaults::all() as $group => $defaults) {
                $values[$group] = self::merge($defaults, (array) ($stored["seo_{$group}"] ?? []));
            }

            return $values;
        });
    }

    private function assertGroup(string $group): void
    {
        if (! in_array($group, self::GROUPS, true)) {
            throw new InvalidArgumentException("Unknown SEO settings group [{$group}].");
        }
    }
}
