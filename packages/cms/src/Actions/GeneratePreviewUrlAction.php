<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Illuminate\Support\Facades\URL;
use Shopper\Cms\Models\Post;

final class GeneratePreviewUrlAction
{
    /**
     * A signed link that shows unpublished content without logging in, so it can be shared
     * with a storefront or a reviewer. It expires after `shopper.cms.preview_minutes`.
     */
    public function execute(Post $post): string
    {
        return URL::temporarySignedRoute(
            'shopper.cms.preview',
            now()->addMinutes((int) config('shopper.cms.preview_minutes', 30)),
            ['post' => $post->id],
        );
    }

    /**
     * Same, for the JSON API (`GET {api}/cms/preview/{post}`), so a storefront can render the draft itself.
     */
    public function api(Post $post): string
    {
        return URL::temporarySignedRoute(
            'shopper.cms.api.preview',
            now()->addMinutes((int) config('shopper.cms.preview_minutes', 30)),
            ['post' => $post->id],
        );
    }
}
