<?php

declare(strict_types=1);

namespace Shopper\Cms\Seo;

use Illuminate\Database\Eloquent\Model;
use Shopper\Cms\Permalinks\PermalinkResolver;
use Shopper\Seo\Contracts\PathResolver;

/**
 * Finds posts, pages, categories and tags behind a public path (redirects and gone paths have no entry).
 */
final class CmsPathResolver implements PathResolver
{
    public function __construct(private readonly PermalinkResolver $resolver) {}

    public function resolve(string $path): ?Model
    {
        $resolved = $this->resolver->resolve($path);

        if ($resolved === null || $resolved->isRedirect()) {
            return null;
        }

        return $resolved->model;
    }
}
