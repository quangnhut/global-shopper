<?php

declare(strict_types=1);

namespace Shopper\Seo\Support;

use Shopper\Seo\Settings\SeoSettings;

/**
 * "Noindex URL patterns" from the Indexing tab: paths with `*` wildcards, each optionally `nofollow`.
 */
final class IndexingRules
{
    public function __construct(private readonly SeoSettings $settings) {}

    /**
     * The first pattern matching the path, or null.
     *
     * @return array{pattern: string, nofollow: bool}|null
     */
    public function match(string $path): ?array
    {
        $path = self::normalize(SeoUrls::path($path));

        foreach ((array) $this->settings->get('indexing.noindex_paths', []) as $rule) {
            $pattern = is_array($rule) ? (string) ($rule['pattern'] ?? '') : (string) $rule;

            if ($pattern === '') {
                continue;
            }

            $regex = '#^'.str_replace('\*', '.*', preg_quote(self::normalize($pattern), '#')).'$#iu';

            if (preg_match($regex, $path) === 1) {
                return ['pattern' => $pattern, 'nofollow' => is_array($rule) && (bool) ($rule['nofollow'] ?? false)];
            }
        }

        return null;
    }

    private static function normalize(string $path): string
    {
        $path = '/'.mb_ltrim($path, '/');

        return $path === '/' ? $path : mb_rtrim($path, '/');
    }
}
