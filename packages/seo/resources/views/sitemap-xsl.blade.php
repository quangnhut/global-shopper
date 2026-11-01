<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <xsl:output method="html" encoding="UTF-8" indent="yes" />
    <xsl:template match="/">
        <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
            <head>
                <meta charset="utf-8" />
                <meta name="viewport" content="width=device-width, initial-scale=1" />
                <meta name="robots" content="noindex, follow" />
                <title>{{ __('shopper-seo::output.sitemap.title') }}</title>
                <style>
                    :root { color-scheme: light dark; }
                    body { margin: 0; padding: 24px 16px; font: 14px/1.5 system-ui, sans-serif; background: Canvas; color: CanvasText; }
                    main { max-width: 1100px; margin: 0 auto; }
                    p { color: GrayText; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { text-align: left; padding: 8px; border-bottom: 1px solid color-mix(in srgb, CanvasText 15%, transparent); }
                    td { word-break: break-all; }
                    a { color: LinkText; }
                </style>
            </head>
            <body>
                <main>
                    <h1>{{ __('shopper-seo::output.sitemap.title') }}</h1>
                    <p>{{ __('shopper-seo::output.sitemap.help') }}</p>
                    <xsl:choose>
                        <xsl:when test="sitemap:sitemapindex">
                            <p>{{ __('shopper-seo::output.sitemap.files') }}: <xsl:value-of select="count(sitemap:sitemapindex/sitemap:sitemap)" /></p>
                            <table>
                                <tr><th>{{ __('shopper-seo::output.sitemap.file') }}</th><th>{{ __('shopper-seo::output.sitemap.lastmod') }}</th></tr>
                                <xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
                                    <tr>
                                        <td><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc" /></a></td>
                                        <td><xsl:value-of select="sitemap:lastmod" /></td>
                                    </tr>
                                </xsl:for-each>
                            </table>
                        </xsl:when>
                        <xsl:otherwise>
                            <p>{{ __('shopper-seo::output.sitemap.urls') }}: <xsl:value-of select="count(sitemap:urlset/sitemap:url)" /></p>
                            <table>
                                <tr><th>URL</th><th>{{ __('shopper-seo::output.sitemap.images') }}</th><th>{{ __('shopper-seo::output.sitemap.lastmod') }}</th></tr>
                                <xsl:for-each select="sitemap:urlset/sitemap:url">
                                    <tr>
                                        <td><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc" /></a></td>
                                        <td><xsl:value-of select="count(image:image)" /></td>
                                        <td><xsl:value-of select="sitemap:lastmod" /></td>
                                    </tr>
                                </xsl:for-each>
                            </table>
                        </xsl:otherwise>
                    </xsl:choose>
                </main>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
