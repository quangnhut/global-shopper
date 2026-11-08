<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| OnePay domestic cards (ATM / Napas)
|--------------------------------------------------------------------------
|
| Merged into `shopper.payment.drivers.onepay`. Credentials come from the
| OnePay merchant contract. With ONEPAY_SANDBOX=true and no credentials the
| public OnePay test merchant is used, so the flow can be tried locally.
|
| Give OnePay the IPN URL `{APP_URL}/api/onepay/ipn`: it is how payments are
| confirmed (the browser return alone is never trusted).
|
*/

$sandbox = (bool) env('ONEPAY_SANDBOX', env('PAYMENT_SANDBOX', false));

return [
    'enabled' => (bool) env('PAYMENT_ONEPAY_ENABLED', true),
    'sandbox' => $sandbox,

    'credentials' => [
        'merchant_id' => env('ONEPAY_MERCHANT_ID', $sandbox ? 'TESTONEPAY' : null),
        'access_code' => env('ONEPAY_ACCESS_CODE', $sandbox ? '6BEB2546' : null),
        'hash_secret' => env('ONEPAY_HASH_SECRET', $sandbox ? '6D0870CDE5F24F34F3915FB0045120DB' : null),
        // QueryDR (payment status lookup) account.
        'query_user' => env('ONEPAY_QUERY_USER', $sandbox ? 'op01' : null),
        'query_password' => env('ONEPAY_QUERY_PASSWORD', $sandbox ? 'op123456' : null),
    ],

    'urls' => [
        // Checked against the sandbox on 2026-11-08; confirm the live URLs with OnePay when signing the contract.
        'payment' => env('ONEPAY_PAYMENT_URL', $sandbox ? 'https://mtf.onepay.vn/paygate/vpcpay.op' : 'https://onepay.vn/paygate/vpcpay.op'),
        'query' => env('ONEPAY_QUERY_URL', $sandbox ? 'https://mtf.onepay.vn/msp/api/v1/vpc/invoices/queries' : 'https://onepay.vn/msp/api/v1/vpc/invoices/queries'),
    ],

    // Card types offered on the OnePay page: DOMESTIC = ATM / Napas cards only (decided 2026-11-08).
    'card_list' => env('ONEPAY_CARD_LIST', 'DOMESTIC'),

    // Shown on the OnePay page.
    'title' => env('ONEPAY_TITLE', env('APP_NAME', 'Shopper')),

    'routes' => [
        'return' => 'onepay/return',
        // The storefront adds its locale middleware so messages come back in the shop's language.
        'return_middleware' => ['web'],
        'ipn' => 'api/onepay/ipn',
    ],
];
