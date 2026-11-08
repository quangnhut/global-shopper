<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shopper\Storefront\Http\Controllers\Auth\LoginController;
use Shopper\Storefront\Http\Controllers\Auth\PasswordResetController;
use Shopper\Storefront\Http\Controllers\Auth\RegisterController;
use Shopper\Storefront\Http\Controllers\CartController;
use Shopper\Storefront\Http\Controllers\CategoryController;
use Shopper\Storefront\Http\Controllers\CheckoutController;
use Shopper\Storefront\Http\Controllers\HomeController;
use Shopper\Storefront\Http\Controllers\LegalController;
use Shopper\Storefront\Http\Controllers\ProductController;
use Shopper\Storefront\Http\Controllers\SearchController;

// Only loaded when `shopper.storefront.routes.enabled` is true. Paths come from `shopper.storefront.paths`.
$path = fn (string $page, string $default): string => (string) config("shopper.storefront.paths.{$page}", $default);

Route::middleware([...config('shopper.storefront.routes.middleware', ['web']), 'shopper-storefront.locale'])
    ->as('shopper.storefront.')
    ->group(function () use ($path): void {
        Route::get('/', HomeController::class)->name('home');
        Route::get($path('search', '/search'), SearchController::class)->name('search');
        Route::get(str_replace('{slug}', '{legal:slug}', $path('legal', '/policies/{slug}')), LegalController::class)->name('legal');

        // Shop entity paths are shared with the SEO module (sitemaps, canonical URLs).
        $categoryPath = (string) config('shopper.seo.paths.category', '/categories/{slug}');
        Route::get($categoryPath, CategoryController::class)->where('slug', '[^/]+')->name('category');
        Route::get((string) config('shopper.seo.paths.product', '/products/{slug}'), ProductController::class)->where('slug', '[^/]+')->name('product');

        Route::get($path('cart', '/cart'), [CartController::class, 'show'])->name('cart');
        Route::post('cart/lines', [CartController::class, 'store'])->middleware('throttle:30,1,sf-cart-lines-store')->name('cart.lines.store');
        Route::get($path('checkout', '/checkout'), [CheckoutController::class, 'show'])->name('checkout');
        Route::get('orders/{number}/thank-you', [CheckoutController::class, 'thanks'])->name('order.thanks');
        Route::post('orders/{number}/onepay', [CheckoutController::class, 'payWithOnePay'])->middleware('throttle:10,1,sf-onepay-pay')->name('onepay.pay');

        Route::middleware('guest')->group(function () use ($path): void {
            Route::get($path('login', '/login'), [LoginController::class, 'create'])->name('login');
            Route::post($path('login', '/login'), [LoginController::class, 'store'])->middleware('throttle:10,1,sf-login-store')->name('login.store');
            Route::get($path('register', '/register'), [RegisterController::class, 'create'])->name('register');
            Route::post($path('register', '/register'), [RegisterController::class, 'store'])->middleware('throttle:5,1,sf-register-store')->name('register.store');
            Route::get($path('password_request', '/forgot-password'), [PasswordResetController::class, 'request'])->name('password.request');
            Route::post($path('password_request', '/forgot-password'), [PasswordResetController::class, 'email'])->middleware('throttle:5,1,sf-password-email')->name('password.email');
            Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
            Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1,sf-password-update')->name('password.update');
        });

        Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
    });
