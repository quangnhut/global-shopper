<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostRevision;

final class RestoreRevisionAction
{
    public function __construct(private readonly SavePostAction $savePost) {}

    /**
     * Copy a revision back into its post. Saving through `SavePostAction` records the restore as a new
     * revision, so it can be undone like any other change (WordPress's "Restore This Revision").
     */
    public function execute(PostRevision $revision): Post
    {
        /** @var Post $post */
        $post = $revision->post;

        return $this->savePost->execute([
            'title' => $revision->title,
            'content' => $revision->content,
            'excerpt' => $revision->excerpt,
        ], $post);
    }
}
