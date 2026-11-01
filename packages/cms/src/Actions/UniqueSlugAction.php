<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Illuminate\Support\Str;
use Shopper\Cms\Models\Post;
use Shopper\Cms\PostTypes\PostTypeRegistry;

final class UniqueSlugAction
{
    public function __construct(private readonly PostTypeRegistry $postTypes) {}

    /**
     * Slugs are unique per post type, and for hierarchical types (pages) only among
     * siblings, so `/about/team` and `/careers/team` can coexist. Like WordPress,
     * duplicates get `-2`, `-3`, ... appended.
     */
    public function execute(string $value, string $type, ?int $parentId = null, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'untitled';
        $slug = $base;
        $suffix = 2;

        while ($this->exists($slug, $type, $parentId, $ignoreId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function exists(string $slug, string $type, ?int $parentId, ?int $ignoreId): bool
    {
        return Post::configuredClass()::query()
            ->withTrashed()
            ->where('type', $type)
            ->where('slug', $slug)
            ->when($this->postTypes->get($type)->hierarchical, fn ($query) => $query->where('parent_id', $parentId))
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
