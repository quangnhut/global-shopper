<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shopper\Storefront\Http\Controllers\CartApiController;
use Shopper\Storefront\Http\Controllers\StorefrontApiController;
use Shopper\Storefront\Http\Middleware\UseCartToken;

Route::prefix(config('shopper.storefront.api.prefix', 'api').'/storefront')
    ->middleware([...config('shopper.storefront.api.middleware', ['api']), 'shopper-storefront.locale'])
    ->as('shopper.storefront.api.')
    ->group(function (): void {
        Route::get('home', [StorefrontApiController::class, 'home'])->name('home');
        Route::get('search', [StorefrontApiController::class, 'search'])->name('search');

        Route::middleware(UseCartToken::class)->group(function (): void {
            Route::get('cart', [CartApiController::class, 'show'])->name('cart');
            Route::post('cart/lines', [CartApiController::class, 'add'])->middleware('throttle:30,1,sf-api-cart-lines')->name('cart.lines.store');
            Route::patch('cart/lines/{line}', [CartApiController::class, 'update'])->whereNumber('line')->name('cart.lines.update');
            Route::delete('cart/lines/{line}', [CartApiController::class, 'destroy'])->whereNumber('line')->name('cart.lines.destroy');
            Route::get('checkout/payment-methods', [CartApiController::class, 'paymentMethods'])->name('checkout.payment-methods');
            Route::post('checkout', [CartApiController::class, 'checkout'])->middleware('throttle:5,1,sf-api-checkout')->name('checkout');
        });
    });
