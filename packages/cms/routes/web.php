<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shopper\Cms\Http\Controllers\PreviewPostController;

// Outside the admin on purpose: the signature is the authorization, so the link also works
// for a storefront or a reviewer without an admin account (and admin error pages don't apply).
Route::middleware(['web', 'signed'])
    ->prefix(config('shopper.cms.preview_path', 'cms-preview'))
    ->as('shopper.cms.')
    ->group(function (): void {
        Route::get('{post}', PreviewPostController::class)
            ->whereNumber('post')
            ->name('preview');
    });
