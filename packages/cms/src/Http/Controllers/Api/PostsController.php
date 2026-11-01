<?php

declare(strict_types=1);

namespace Shopper\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shopper\Cms\Http\Resources\PostResource;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Support\CmsCache;

/**
 * `GET {api}/cms/posts?type=post&category=news&tag=laravel&search=…&page=2` → published content, newest first.
 */
final class PostsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:32'],
            'category' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $body = CmsCache::remember('posts:'.http_build_query($filters), function () use ($filters, $request): array {
            $posts = Post::configuredClass()::query()
                ->ofType($filters['type'] ?? 'post')
                ->published()
                ->when($filters['category'] ?? null, fn ($query, string $slug) => $query->whereHas('categories', fn ($query) => $query->where('slug', $slug)))
                ->when($filters['tag'] ?? null, fn ($query, string $slug) => $query->whereHas('tags', fn ($query) => $query->where('slug', $slug)))
                ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")))
                ->with(['author', 'featuredImage', 'categories', 'tags'])
                ->latest('published_at')
                ->paginate((int) config('shopper.cms.api.per_page', 10), page: (int) ($filters['page'] ?? 1));

            return PostResource::collection($posts)->response($request)->getData(true);
        });

        return response()->json($body);
    }
}
