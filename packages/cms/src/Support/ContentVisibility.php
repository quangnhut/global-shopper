<?php

declare(strict_types=1);

namespace Shopper\Cms\Support;

use Illuminate\Support\Facades\Hash;
use Shopper\Cms\Models\Post;

/**
 * Who may see content on the public side (API and optional web routes). Drafts, pending, scheduled and
 * private content are never public; they are only reachable through a signed preview link.
 */
final class ContentVisibility
{
    public static function isPublic(Post $post): bool
    {
        return ! $post->trashed() && $post->isPublished();
    }

    public static function unlocks(Post $post, ?string $password): bool
    {
        return ! $post->isPasswordProtected()
            || (filled($password) && Hash::check((string) $password, (string) $post->password));
    }
}
