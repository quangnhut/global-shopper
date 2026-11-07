<?php

declare(strict_types=1);

namespace Tests\Storefront;

/**
 * Boots the application with the optional Blade storefront routes switched on.
 */
abstract class PublicRoutesTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('shopper.storefront.routes.enabled', true);
    }
}
