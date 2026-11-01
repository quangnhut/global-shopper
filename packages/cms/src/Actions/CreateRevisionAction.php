<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Shopper\Cms\Enums\RevisionKind;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostRevision;

final class CreateRevisionAction
{
    /**
     * Like WordPress, a revision is a copy of the post *after* a save that changed its title, content or
     * excerpt, so the latest revision always matches the post and any earlier one can be restored.
     */
    public function execute(Post $post, bool $created = false): ?PostRevision
    {
        if (! config('shopper.cms.revisions.enabled', true) || ! $post->postType()->supports('revisions')) {
            return null;
        }

        // `$created` comes from the caller: `wasRecentlyCreated` stays true for the model's lifetime.
        if (! $created && ! $post->wasChanged(['title', 'content', 'excerpt'])) {
            return null;
        }

        if (! $created && ! $post->revisions()->exists()) {
            $this->storeBaseline($post);
        }

        $revision = $post->revisions()->create([
            'kind' => RevisionKind::Revision,
            'user_id' => shopper()->auth()->id(),
            'title' => $post->title,
            'content' => $post->content,
            'excerpt' => $post->excerpt,
        ]);

        $this->prune($post);

        return $revision;
    }

    /**
     * Content saved before revisions existed (or while they were disabled) has no history: keep the version
     * this save replaced, so the first change can still be undone.
     */
    private function storeBaseline(Post $post): void
    {
        $previous = $post->getPrevious();

        $post->revisions()->create([
            'kind' => RevisionKind::Revision,
            'user_id' => $post->author_id,
            'title' => $previous['title'] ?? $post->title,
            'content' => $previous['content'] ?? $post->content,
            'excerpt' => $previous['excerpt'] ?? $post->excerpt,
            'created_at' => $previous['updated_at'] ?? $post->created_at,
            'updated_at' => $previous['updated_at'] ?? $post->created_at,
        ]);
    }

    /**
     * Keep the latest `shopper.cms.revisions.limit` revisions (null keeps all, like WP_POST_REVISIONS = true).
     */
    private function prune(Post $post): void
    {
        $limit = config('shopper.cms.revisions.limit');

        if ($limit === null) {
            return;
        }

        $keep = $post->revisions()->latest('id')->limit(max(0, (int) $limit))->pluck('id');

        $post->revisions()->whereNotIn('id', $keep)->delete();
    }
}
