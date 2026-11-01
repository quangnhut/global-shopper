<?php

declare(strict_types=1);

namespace Shopper\Seo\Data;

/**
 * Everything a page needs in its `<head>` and around `<body>`, ready to render or to send as JSON.
 *
 * `meta` items are `['name' => …, 'content' => …]` or `['property' => …, 'content' => …]`; `links` items
 * are `['rel' => …, 'href' => …]` plus optional attributes (`sizes`, `type`).
 */
final readonly class SeoData
{
    /**
     * @param  array<int, array<string, string>>  $meta
     * @param  array<int, array<string, string>>  $links
     * @param  array<string, mixed>|null  $jsonLd
     */
    public function __construct(
        public string $type,
        public string $title,
        public ?string $description,
        public ?string $canonical,
        public string $robots,
        public array $meta = [],
        public array $links = [],
        public ?array $jsonLd = null,
        public ?string $gtm = null,
        public ?string $ga4 = null,
        public ?string $customHead = null,
        public ?string $customBodyOpen = null,
        public ?string $customBodyClose = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) $data['type'],
            title: (string) $data['title'],
            description: $data['description'] ?? null,
            canonical: $data['canonical'] ?? null,
            robots: (string) $data['robots'],
            meta: $data['meta'] ?? [],
            links: $data['links'] ?? [],
            jsonLd: $data['json_ld'] ?? null,
            gtm: $data['gtm'] ?? null,
            ga4: $data['ga4'] ?? null,
            customHead: $data['custom_head'] ?? null,
            customBodyOpen: $data['custom_body_open'] ?? null,
            customBodyClose: $data['custom_body_close'] ?? null,
        );
    }

    public function isIndexable(): bool
    {
        return ! str_contains($this->robots, 'noindex');
    }

    public function metaContent(string $key): ?string
    {
        foreach ($this->meta as $meta) {
            if (($meta['name'] ?? $meta['property'] ?? null) === $key) {
                return $meta['content'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'robots' => $this->robots,
            'meta' => $this->meta,
            'links' => $this->links,
            'json_ld' => $this->jsonLd,
            'gtm' => $this->gtm,
            'ga4' => $this->ga4,
            'custom_head' => $this->customHead,
            'custom_body_open' => $this->customBodyOpen,
            'custom_body_close' => $this->customBodyClose,
        ];
    }
}
