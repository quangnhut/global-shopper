<?php

declare(strict_types=1);

namespace Shopper\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shopper\Cms\Http\Resources\PostResource;
use Shopper\Cms\Http\Resources\TermResource;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Cms\Models\PostTag;
use Shopper\Cms\Permalinks\PermalinkResolver;
use Shopper\Cms\Support\CmsCache;
use Shopper\Cms\Support\ContentVisibility;
use Shopper\Seo\SeoManager;

/**
 * `GET {api}/cms/resolve?path=/2026/10/hello-world` → what a storefront should render for that path.
 */
final class ResolveController
{
    public function __invoke(Request $request, PermalinkResolver $resolver): JsonResponse
    {
        $request->validate(['path' => ['required', 'string', 'max:2048'], 'page' => ['nullable', 'integer', 'min:1']]);

        $path = (string) $request->query('path');
        $page = (int) $request->query('page', 1);
        $password = $request->header('X-Post-Password');

        // Responses that depend on a password are never cached.
        [$body, $status] = filled($password)
            ? $this->respond($request, $resolver, $path, $page)
            : CmsCache::remember(
                "resolve:{$path}:{$page}",
                fn (): array => $this->respond($request, $resolver, $path, $page),
                // Redirects are not cached so their hits keep being counted.
                fn (array $response): bool => $response[1] === 200,
            );

        return response()->json($body, $status);
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function respond(Request $request, PermalinkResolver $resolver, string $path, int $page): array
    {
        $resolved = $resolver->resolve($path);

        if ($resolved === null) {
            return [['type' => 'not_found'], 404];
        }

        if ($resolved->isRedirect()) {
            return [['type' => 'redirect', 'redirect' => $resolved->redirectTo, 'status' => $resolved->status], $resolved->status];
        }

        if ($resolved->type === 'gone') {
            return [['type' => 'gone'], 410];
        }

        $model = $resolved->model;

        if ($model instanceof Post) {
            if (! ContentVisibility::isPublic($model)) {
                return [['type' => 'not_found'], 404];
            }

            $model->load(['author', 'featuredImage', 'categories', 'tags']);

            return [[
                'type' => $resolved->type,
                'data' => PostResource::make($model)->resolve($request),
                'seo' => resolve(SeoManager::class)->for($model)->toArray(),
            ], 200];
        }

        /** @var PostCategory|PostTag $model */
        $posts = $model->posts()
            ->published()
            ->with(['author', 'featuredImage', 'categories', 'tags'])
            ->latest('published_at')
            ->paginate((int) config('shopper.cms.api.per_page', 10), page: $page);

        return [[
            'type' => $resolved->type,
            'data' => TermResource::make($model)->resolve($request),
            'posts' => PostResource::collection($posts)->response($request)->getData(true),
            'seo' => resolve(SeoManager::class)->for($model, ['page' => $posts->currentPage(), 'last_page' => $posts->lastPage()])->toArray(),
        ], 200];
    }
}
