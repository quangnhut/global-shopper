<?php

declare(strict_types=1);

namespace Shopper\Cms\Support;

use Illuminate\Support\Collection;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;

final class PostHierarchy
{
    /**
     * Depth of each item of a hierarchical type, keyed by id (trashed items included,
     * so children of a trashed page keep a stable depth).
     *
     * @return array<int, int>
     */
    public static function depths(string $type): array
    {
        $parents = Post::configuredClass()::query()
            ->withTrashed()
            ->ofType($type)
            ->pluck('parent_id', 'id')
            ->all();

        $depths = [];

        foreach (array_keys($parents) as $id) {
            $depth = 0;
            $parentId = $parents[$id];
            $seen = [$id => true];

            while ($parentId !== null && array_key_exists($parentId, $parents) && ! isset($seen[$parentId])) {
                $seen[$parentId] = true;
                $depth++;
                $parentId = $parents[$parentId];
            }

            $depths[$id] = $depth;
        }

        return $depths;
    }

    /**
     * Ids of a hierarchical type in WordPress's display order: depth-first, siblings by
     * menu order then title (trashed items included so every row has a position).
     *
     * @return list<int>
     */
    public static function orderedIds(string $type): array
    {
        $posts = Post::configuredClass()::query()
            ->withTrashed()
            ->ofType($type)
            ->orderBy('menu_order')
            ->orderBy('title')
            ->get(['id', 'parent_id']);

        $ids = $posts->pluck('id')->all();
        $children = $posts->groupBy(fn (Post $post): int => $post->parent_id !== null && in_array($post->parent_id, $ids, true) ? $post->parent_id : 0);
        $ordered = [];

        $walk = function (int $parentId) use (&$walk, &$ordered, $children): void {
            foreach ($children->get($parentId, collect()) as $post) {
                $ordered[] = $post->id;
                $walk($post->id);
            }
        };

        $walk(0);

        return $ordered;
    }

    /**
     * Indented options of non-trashed items of a hierarchical type, ordered like WordPress
     * (by menu order, then title, children under their parent). `$exclude` and its descendants
     * are left out so an item cannot become its own ancestor.
     *
     * @return array<int, string>
     */
    public static function parentOptions(string $type, ?int $exclude = null): array
    {
        $posts = Post::configuredClass()::query()
            ->ofType($type)
            ->orderBy('menu_order')
            ->orderBy('title')
            ->get(['id', 'title', 'parent_id']);

        $children = $posts->groupBy(fn (Post $post): int => $post->parent_id ?? 0);
        $options = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$options, $children, $exclude): void {
            foreach ($children->get($parentId, collect()) as $post) {
                if ($post->id === $exclude) {
                    continue;
                }

                $options[$post->id] = str_repeat('— ', $depth).($post->title !== '' ? $post->title : __('shopper-cms::posts.no_title'));
                $walk($post->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function categoryOptions(): array
    {
        return PostCategory::tree()
            ->depthFirst()
            ->get()
            ->mapWithKeys(fn (PostCategory $category): array => [
                $category->id => str_repeat('— ', (int) $category->getAttribute('depth')).$category->name,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $ids
     * @return array<int, string>
     */
    public static function categoryNames(Collection|array $ids): array
    {
        return PostCategory::query()->whereKey($ids)->orderBy('name')->pluck('name', 'id')->all();
    }
}
