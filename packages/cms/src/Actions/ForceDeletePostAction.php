<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Shopper\Cms\Models\Post;

final class ForceDeletePostAction
{
    /**
     * Delete permanently. Like WordPress's `wp_delete_post()`, child pages move up to
     * the deleted page's parent instead of being orphaned.
     */
    public function execute(Post $post): void
    {
        Post::configuredClass()::query()
            ->withTrashed()
            ->where('parent_id', $post->id)
            ->update(['parent_id' => $post->parent_id]);

        $post->forceDelete();
    }
}
