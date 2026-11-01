<?php

declare(strict_types=1);

namespace Shopper\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shopper\Cms\Http\Resources\PostResource;
use Shopper\Cms\Models\Post;

/**
 * Signed preview of unpublished content for storefronts (`GeneratePreviewUrlAction::api()`).
 */
final class PreviewController
{
    public function __invoke(Request $request, int $post): JsonResponse
    {
        $post = Post::configuredClass()::query()
            ->with(['author', 'featuredImage', 'categories', 'tags'])
            ->findOrFail($post);

        // A preview shows the content even when it is password protected.
        $data = PostResource::make($post)->resolve($request);
        $data['excerpt'] = $post->excerpt;
        $data['content'] = $post->content;

        return response()->json(['type' => $post->type, 'data' => $data, 'preview' => true, 'seo' => null]);
    }
}
