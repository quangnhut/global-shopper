<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Shopper\Cms\Enums\RevisionKind;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostRevision;
use Shopper\Cms\Support\HtmlSanitizer;

final class AutosavePostAction
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /**
     * Keep one autosave per user and post (WordPress's autosave). The post itself is never changed.
     *
     * @param  array{title?: ?string, content?: ?string, excerpt?: ?string}  $data
     */
    public function execute(Post $post, array $data, ?int $userId): PostRevision
    {
        return PostRevision::query()->updateOrCreate(
            [
                'post_id' => $post->id,
                'user_id' => $userId,
                'kind' => RevisionKind::Autosave,
            ],
            [
                'title' => (string) ($data['title'] ?? ''),
                'content' => $this->sanitizer->clean($data['content'] ?? null),
                'excerpt' => $this->sanitizer->clean($data['excerpt'] ?? null),
            ],
        );
    }
}
