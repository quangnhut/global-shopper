<?php

declare(strict_types=1);

namespace Shopper\Storefront\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Shopper\Core\Traits\HasModelContract;
use Shopper\Storefront\Models\Contracts\HomeSectionItem as HomeSectionItemContract;

/**
 * A product or post picked for a section, in the order the admin set.
 *
 * @property-read int $id
 * @property-read int $home_section_id
 * @property-read string $itemable_type
 * @property-read int $itemable_id
 * @property-read int $position
 * @property-read HomeSection $section
 * @property-read ?Model $itemable
 */
class HomeSectionItem extends Model implements HomeSectionItemContract
{
    use HasModelContract;

    public $timestamps = false;

    protected $guarded = [];

    public static function configuredClass(): string
    {
        return config('shopper.storefront.models.home_section_item', static::class);
    }

    public function getTable(): string
    {
        return shopper_table('storefront_home_section_items');
    }

    /**
     * @return BelongsTo<HomeSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::configuredClass(), 'home_section_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}
