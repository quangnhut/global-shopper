<?php

declare(strict_types=1);

namespace Shopper\Storefront\Data;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use Shopper\Storefront\Enums\HomeSectionLayout;
use Shopper\Storefront\Enums\HomeSectionStyle;
use Shopper\Storefront\Enums\HomeSectionType;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class SectionData implements Arrayable
{
    /**
     * @param  array<int, ProductCardData|PostCardData>  $items
     */
    public function __construct(
        public int $id,
        public HomeSectionType $type,
        public string $title,
        public ?string $subtitle,
        public HomeSectionStyle $style,
        public HomeSectionLayout $layout,
        public int $columns,
        public ?string $viewAllUrl,
        public ?CarbonInterface $endsAt,
        public array $items,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'style' => $this->style->value,
            'badge' => $this->style->badge(),
            'layout' => $this->layout->value,
            'columns' => $this->columns,
            'view_all_url' => $this->viewAllUrl,
            'ends_at' => $this->style->hasCountdown() ? $this->endsAt?->toIso8601String() : null,
            'items' => array_map(fn (ProductCardData|PostCardData $item): array => $item->toArray(), $this->items),
        ];
    }
}
