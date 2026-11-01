<?php

declare(strict_types=1);

namespace Shopper\Seo\Sources;

use Illuminate\Database\Eloquent\Model;
use Shopper\Seo\Contracts\PathResolver;
use Shopper\Seo\Registry\SeoableRegistry;
use Shopper\Seo\Support\SeoUrls;

/**
 * Matches storefront paths of shop entities (`shopper.seo.paths`, e.g. `/products/{slug}`).
 */
final class ShopPathResolver implements PathResolver
{
    public function __construct(private readonly SeoableRegistry $registry) {}

    public function resolve(string $path): ?Model
    {
        $path = mb_rtrim(SeoUrls::path($path), '/');

        foreach ((array) config('shopper.seo.paths', []) as $type => $pattern) {
            if (! $this->registry->has((string) $type)) {
                continue;
            }

            $regex = '#^'.str_replace('\{slug\}', '(?<slug>[^/]+)', preg_quote(mb_rtrim((string) $pattern, '/'), '#')).'$#u';

            if (preg_match($regex, $path, $matches) === 1) {
                $model = $this->registry->model((string) $type)::query()->where('slug', rawurldecode($matches['slug']))->first();

                if ($model !== null) {
                    return $model;
                }
            }
        }

        return null;
    }
}
