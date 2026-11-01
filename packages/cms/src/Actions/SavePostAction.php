<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Enums\PostVisibility;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Permalinks\PermalinkChangeRecorder;
use Shopper\Cms\PostTypes\PostTypeRegistry;
use Shopper\Cms\Support\HtmlSanitizer;

final class SavePostAction
{
    public function __construct(
        private readonly PostTypeRegistry $postTypes,
        private readonly UniqueSlugAction $uniqueSlug,
        private readonly HtmlSanitizer $sanitizer,
        private readonly CreateRevisionAction $createRevision,
        private readonly PermalinkChangeRecorder $permalinks,
    ) {}

    /**
     * Create or update a post or page, applying WordPress's publishing rules.
     *
     * Accepted keys: type, title, slug, excerpt, content, status, visibility, password,
     * published_at, author_id, parent_id, menu_order, template, featured_upload_id, metadata.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Post $post = null): Post
    {
        $post ??= new (Post::configuredClass());

        $type = $data['type'] ?? $post->type ?? 'post';

        if (! $this->postTypes->has($type)) {
            throw ValidationException::withMessages(['type' => __('shopper-cms::messages.unknown_type', ['type' => $type])]);
        }

        $attributes = Arr::only($data, [
            'title', 'excerpt', 'content', 'author_id', 'menu_order', 'template', 'featured_upload_id', 'metadata',
        ]);
        // Rich text is sanitized on every save, whatever the source (editor, API, import).
        foreach (['content', 'excerpt'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = $this->sanitizer->clean($attributes[$field]);
            }
        }

        $attributes['type'] = $type;
        $attributes['title'] ??= $post->title ?? '';
        $attributes['parent_id'] = $this->parentId($data, $post, $type);

        $publishedAt = array_key_exists('published_at', $data)
            ? ($data['published_at'] !== null ? Carbon::parse($data['published_at']) : null)
            : $post->published_at;

        $status = $data['status'] ?? $post->status ?? PostStatus::Draft;
        $status = $status instanceof PostStatus ? $status : PostStatus::from($status);

        $attributes = [
            ...$attributes,
            ...$this->visibilityAttributes($data, $post, $status),
        ];

        [$attributes['status'], $attributes['published_at']] = $this->resolvePublishing($attributes['status'], $publishedAt);

        $slugSource = filled($data['slug'] ?? null) ? $data['slug'] : ($post->slug ?? $attributes['title']);

        if (! $post->exists || filled($data['slug'] ?? null) || $post->parent_id !== $attributes['parent_id']) {
            $attributes['slug'] = $this->uniqueSlug->execute($slugSource, $type, $attributes['parent_id'], $post->id);
        }

        $created = ! $post->exists;
        $livePaths = $this->permalinks->snapshot($post);

        $post->fill($attributes)->save();

        $this->permalinks->record($livePaths);

        $this->createRevision->execute($post, $created);

        return $post;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: PostStatus, password?: ?string}
     */
    private function visibilityAttributes(array $data, Post $post, PostStatus $status): array
    {
        if (! array_key_exists('visibility', $data)) {
            return ['status' => $status];
        }

        $visibility = $data['visibility'] instanceof PostVisibility
            ? $data['visibility']
            : PostVisibility::from($data['visibility']);

        return match ($visibility) {
            PostVisibility::Private => ['status' => PostStatus::Private, 'password' => null],
            PostVisibility::Password => $this->passwordAttributes($data, $post, $status),
            PostVisibility::Public => [
                'status' => $status === PostStatus::Private ? PostStatus::Published : $status,
                'password' => null,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: PostStatus, password?: string}
     */
    private function passwordAttributes(array $data, Post $post, PostStatus $status): array
    {
        $status = $status === PostStatus::Private ? PostStatus::Published : $status;

        if (filled($data['password'] ?? null)) {
            return ['status' => $status, 'password' => $data['password']];
        }

        if ($post->isPasswordProtected()) {
            return ['status' => $status];
        }

        throw ValidationException::withMessages(['password' => __('shopper-cms::messages.password_required')]);
    }

    /**
     * @return array{0: PostStatus, 1: ?CarbonInterface}
     */
    private function resolvePublishing(PostStatus $status, ?CarbonInterface $publishedAt): array
    {
        return match ($status) {
            PostStatus::Published, PostStatus::Scheduled => match (true) {
                $publishedAt === null && $status === PostStatus::Scheduled => throw ValidationException::withMessages([
                    'published_at' => __('shopper-cms::messages.schedule_date_required'),
                ]),
                $publishedAt === null => [PostStatus::Published, now()],
                $publishedAt->isFuture() => [PostStatus::Scheduled, $publishedAt],
                default => [PostStatus::Published, $publishedAt],
            },
            PostStatus::Private => [PostStatus::Private, $publishedAt ?? now()],
            default => [$status, $publishedAt],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parentId(array $data, Post $post, string $type): ?int
    {
        if (! $this->postTypes->get($type)->hierarchical) {
            return null;
        }

        $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $post->parent_id;

        if ($parentId === null || ! $post->exists) {
            return $parentId;
        }

        $ancestorId = (int) $parentId;

        // Walk up from the new parent: reaching this post means the move would create a loop.
        while ($ancestorId !== 0) {
            if ($ancestorId === $post->id) {
                throw ValidationException::withMessages(['parent_id' => __('shopper-cms::messages.invalid_parent')]);
            }

            $ancestorId = (int) Post::configuredClass()::query()->withTrashed()->whereKey($ancestorId)->value('parent_id');
        }

        return (int) $parentId;
    }
}
