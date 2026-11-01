<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;

final class DeletePostCategoryAction
{
    public function __construct(private readonly ResolveDefaultCategoryAction $defaultCategory) {}

    /**
     * Delete a category like WordPress: the default category cannot be deleted, posts left
     * without a category move to the default one, and child categories move up a level.
     */
    public function execute(PostCategory $category): void
    {
        $default = $this->defaultCategory->execute();

        if ($category->is($default)) {
            throw ValidationException::withMessages([
                'category' => __('shopper-cms::taxonomies.cannot_delete_default'),
            ]);
        }

        DB::transaction(function () use ($category, $default): void {
            $category->children()->update(['parent_id' => $category->parent_id]);

            $category->posts()
                ->withTrashed()
                ->withCount('categories')
                ->get()
                ->each(function (Post $post) use ($category, $default): void {
                    if ($post->categories_count === 1) {
                        $post->categories()->attach($default->id);
                    }

                    if ($post->primary_category_id === $category->id) {
                        $post->forceFill([
                            'primary_category_id' => $post->categories()
                                ->whereKeyNot($category->id)
                                ->value($post->categories()->getRelated()->getQualifiedKeyName()),
                        ])->save();
                    }
                });

            $category->delete();
        });
    }
}
