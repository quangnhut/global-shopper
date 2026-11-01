<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Shopper\Cms\Models\Post;

final class TrashPostAction
{
    public const string TRASHED_SUFFIX = '__trashed';

    public const string DESIRED_SLUG_KEY = '_desired_slug';

    /**
     * Move to the trash (soft delete). The status is kept so a restore puts the post back as it was.
     * Like WordPress, the slug is renamed to `{slug}__trashed` so a new post can take it,
     * and the original slug is remembered for the restore.
     */
    public function execute(Post $post): Post
    {
        if ($post->trashed()) {
            return $post;
        }

        $post->forceFill([
            'slug' => $post->slug.self::TRASHED_SUFFIX,
            'metadata' => [...($post->metadata ?? []), self::DESIRED_SLUG_KEY => $post->slug],
        ])->save();

        $post->delete();

        return $post;
    }
}
