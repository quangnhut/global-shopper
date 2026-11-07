<?php

declare(strict_types=1);

use Shopper\Core\Models\Setting;
use Shopper\Storefront\Settings\StorefrontSettings;

uses(Tests\Storefront\TestCase::class);

function storefrontStoreSetting(string $key, mixed $value): void
{
    Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'locked' => true]);
}

describe(StorefrontSettings::class, function (): void {
    it('returns defaults when nothing is saved', function (): void {
        $settings = resolve(StorefrontSettings::class);

        expect($settings->get('hotline'))->toBeNull()
            ->and($settings->get('footer_columns'))->toBe([])
            ->and($settings->get('missing', 'fallback'))->toBe('fallback');
    });

    it('saves values under the `storefront_` prefix', function (): void {
        resolve(StorefrontSettings::class)->save([
            'hotline' => '1900 1234',
            'footer_columns' => [['title' => 'Chính sách', 'links' => []]],
        ]);

        expect(Setting::query()->where('key', 'storefront_hotline')->value('value'))->toBe('1900 1234')
            ->and(resolve(StorefrontSettings::class)->get('footer_columns'))->toBe([['title' => 'Chính sách', 'links' => []]]);
    });

    it('rejects unknown keys', function (): void {
        resolve(StorefrontSettings::class)->save(['not_a_setting' => 1]);
    })->throws(InvalidArgumentException::class);

    it('falls back to the store settings for hotline, logo and company name', function (): void {
        storefrontStoreSetting('name', 'Shop Global');
        storefrontStoreSetting('phone_number', '028 3822 1234');
        storefrontStoreSetting('logo', 'logos/shop.png');
        $settings = resolve(StorefrontSettings::class);

        expect($settings->hotline())->toBe('028 3822 1234')
            ->and($settings->logo())->toBe('logos/shop.png')
            ->and($settings->companyName())->toBe('Shop Global');

        storefrontStoreSetting('legal_name', 'Công ty TNHH Shop Global');
        $settings->save(['hotline' => '1900 1234', 'header_logo' => 7]);

        expect($settings->hotline())->toBe('1900 1234')
            ->and($settings->logo())->toBe(7)
            ->and($settings->companyName())->toBe('Công ty TNHH Shop Global')
            ->and($settings->storeName())->toBe('Shop Global');
    });

    it('refreshes when a store setting changes in Settings → General', function (): void {
        $settings = resolve(StorefrontSettings::class);
        storefrontStoreSetting('street_address', '12 Nguyễn Huệ');
        storefrontStoreSetting('city', 'TP. Hồ Chí Minh');

        expect($settings->address())->toBe('12 Nguyễn Huệ, TP. Hồ Chí Minh');

        storefrontStoreSetting('city', 'Hà Nội');

        expect($settings->address())->toBe('12 Nguyễn Huệ, Hà Nội');
    });

    it('lists the legally required footer details that are missing', function (): void {
        $settings = resolve(StorefrontSettings::class);

        expect($settings->missingLegalDetails())->toContain('company_name', 'business_registration', 'bct_url');

        storefrontStoreSetting('name', 'Shop Global');
        storefrontStoreSetting('street_address', '12 Nguyễn Huệ');
        storefrontStoreSetting('email', 'hello@shop.vn');
        $settings->save([
            'hotline' => '1900 1234',
            'business_registration' => '0312345678',
            'bct_url' => 'http://online.gov.vn/Home/WebDetails/1',
        ]);

        expect($settings->missingLegalDetails())->toBe([]);
    });
});
