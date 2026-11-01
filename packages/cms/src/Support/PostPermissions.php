<?php

declare(strict_types=1);

namespace Shopper\Cms\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Models\Post;
use Shopper\Cms\PostTypes\PostType;

/**
 * WordPress's capability rules: authors manage their own content, `edit_others_*` is needed for
 * anyone else's, and only users who may publish can change content that is already live.
 */
final class PostPermissions
{
    public static function canPublish(?Authorizable $user, PostType $type): bool
    {
        return (bool) $user?->can($type->permission('publish'));
    }

    public static function canCreate(?Authorizable $user, PostType $type): bool
    {
        return (bool) $user?->can($type->permission('add'));
    }

    public static function canEdit(?Authorizable $user, Post $post): bool
    {
        return self::canManage($user, $post, 'edit');
    }

    public static function canDelete(?Authorizable $user, Post $post): bool
    {
        return self::canManage($user, $post, 'delete');
    }

    private static function canManage(?Authorizable $user, Post $post, string $ability): bool
    {
        if ($user === null) {
            return false;
        }

        $type = $post->postType();
        $isOwn = $post->author_id !== null && $post->author_id === $user->getAuthIdentifier();
        $isLive = in_array($post->status, [PostStatus::Published, PostStatus::Scheduled, PostStatus::Private], true);

        return $user->can($type->permission($ability))
            && ($isOwn || $user->can($type->permission('edit', others: true)))
            && (! $isLive || self::canPublish($user, $type));
    }
}
