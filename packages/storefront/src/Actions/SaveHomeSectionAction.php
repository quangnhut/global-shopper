<?php

declare(strict_types=1);

namespace Shopper\Storefront\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Shopper\Storefront\Enums\HomeSectionType;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Support\SectionItems;
use Shopper\Storefront\Support\StorefrontCache;

/**
 * Creates or updates a section and replaces its items with the given ids, in that order.
 * The type of an existing section never changes: its items would no longer match.
 */
final class SaveHomeSectionAction
{
    private const array FIELDS = [
        'type', 'title', 'subtitle', 'style', 'layout', 'columns', 'limit', 'view_all_url', 'is_enabled', 'starts_at', 'ends_at',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string|null>  $itemIds
     */
    public function execute(?HomeSection $section, array $attributes, array $itemIds): HomeSection
    {
        // The items are replaced with query deletes, which fire no model events.
        return tap(DB::transaction(function () use ($section, $attributes, $itemIds): HomeSection {
            $values = Arr::only($attributes, self::FIELDS);

            if ($section?->exists) {
                unset($values['type']);
                $section->update($values);
            } else {
                $section = HomeSection::query()->create([
                    ...$values,
                    'position' => (int) HomeSection::query()->max('position') + 1,
                ]);
            }

            $morph = $section->type === HomeSectionType::Products ? SectionItems::productMorph() : SectionItems::postMorph();
            $ids = collect($itemIds)->filter(fn (mixed $id): bool => filled($id))->map(fn (mixed $id): int => (int) $id)->unique()->values();

            $section->items()->delete();
            $section->items()->createMany($ids->map(fn (int $id, int $position): array => [
                'itemable_type' => $morph,
                'itemable_id' => $id,
                'position' => $position,
            ])->all());

            return $section->refresh();
        }), function (): void {
            StorefrontCache::flush();
        });
    }
}
