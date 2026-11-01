<?php

declare(strict_types=1);

namespace Shopper\Seo\Http\Controllers;

use Illuminate\Http\Response;
use Shopper\Seo\Sitemaps\SitemapBuilder;
use Shopper\Seo\Support\RobotsTxt;

/**
 * `/sitemap.xml`, `/sitemap-{type}-{page}.xml`, `/sitemap.xsl` and `/robots.txt` (also under the API prefix).
 */
final class SitemapController
{
    private const array HEADERS = [
        'Content-Type' => 'application/xml; charset=UTF-8',
        'Cache-Control' => 'public, max-age=3600',
        // Sitemaps are for crawlers, not for search results.
        'X-Robots-Tag' => 'noindex, follow',
    ];

    public function index(SitemapBuilder $sitemaps): Response
    {
        return response($sitemaps->index(), 200, self::HEADERS);
    }

    public function show(SitemapBuilder $sitemaps, string $type, int $page): Response
    {
        $xml = $sitemaps->page($type, $page);

        abort_if($xml === null, 404);

        return response($xml, 200, self::HEADERS);
    }

    public function stylesheet(): Response
    {
        // The XML declaration cannot live in a Blade file (`<?` is PHP's open tag).
        $xsl = '<?xml version="1.0" encoding="UTF-8"?>'."\n".view('shopper-seo::sitemap-xsl')->render();

        return response($xsl, 200, [
            'Content-Type' => 'text/xsl; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function robots(RobotsTxt $robots): Response
    {
        return response($robots->render(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
