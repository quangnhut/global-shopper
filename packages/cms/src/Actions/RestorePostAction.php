<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Illuminate\Support\Arr;
use Shopper\Cms\Models\Post;

final class RestorePostAction
{
    public function __construct(private readonly UniqueSlugAction $uniqueSlug) {}

    /**
     * Restore from the trash with the status it had. The original slug comes back,
     * or its next free variant (`-2`) if another post took it in the meantime.
     */
    public function execute(Post $post): Post
    {
        if (! $post->trashed()) {
            return $post;
        }

        $metadata = $post->metadata ?? [];
        $desiredSlug = $metadata[TrashPostAction::DESIRED_SLUG_KEY]
            ?? str($post->slug)->beforeLast(TrashPostAction::TRASHED_SUFFIX)->toString();

        $post->restore();

        $post->forceFill([
            'slug' => $this->uniqueSlug->execute($desiredSlug, $post->type, $post->parent_id, $post->id),
            'metadata' => Arr::except($metadata, TrashPostAction::DESIRED_SLUG_KEY) ?: null,
        ])->save();

        return $post;
    }
}
