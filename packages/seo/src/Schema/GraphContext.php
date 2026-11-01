<?php

declare(strict_types=1);

namespace Shopper\Seo\Schema;

/**
 * What node factories need to link their nodes into the page's `@graph`.
 */
final readonly class GraphContext
{
    public function __construct(
        public string $url,
        public string $name,
        public ?string $description,
        public ?string $image,
        public string $language,
        public string $websiteId,
        public string $publisherId,
        public string $webpageId,
        public ?string $breadcrumbId = null,
        public string $schemaType = 'WebPage',
        public ?string $datePublished = null,
        public ?string $dateModified = null,
    ) {}

    /**
     * `@id` of a node of this page, e.g. `#product`.
     */
    public function id(string $fragment): string
    {
        return mb_rtrim(strtok($this->url, '#') ?: $this->url).'#'.mb_ltrim($fragment, '#');
    }
}
