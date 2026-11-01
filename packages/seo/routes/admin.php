<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shopper\Seo\Livewire\Pages;

Route::prefix('seo')->as('seo.')->group(function (): void {
    Route::get('settings', Pages\SeoSettingsPage::class)->name('settings');
    Route::get('content', Pages\ContentSeo::class)->name('content');
});
