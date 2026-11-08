<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shopper\OnePay\Http\Controllers\IpnController;
use Shopper\OnePay\Http\Controllers\ReturnController;

Route::middleware((array) config('shopper.payment.drivers.onepay.routes.return_middleware', ['web']))
    ->get((string) config('shopper.payment.drivers.onepay.routes.return', 'onepay/return'), ReturnController::class)
    ->middleware('throttle:30,1,onepay-return')
    ->name('shopper.onepay.return');

// No session or CSRF: OnePay calls it server to server, every request is signature-checked.
Route::match(['get', 'post'], (string) config('shopper.payment.drivers.onepay.routes.ipn', 'api/onepay/ipn'), IpnController::class)
    ->middleware('throttle:120,1,onepay-ipn')
    ->name('shopper.onepay.ipn');
