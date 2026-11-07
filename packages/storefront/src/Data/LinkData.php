<?php

declare(strict_types=1);

namespace Shopper\Storefront\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A link with an optional image (menu entry, footer link, badge, partner logo).
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class LinkData implements Arrayable
{
    /**
     * @param  array<int, LinkData>  $children
     */
    public function __construct(
        public string $label,
        public ?string $url,
        public ?string $image = null,
        public array $children = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'label' => $this->label,
            'url' => $this->url,
            'image' => $this->image,
            'children' => array_map(fn (LinkData $child): array => $child->toArray(), $this->children),
        ], fn (mixed $value, string $key): bool => $key === 'url' || $key === 'label' || filled($value), ARRAY_FILTER_USE_BOTH);
    }
}
