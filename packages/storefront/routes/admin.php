<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shopper\Storefront\Livewire\Pages;

Route::prefix('homepage')->as('storefront.')->group(function (): void {
    Route::get('sections', Pages\Sections\Index::class)->name('sections.index');
    Route::get('sections/create', Pages\Sections\Editor::class)->name('sections.create');
    Route::get('sections/{section}/edit', Pages\Sections\Editor::class)->name('sections.edit')->whereNumber('section');
    Route::get('settings', Pages\HeaderFooterSettings::class)->name('settings');
});
