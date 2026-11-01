<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Shopper\Cms\Models\PostCategory;
use Shopper\Core\Models\Setting;

final class ResolveDefaultCategoryAction
{
    public const string SETTING_KEY = 'cms_default_category_id';

    /**
     * The category posts fall back to (WordPress's "Uncategorized"). It is created on
     * demand if the setting is missing or points to a category that no longer exists.
     */
    public function execute(): PostCategory
    {
        $categoryId = Setting::query()->where('key', self::SETTING_KEY)->value('value');

        $category = $categoryId !== null ? PostCategory::query()->find($categoryId) : null;

        if ($category !== null) {
            return $category;
        }

        $category = PostCategory::query()->firstOrCreate(
            ['slug' => 'uncategorized'],
            ['name' => __('shopper-cms::taxonomies.uncategorized')],
        );

        Setting::query()->updateOrCreate(['key' => self::SETTING_KEY], [
            'value' => $category->id,
            'display_name' => __('shopper-cms::taxonomies.default_category'),
            'locked' => true,
        ]);

        return $category;
    }
}
