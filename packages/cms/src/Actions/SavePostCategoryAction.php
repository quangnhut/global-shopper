<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Shopper\Cms\Models\PostCategory;

final class SavePostCategoryAction
{
    /**
     * @param  array<string, mixed>  $data  name, slug, description, parent_id, position
     */
    public function execute(array $data, ?PostCategory $category = null): PostCategory
    {
        $category ??= new PostCategory;

        $attributes = Arr::only($data, ['name', 'slug', 'description', 'parent_id', 'position']);

        if (array_key_exists('slug', $attributes) && blank($attributes['slug'])) {
            unset($attributes['slug']);
        }

        $parentId = $attributes['parent_id'] ?? null;

        // Compared in PHP: constraining the recursive `descendantsAndSelf()` query by key
        // references the table outside its CTE and fails.
        if ($parentId !== null && $category->exists
            && in_array((int) $parentId, $category->descendantsAndSelf()->pluck('id')->all(), true)) {
            throw ValidationException::withMessages([
                'parent_id' => __('shopper-cms::taxonomies.categories.invalid_parent'),
            ]);
        }

        $category->fill($attributes)->save();

        return $category;
    }
}
