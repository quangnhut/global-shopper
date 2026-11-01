<?php

declare(strict_types=1);

namespace Tests\Cms;

use Shopper\Cms\CmsServiceProvider;
use Shopper\Core\Models\Setting;
use Shopper\Seo\SeoServiceProvider;

abstract class TestCase extends \Tests\Admin\TestCase
{
    /**
     * Admin pages redirect to the setup screen until the store has an email and an address.
     */
    protected function configureStore(): void
    {
        foreach (['email' => 'store@example.com', 'street_address' => '1 Main Street'] as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'locked' => true]);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            SeoServiceProvider::class,
            CmsServiceProvider::class,
        ];
    }
}
