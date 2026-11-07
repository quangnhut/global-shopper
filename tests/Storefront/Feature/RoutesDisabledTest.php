<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\Storefront\TestCase::class);

it('does not register the Blade storefront routes unless enabled', function (): void {
    expect(Route::has('shopper.storefront.home'))->toBeFalse()
        ->and(Route::has('shopper.storefront.login'))->toBeFalse()
        ->and(Route::has('shopper.storefront.api.home'))->toBeTrue();
});
