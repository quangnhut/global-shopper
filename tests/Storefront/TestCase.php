<?php

declare(strict_types=1);

namespace Tests\Storefront;

use Shopper\OnePay\OnePayServiceProvider;
use Shopper\Storefront\StorefrontServiceProvider;

/**
 * Storefront tests boot the CMS and SEO packages too (post sections, head tags).
 */
abstract class TestCase extends \Tests\Cms\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            OnePayServiceProvider::class,
            StorefrontServiceProvider::class,
        ];
    }
}
