<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostTag;
use Shopper\Cms\Permalinks\PermalinkChangeRecorder;
use Shopper\Cms\Support\CmsCache;

final class SyncPostTermsAction
{
    public function __construct(
        private readonly ResolveDefaultCategoryAction $defaultCategory,
        private readonly PermalinkChangeRecorder $permalinks,
    ) {}

    /**
     * Set a post's categories and tags like WordPress's post editor:
     * no category means the default one, and tags can be given as ids or as new names.
     *
     * @param  list<int|string>  $categoryIds
     * @param  list<int|string>  $tags  tag ids, or names (created when they don't exist)
     */
    public function execute(Post $post, array $categoryIds = [], array $tags = [], ?int $primaryCategoryId = null): Post
    {
        $postType = $post->postType();
        // A `%category%` structure puts the primary category in the URL.
        $livePaths = $this->permalinks->snapshot($post);

        if ($postType->supports('categories')) {
            $categoryIds = collect($categoryIds)->map(fn (int|string $id): int => (int) $id)->unique()->values();

            if ($categoryIds->isEmpty()) {
                $categoryIds = collect([$this->defaultCategory->execute()->id]);
            }

            $post->categories()->sync($categoryIds->all());

            $post->forceFill([
                'primary_category_id' => $this->primaryCategoryId($post, $categoryIds, $primaryCategoryId),
            ])->save();
        }

        if ($postType->supports('tags')) {
            $post->tags()->sync($this->tagIds($tags));
        }

        $this->permalinks->record($livePaths);
        // Pivot changes fire no model events.
        CmsCache::flush();

        return $post->load(['categories', 'tags']);
    }

    /**
     * @param  Collection<int, int>  $categoryIds
     */
    private function primaryCategoryId(Post $post, Collection $categoryIds, ?int $requested): int
    {
        foreach ([$requested, $post->primary_category_id] as $candidate) {
            if ($candidate !== null && $categoryIds->contains($candidate)) {
                return $candidate;
            }
        }

        return $categoryIds->first();
    }

    /**
     * @param  list<int|string>  $tags
     * @return list<int>
     */
    private function tagIds(array $tags): array
    {
        return collect($tags)
            ->map(function (int|string $tag): ?int {
                if (is_int($tag) || ctype_digit($tag)) {
                    return (int) $tag;
                }

                $name = mb_trim($tag);

                if ($name === '') {
                    return null;
                }

                return PostTag::query()
                    ->where('slug', Str::slug($name))
                    ->orWhere('name', $name)
                    ->value('id')
                    ?? PostTag::query()->create(['name' => $name])->id;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
