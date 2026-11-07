<?php

declare(strict_types=1);

namespace Shopper\Storefront\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shopper\Core\Traits\HasModelContract;
use Shopper\Storefront\Database\Factories\HomeSectionFactory;
use Shopper\Storefront\Enums\HomeSectionLayout;
use Shopper\Storefront\Enums\HomeSectionStyle;
use Shopper\Storefront\Enums\HomeSectionType;
use Shopper\Storefront\Models\Contracts\HomeSection as HomeSectionContract;

/**
 * A block of the home page. `position` is its priority: lower values are shown higher on the page.
 *
 * @property-read int $id
 * @property-read HomeSectionType $type
 * @property-read string $title
 * @property-read ?string $subtitle
 * @property-read HomeSectionStyle $style
 * @property-read HomeSectionLayout $layout
 * @property-read int $columns
 * @property-read ?int $limit
 * @property-read ?string $view_all_url
 * @property-read int $position
 * @property-read bool $is_enabled
 * @property-read ?CarbonInterface $starts_at
 * @property-read ?CarbonInterface $ends_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, HomeSectionItem> $items
 */
class HomeSection extends Model implements HomeSectionContract
{
    /** @use HasFactory<HomeSectionFactory> */
    use HasFactory;

    use HasModelContract;

    protected $guarded = [];

    public static function configuredClass(): string
    {
        return config('shopper.storefront.models.home_section', static::class);
    }

    public function getTable(): string
    {
        return shopper_table('storefront_home_sections');
    }

    /**
     * @return HasMany<HomeSectionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(HomeSectionItem::configuredClass(), 'home_section_id')->orderBy('position');
    }

    /**
     * Enabled and inside its schedule.
     */
    public function isLive(): bool
    {
        return $this->is_enabled
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function isScheduled(): bool
    {
        return $this->starts_at !== null && $this->starts_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    /**
     * @param  Builder<HomeSection>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('is_enabled', true)
            ->where(fn (Builder $query): Builder => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query): Builder => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * @param  Builder<HomeSection>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    protected static function newFactory(): HomeSectionFactory
    {
        return HomeSectionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => HomeSectionType::class,
            'style' => HomeSectionStyle::class,
            'layout' => HomeSectionLayout::class,
            'columns' => 'integer',
            'limit' => 'integer',
            'position' => 'integer',
            'is_enabled' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
