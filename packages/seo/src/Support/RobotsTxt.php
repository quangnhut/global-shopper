<?php

declare(strict_types=1);

namespace Shopper\Seo\Support;

use Shopper\Seo\Settings\SeoSettings;

/**
 * The robots.txt body: the text edited in SEO settings plus the sitemap line, or a full block while
 * "Discourage search engines" is on.
 */
final class RobotsTxt
{
    public function __construct(private readonly SeoSettings $settings) {}

    public function render(): string
    {
        if ($this->settings->get('general.discourage_search_engines')) {
            return "User-agent: *\nDisallow: /\n";
        }

        $body = mb_rtrim(str_replace("\r\n", "\n", (string) $this->settings->get('robots.content')));
        $sitemap = SeoUrls::absolute('/sitemap.xml');

        if (! str_contains($body, $sitemap)) {
            $body .= ($body === '' ? '' : "\n\n").'Sitemap: '.$sitemap;
        }

        return $body."\n";
    }
}
