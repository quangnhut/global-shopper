<?php

declare(strict_types=1);

namespace Shopper\Storefront\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class HomePageData implements Arrayable
{
    /**
     * @param  array<int, SectionData>  $sections
     */
    public function __construct(
        public HeaderData $header,
        public array $sections,
        public FooterData $footer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'header' => $this->header->toArray(),
            'sections' => array_map(fn (SectionData $section): array => $section->toArray(), $this->sections),
            'footer' => $this->footer->toArray(),
        ];
    }
}
