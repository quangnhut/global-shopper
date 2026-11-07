<?php

declare(strict_types=1);

namespace Shopper\Storefront\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class HeaderData implements Arrayable
{
    /**
     * @param  array<int, LinkData>  $categories
     */
    public function __construct(
        public ?string $storeName,
        public ?string $logo,
        public ?string $hotline,
        public ?string $announcement,
        public array $categories,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'store_name' => $this->storeName,
            'logo' => $this->logo,
            'hotline' => $this->hotline,
            'announcement' => $this->announcement,
            'categories' => array_map(fn (LinkData $link): array => $link->toArray(), $this->categories),
        ];
    }
}
