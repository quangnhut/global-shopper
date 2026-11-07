<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shopper\Storefront\Http\Controllers\StorefrontApiController;

Route::prefix(config('shopper.storefront.api.prefix', 'api').'/storefront')
    ->middleware([...config('shopper.storefront.api.middleware', ['api']), 'shopper-storefront.locale'])
    ->as('shopper.storefront.api.')
    ->group(function (): void {
        Route::get('home', [StorefrontApiController::class, 'home'])->name('home');
        Route::get('search', [StorefrontApiController::class, 'search'])->name('search');
    });
