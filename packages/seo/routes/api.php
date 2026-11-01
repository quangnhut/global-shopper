<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shopper\Seo\Http\Controllers\SeoController;
use Shopper\Seo\Http\Controllers\SitemapController;

// Headless API: storefronts on another domain proxy their /sitemap.xml and /robots.txt to these.
Route::prefix(mb_trim((string) config('shopper.seo.api.prefix', 'api'), '/').'/seo')
    ->middleware(config('shopper.seo.api.middleware', ['api']))
    ->as('shopper.seo.api.')
    ->group(function (): void {
        Route::get('/', SeoController::class)->name('show');
        Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
        Route::get('sitemap-{type}-{page}.xml', [SitemapController::class, 'show'])
            ->where(['type' => '[a-z0-9_]+', 'page' => '[0-9]+'])
            ->name('sitemap.page');
        Route::get('sitemap.xsl', [SitemapController::class, 'stylesheet'])->name('sitemap.xsl');
        Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');
    });
