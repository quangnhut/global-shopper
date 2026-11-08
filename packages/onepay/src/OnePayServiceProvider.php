<?php

declare(strict_types=1);

namespace Shopper\OnePay;

use Illuminate\Support\ServiceProvider;
use Shopper\Payment\Facades\Payment;

final class OnePayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/onepay.php', 'shopper.payment.drivers.onepay');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'shopper-onepay');
        $this->loadRoutesFrom(__DIR__.'/../routes/onepay.php');

        Payment::extend(OnePayDriver::CODE, fn (): OnePayDriver => new OnePayDriver(
            credentials: (array) config('shopper.payment.drivers.onepay.credentials', []),
            urls: (array) config('shopper.payment.drivers.onepay.urls', []),
            title: (string) config('shopper.payment.drivers.onepay.title', 'Shopper'),
            cardList: config('shopper.payment.drivers.onepay.card_list', 'DOMESTIC'),
        ));
    }
}
