<?php

declare(strict_types=1);

namespace Shopper\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Shopper\Seo\Sitemaps\SitemapBuilder;

/**
 * Rebuilds the sitemap cache after a change (`shopper.seo.sitemaps.queue`), so crawlers never wait for it.
 */
final class GenerateSitemapsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $uniqueFor = 60;

    public function handle(SitemapBuilder $sitemaps): void
    {
        $sitemaps->warm();
    }
}
