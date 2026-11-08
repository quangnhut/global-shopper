<?php

declare(strict_types=1);

namespace Tests\OnePay;

use Shopper\OnePay\OnePayServiceProvider;

abstract class TestCase extends \Tests\Admin\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            OnePayServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('shopper.payment.drivers.onepay.credentials', [
            'merchant_id' => 'ONEPAY',
            'access_code' => 'D67342C2',
            'hash_secret' => 'A3EFDFABA8653DF2342E8DAC29B51AF0',
            'query_user' => 'op01',
            'query_password' => 'op123456',
        ]);
        $app['config']->set('shopper.payment.drivers.onepay.urls', [
            'payment' => 'https://mtf.onepay.vn/paygate/vpcpay.op',
            'query' => 'https://mtf.onepay.vn/msp/api/v1/vpc/invoices/queries',
        ]);
    }
}
