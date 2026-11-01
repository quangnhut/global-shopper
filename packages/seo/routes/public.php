<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shopper\Seo\Http\Controllers\SitemapController;

// Root routes of the app (`shopper.seo.routes.enabled`). A static public/robots.txt would win, delete it.
Route::middleware(config('shopper.seo.routes.middleware', []))->group(function (): void {
    Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('shopper.seo.sitemap');
    Route::get('sitemap-{type}-{page}.xml', [SitemapController::class, 'show'])
        ->where(['type' => '[a-z0-9_]+', 'page' => '[0-9]+'])
        ->name('shopper.seo.sitemap.page');
    Route::get('sitemap.xsl', [SitemapController::class, 'stylesheet'])->name('shopper.seo.sitemap.xsl');
    Route::get('robots.txt', [SitemapController::class, 'robots'])->name('shopper.seo.robots');
});
