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
     * @param  array<string, array{label: string, short: string, flag: string}>  $languages  Google Translate languages, empty when the picker is off
     */
    public function __construct(
        public ?string $storeName,
        public ?string $logo,
        public ?string $hotline,
        public ?string $announcement,
        public array $categories,
        public array $languages = [],
    ) {}

    public function translates(): bool
    {
        return count($this->languages) > 1;
    }

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
            'translate' => [
                'enabled' => $this->translates(),
                'page_language' => (string) config('shopper.storefront.translate.page_language', 'vi'),
                'script' => $this->translates() ? config('shopper.storefront.translate.script') : null,
                'languages' => array_map(
                    fn (string $code, array $language): array => ['code' => $code, ...$language],
                    array_keys($this->languages),
                    $this->languages,
                ),
            ],
        ];
    }
}
