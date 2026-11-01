<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shopper\Cms\Http\Controllers\Api;

Route::middleware(config('shopper.cms.api.middleware', ['api']))
    ->prefix(mb_trim((string) config('shopper.cms.api.prefix', 'api'), '/').'/cms')
    ->as('shopper.cms.api.')
    ->group(function (): void {
        Route::get('resolve', Api\ResolveController::class)->name('resolve');
        Route::get('posts', Api\PostsController::class)->name('posts');
        Route::get('preview/{post}', Api\PreviewController::class)->whereNumber('post')->middleware('signed')->name('preview');
    });
