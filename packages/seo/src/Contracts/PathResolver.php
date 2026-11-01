<?php

declare(strict_types=1);

namespace Shopper\Seo\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Finds the entry behind a public path (`/products/iphone`, `/2026/10/hello-world`), for the SEO API.
 */
interface PathResolver
{
    public function resolve(string $path): ?Model;
}
