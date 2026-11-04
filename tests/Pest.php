<?php

declare(strict_types=1);

use Shopper\Core\Models\Currency;
use Shopper\Core\Models\Setting;

pest()->project()->github('shopperlabs/shopper');

/**
 * @param  array<string>  $currencies
 */
function setupCurrencies(array $currencies = ['USD'], string $defaultCurrency = 'USD'): void
{
    // The app ships with VND only, so tests create the other currencies they exercise.
    $currencies = collect([...$currencies, $defaultCurrency])
        ->unique()
        ->map(fn (string $code): Currency => Currency::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'symbol' => $code, 'format' => '1,0.00', 'exchange_rate' => 1, 'is_enabled' => true],
        ));

    Setting::query()->updateOrCreate(
        ['key' => 'currencies'],
        [
            'value' => $currencies->pluck('id')->toArray(),
            'display_name' => Setting::lockedAttributesDisplayName('currencies'),
            'locked' => true,
        ]
    );

    $currency = Currency::query()->where('code', $defaultCurrency)->first();
    Setting::query()->updateOrCreate(
        ['key' => 'default_currency_id'],
        [
            'value' => $currency->id,
            'display_name' => Setting::lockedAttributesDisplayName('default_currency_id'),
            'locked' => true,
        ]
    );
}
