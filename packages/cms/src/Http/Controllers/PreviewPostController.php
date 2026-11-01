<?php

declare(strict_types=1);

namespace Shopper\Cms\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Shopper\Cms\Models\Post;

final class PreviewPostController
{
    public function __invoke(Request $request, int $post): View
    {
        $post = Post::configuredClass()::query()->findOrFail($post);

        return view('shopper-cms::preview', [
            'post' => $post,
            'expiresAt' => now()->setTimestamp((int) $request->query('expires')),
        ]);
    }
}
