<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shopper\Cms\Http\Controllers\PublicContentController;

// Only loaded when `shopper.cms.routes.enabled` is true. A fallback route runs only when no other
// route matches, so the application's own pages always come first.
Route::middleware(config('shopper.cms.routes.middleware', ['web']))->group(function (): void {
    Route::fallback(PublicContentController::class)->name('shopper.cms.public');
    // A dedicated path: a catch-all POST route would swallow the application's own forms.
    Route::post('cms-unlock/{post}', [PublicContentController::class, 'unlock'])
        ->whereNumber('post')
        ->name('shopper.cms.public.unlock');
});
