<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Carbon\CarbonInterface;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Models\Post;

final class PublishPostAction
{
    public function __construct(private readonly SavePostAction $savePost) {}

    /**
     * Publish now, or schedule when `$at` is in the future (the "Publish" / "Schedule" button).
     */
    public function execute(Post $post, ?CarbonInterface $at = null): Post
    {
        return $this->savePost->execute([
            'status' => PostStatus::Published,
            'published_at' => $at ?? now(),
        ], $post);
    }
}
