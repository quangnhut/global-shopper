<?php

declare(strict_types=1);

namespace Tests\Cms;

/**
 * Boots the application with the optional public CMS routes switched on.
 */
abstract class PublicRoutesTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('shopper.cms.routes.enabled', true);
    }
}
