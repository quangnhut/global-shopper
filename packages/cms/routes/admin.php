<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shopper\Cms\Http\Controllers\EditorUploadController;
use Shopper\Cms\Livewire\Pages;

Route::as('cms.')->group(function (): void {
    Route::post('cms/editor/upload', EditorUploadController::class)->name('editor.upload');

    Route::prefix('setting')->as('settings.')->group(function (): void {
        Route::get('permalinks', Pages\Settings\Permalinks::class)->name('permalinks');
        Route::get('redirects', Pages\Settings\Redirects::class)->name('redirects');
    });

    Route::prefix('posts')->group(function (): void {
        Route::get('/', Pages\Posts\Index::class)->name('posts.index');
        Route::get('create', Pages\Posts\Editor::class)->name('posts.create');
        Route::get('{post}/edit', Pages\Posts\Editor::class)->name('posts.edit')->withTrashed();
        Route::get('{post}/revisions', Pages\Posts\Revisions::class)->name('posts.revisions');
        Route::get('categories', Pages\PostCategories\Index::class)->name('post-categories.index');
        Route::get('tags', Pages\PostTags\Index::class)->name('post-tags.index');
    });

    Route::prefix('pages')->group(function (): void {
        Route::get('/', Pages\Pages\Index::class)->name('pages.index');
        Route::get('create', Pages\Pages\Editor::class)->name('pages.create');
        Route::get('{post}/edit', Pages\Pages\Editor::class)->name('pages.edit')->withTrashed();
        Route::get('{post}/revisions', Pages\Posts\Revisions::class)->name('pages.revisions');
    });
});
