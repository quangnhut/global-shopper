<?php

declare(strict_types=1);

namespace Shopper\Storefront\Actions;

use Illuminate\Support\Facades\DB;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Models\HomeSectionItem;

/**
 * Copies a section and its items to the bottom of the page, disabled so it can be edited before it goes live.
 */
final class DuplicateHomeSectionAction
{
    public function execute(HomeSection $section): HomeSection
    {
        return DB::transaction(function () use ($section): HomeSection {
            // Fresh copy: the table passes rows carrying computed columns such as `items_count`.
            $source = HomeSection::query()->with('items')->findOrFail($section->id);
            $copy = $source->replicate(['position', 'is_enabled']);
            $copy->fill([
                'title' => __('shopper-storefront::admin.sections.copy_of', ['title' => $source->title]),
                'position' => (int) HomeSection::query()->max('position') + 1,
                'is_enabled' => false,
            ])->save();

            $copy->items()->createMany($source->items->map(fn (HomeSectionItem $item): array => [
                'itemable_type' => $item->itemable_type,
                'itemable_id' => $item->itemable_id,
                'position' => $item->position,
            ])->all());

            return $copy;
        });
    }
}
