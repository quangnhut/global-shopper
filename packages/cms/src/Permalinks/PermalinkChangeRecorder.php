<?php

declare(strict_types=1);

namespace Shopper\Cms\Permalinks;

use Shopper\Cms\Actions\SaveRedirectAction;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\Redirect;

/**
 * WordPress's `_wp_old_slug`: when the public path of live content changes, the old path redirects (301)
 * to the new one. For pages, the paths of all descendants are followed too.
 *
 * Usage: `$before = $recorder->snapshot($post); …save…; $recorder->record($before);`
 */
final class PermalinkChangeRecorder
{
    public function __construct(
        private readonly PermalinkGenerator $generator,
        private readonly SaveRedirectAction $saveRedirect,
    ) {}

    /**
     * @return array<int, string> post id => current path, only for content that is live
     */
    public function snapshot(Post $post): array
    {
        if (! $post->exists) {
            return [];
        }

        $paths = [];

        foreach ($this->withDescendants($post) as $item) {
            if ($item->status === PostStatus::Published && ! $item->trashed()) {
                $paths[$item->id] = $this->generator->path($item);
            }
        }

        return $paths;
    }

    /**
     * @param  array<int, string>  $before
     */
    public function record(array $before): void
    {
        foreach ($before as $id => $oldPath) {
            $post = Post::configuredClass()::query()->find($id);

            if ($post === null) {
                continue;
            }

            $newPath = $this->generator->path($post);

            // The new path is live content again: a redirect away from it would hide it.
            Redirect::query()->where('from_path', Redirect::normalize($newPath))->delete();

            if (Redirect::normalize($oldPath) !== Redirect::normalize($newPath) && $oldPath !== '/' && ! str_starts_with($oldPath, '/?')) {
                $this->saveRedirect->execute($oldPath, $newPath, 301, Redirect::SOURCE_AUTO);
            }
        }
    }

    /**
     * @return list<Post>
     */
    private function withDescendants(Post $post): array
    {
        $items = [$post];

        if (! $post->postType()->hierarchical) {
            return $items;
        }

        $queue = [$post->id];
        $seen = [$post->id => true];

        while ($queue !== []) {
            $children = Post::configuredClass()::query()->whereIn('parent_id', $queue)->get();
            $queue = [];

            foreach ($children as $child) {
                if (! isset($seen[$child->id])) {
                    $seen[$child->id] = true;
                    $items[] = $child;
                    $queue[] = $child->id;
                }
            }
        }

        return $items;
    }
}
