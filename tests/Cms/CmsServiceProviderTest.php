<?php

declare(strict_types=1);

use Shopper\Cms\CmsAddon;
use Shopper\Cms\CmsServiceProvider;

uses(Tests\Cms\TestCase::class);

describe(CmsServiceProvider::class, function (): void {
    it('merges the cms config under `shopper.cms`', function (): void {
        expect(config('shopper.cms.trash_days'))->toBe(30)
            ->and(config('shopper.cms.post_types'))->toHaveKeys(['post', 'page']);
    });

    it('registers the `cms` addon on the Shopper panel', function (): void {
        expect(shopper()->hasAddon('cms'))->toBeTrue()
            ->and(shopper()->getAddon('cms'))->toBeInstanceOf(CmsAddon::class);
    });

    it('loads the cms translations', function (): void {
        expect(__('shopper-cms::post-types.post.plural'))->toBe('Posts');
    });
})->group('cms');
