<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Shopper\Core\Models\Country;
use Shopper\Core\Models\Currency;
use Shopper\Core\Models\Setting;

final class StoreSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $country = Country::query()->where('cca2', 'US')->firstOrFail();
        $currencyId = Currency::query()->where('code', 'VND')->valueOrFail('id');

        $settings = [
            'name' => 'Shopper Demo Store',
            'legal_name' => 'Shopper Demo Inc.',
            'email' => 'store@laravelshopper.dev',
            'about' => 'A demo store seeded for local development.',
            'phone_number' => '+1 415 555 0100',
            'street_address' => '548 Market Street',
            'postal_code' => '94104',
            'city' => 'San Francisco',
            'state' => 'CA',
            'country_id' => $country->id,
            'default_currency_id' => $currencyId,
            'currencies' => [$currencyId],
            'facebook_link' => null,
            'instagram_link' => null,
            'twitter_link' => null,
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], [
                'value' => $value,
                'display_name' => Setting::lockedAttributesDisplayName($key),
                'locked' => true,
            ]);
        }

        Cache::flush();
    }
}
